<?php

namespace App\Services\ExpressGifts;

use App\Models\Order;
use App\Models\OrderProductBonification;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aplica sobre un pedido Express ya creado el prorrateo del valor de sus obsequios
 * facturados y deja guardado todo lo que FvDynamicsService necesita para armar la factura.
 */
class ExpressGiftAllocationService
{
    public function __construct(private readonly GiftProrationService $proration)
    {
    }

    /**
     * @return array{success: bool, applied: bool, message: ?string, result: array<string, mixed>|null}
     */
    public function allocate(Order $order): array
    {
        $order->load(['products.product.tax', 'bonifications.product.tax', 'bonifications.orderProduct', 'bonifications.bonification.products', 'bonifications.spendingGift.products']);

        $giftRows = $order->bonifications->filter(fn (OrderProductBonification $b) => $b->isInvoiced());
        if ($giftRows->isEmpty()) {
            return ['success' => true, 'applied' => false, 'message' => null, 'result' => null];
        }

        $lines = [];
        foreach ($order->products as $orderProduct) {
            $product = $orderProduct->product;
            if (! $product) {
                continue;
            }

            $pricing = OrderRepository::resolveXmlPricing($orderProduct, $product, 0, (int) $order->id, false);
            if ((float) $pricing['discount_percentage'] > 0 || (float) $pricing['flat_discount_amount'] > 0) {
                continue; // Lineas con descuento no reciben prorrateo.
            }

            $packageQty = max(1, (int) ($orderProduct->package_quantity ?? 1));
            $qty = $product->calculate_package_price
                ? (int) $orderProduct->quantity * $packageQty
                : (int) $orderProduct->quantity;

            $lines[] = [
                'key' => (int) $orderProduct->id,
                'product_id' => (int) $orderProduct->product_id,
                'qty' => $qty,
                'unit_price' => (float) $pricing['unit_price'],
                'tax_rate' => (float) ($product->tax->tax ?? 0),
            ];
        }

        $gifts = $giftRows->map(function (OrderProductBonification $b) {
            if ($b->spending_gift_id) {
                $triggerIds = $b->spendingGift->products->pluck('id')->map(fn ($id) => (int) $id)->all();
            } elseif ($b->bonification && $b->bonification->sumsProducts()) {
                $triggerIds = $b->bonification->products->pluck('id')->map(fn ($id) => (int) $id)->all();
            } else {
                $triggerIds = [(int) ($b->orderProduct->product_id ?? 0)];
            }

            return [
                'key' => (int) $b->id,
                'qty' => (int) $b->quantity,
                'unit_value' => (float) $b->invoice_unit_value,
                'tax_rate' => (float) ($b->product->tax->tax ?? 0),
                'scope' => $b->prorationScope(),
                'trigger_product_id' => (int) ($triggerIds[0] ?? 0),
                'trigger_product_ids' => $triggerIds,
            ];
        })->values()->all();

        $result = $this->proration->prorateScoped($lines, $gifts);
        $failedIds = array_map('intval', array_keys($result['failed'] ?? []));

        if ($result['gift_totals'] === [] && $failedIds !== []) {
            Log::warning('Express gifts: allocation not feasible at checkout', [
                'order_id' => $order->id,
                'code' => $result['code'],
                'reason' => $result['reason'],
            ]);

            return [
                'success' => false,
                'applied' => false,
                'message' => 'No se pudo aplicar el obsequio: '.$result['reason'],
                'failed_ids' => $failedIds,
                'result' => $result,
            ];
        }

        foreach ($result['deductions'] as $orderProductId => $deduction) {
            DB::table('order_products')
                ->where('id', $orderProductId)
                ->update(['gift_deduction_per_unit' => $deduction]);
        }
        foreach ($result['gift_totals'] as $bonificationRowId => $total) {
            OrderProductBonification::query()
                ->whereKey($bonificationRowId)
                ->update(['invoice_total_value' => $total]);
        }

        return [
            'success' => true,
            'applied' => true,
            'message' => $failedIds === [] ? null : 'No se pudo aplicar el obsequio: '.($result['reason'] ?? ''),
            'failed_ids' => $failedIds,
            'result' => $result,
        ];
    }

    /**
     * Quita los obsequios facturados del pedido y devuelve su inventario.
     * Se usa cuando el prorrateo no es posible al cerrar el pedido.
     */
    public function releaseInvoicedGifts(Order $order, bool $isInventoryEnabled, ?string $bodega, array $onlyIds = []): int
    {
        $query = OrderProductBonification::query()
            ->where('order_id', $order->id)
            ->whereNotNull('invoice_unit_value')
            ->with('product');
        if ($onlyIds !== []) {
            $query->whereIn('id', $onlyIds);
        }
        $rows = $query->get();

        foreach ($rows as $row) {
            $giftProduct = $row->product;
            if ($isInventoryEnabled && $bodega && $giftProduct && $giftProduct->isInventoryManaged()) {
                $inventory = $giftProduct
                    ->inventoryQueryForBodega($bodega, $row->variation_item_id ? (int) $row->variation_item_id : null)
                    ?->lockForUpdate()
                    ->first();
                if ($inventory) {
                    $inventory->update(['available' => (int) $inventory->available + (int) $row->quantity]);
                }
            }
            $row->delete();
        }

        return $rows->count();
    }

    /**
     * Lineas de obsequio ya prorrateadas, listas para la factura FV.
     *
     * @return array<int, array{sku: string, qty: int, unit_price: float, tax_value: float}>
     */
    public function invoiceLines(Order $order): array
    {
        $order->loadMissing(['bonifications.product.tax']);

        $lines = [];
        foreach ($order->bonifications as $row) {
            if (! $row->isInvoiced() || $row->invoice_total_value === null) {
                continue;
            }

            $sku = $this->resolveGiftSku($row);
            if ($sku === '') {
                Log::channel('soap')->warning('FV gift line skipped - product without SKU', [
                    'order_id' => $order->id,
                    'product_id' => $row->product_id,
                ]);

                continue;
            }

            $taxRate = (float) ($row->product->tax->tax ?? 0);
            foreach (GiftProrationService::splitGiftTotal((float) $row->invoice_total_value, (int) $row->quantity) as $part) {
                $net = $part['qty'] * $part['unit_price'];
                $lines[] = [
                    'sku' => $sku,
                    'qty' => $part['qty'],
                    'unit_price' => $part['unit_price'],
                    'tax_value' => round($net * $taxRate / 100, 2),
                ];
            }
        }

        return $lines;
    }

    private function resolveGiftSku(OrderProductBonification $row): string
    {
        if ($row->variation_item_id) {
            $variationSku = DB::table('product_item_variation')
                ->where('product_id', $row->product_id)
                ->where('variation_item_id', $row->variation_item_id)
                ->value('sku');
            if (! empty($variationSku)) {
                return (string) $variationSku;
            }
        }

        return (string) ($row->product->sku ?? '');
    }
}
