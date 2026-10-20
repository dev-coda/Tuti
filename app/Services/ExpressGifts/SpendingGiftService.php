<?php

namespace App\Services\ExpressGifts;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderProductBonification;
use App\Models\Product;
use App\Models\SpendingGift;
use App\Services\BonificationCheckoutService;
use Illuminate\Support\Facades\Log;

/**
 * Obsequio único de Express cuando la compra de ciertos productos supera un monto.
 * Cada regla tiene su propio obsequio y su propio umbral.
 */
class SpendingGiftService
{
    /**
     * @param  array<int, array{product_id:int|string, quantity:int|string}>  $cart
     * @return array{grants: array<int, array{gift: SpendingGift, spent: float}>}
     */
    public function qualify(array $cart, bool $hasOrders): array
    {
        $rules = SpendingGift::query()
            ->where('active', true)
            ->with(['products:id', 'giftProduct'])
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            return ['grants' => []];
        }

        $spent = $this->spentByProduct($cart, $hasOrders);
        $grants = [];

        foreach ($rules as $gift) {
            $ids = $gift->products->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($ids === [] || ! $gift->giftProduct) {
                continue;
            }

            $total = 0.0;
            foreach ($ids as $id) {
                $total += $spent[$id] ?? 0;
            }
            $total = round($total, 2);

            if ($total + 0.009 < (float) $gift->minimum_amount) {
                continue;
            }

            $grants[] = ['gift' => $gift, 'spent' => $total];
        }

        return ['grants' => $grants];
    }

    /**
     * Crea las líneas de obsequio facturables. Si no hay stock, esa regla se omite.
     *
     * @param  array<int, array{product_id:int|string, quantity:int|string}>  $cart
     * @return array{messages: array<int, string>}
     */
    public function attachToOrder(Order $order, array $cart, bool $hasOrders, bool $inventoryEnabled, ?string $bodega): array
    {
        $messages = [];

        foreach ($this->qualify($cart, $hasOrders)['grants'] as $grant) {
            /** @var SpendingGift $gift */
            $gift = $grant['gift'];
            $gift->loadMissing(['giftProduct.items', 'giftProduct.categories', 'products']);
            $catalog = $gift->giftProduct;
            $qty = max(1, (int) $gift->gift_quantity);
            $triggerIds = $gift->products->pluck('id')->map(fn ($id) => (int) $id)->all();

            $anchor = OrderProduct::query()
                ->where('order_id', $order->id)
                ->whereIn('product_id', $triggerIds)
                ->orderBy('id')
                ->first();

            if (! $anchor || ! $catalog) {
                continue;
            }

            $variationItemId = BonificationCheckoutService::resolveGiftVariationItemId(
                $catalog,
                (int) $order->id,
                $inventoryEnabled ? $bodega : null,
                $qty
            );

            if ($inventoryEnabled && $bodega && $catalog->isInventoryManaged()) {
                $inventory = $catalog->inventoryQueryForBodega($bodega, $variationItemId)?->lockForUpdate()->first();
                $available = (int) ($inventory?->available ?? 0);
                if (! BonificationCheckoutService::hasEnoughStockForRequestedUnits($available, $catalog, $qty)) {
                    $messages[] = 'No se pudo entregar el obsequio "'.$gift->name.'": no hay stock suficiente.';
                    Log::warning('Spending gift skipped, insufficient stock', [
                        'order_id' => $order->id,
                        'spending_gift_id' => $gift->id,
                        'available' => $available,
                        'requested' => $qty,
                    ]);

                    continue;
                }
                if ($inventory) {
                    $inventory->update(['available' => $available - $qty]);
                }
            }

            OrderProductBonification::create([
                'bonification_id' => null,
                'spending_gift_id' => $gift->id,
                'order_product_id' => $anchor->id,
                'product_id' => $catalog->id,
                'variation_item_id' => $variationItemId,
                'quantity' => $qty,
                'order_id' => $order->id,
                'invoice_unit_value' => $gift->giftInvoiceUnitValue(),
                'proration_scope' => $gift->giftProrationScope(),
            ]);

            $messages[] = sprintf(
                'Obsequio "%s": se entrega %s porque la compra de los productos asociados llegó a $%s (mínimo $%s).',
                $gift->name,
                $catalog->name,
                number_format((float) $grant['spent'], 0, ',', '.'),
                number_format((float) $gift->minimum_amount, 0, ',', '.')
            );
        }

        return ['messages' => $messages];
    }

    /**
     * @param  array<int, array{product_id:int|string, quantity:int|string}>  $cart
     * @return array<int, float>
     */
    private function spentByProduct(array $cart, bool $hasOrders): array
    {
        $ids = collect($cart)->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->all();
        if ($ids === []) {
            return [];
        }

        $products = Product::query()->with('tax')->whereIn('id', $ids)->get()->keyBy('id');
        $spent = [];

        foreach ($cart as $row) {
            $product = $products->get((int) ($row['product_id'] ?? 0));
            if (! $product) {
                continue;
            }
            $unit = (float) ($product->getFinalPriceForUser($hasOrders)['price'] ?? 0);
            $spent[$product->id] = round(($spent[$product->id] ?? 0) + ($unit * (int) $row['quantity']), 2);
        }

        return $spent;
    }
}
