<?php

namespace App\Services\ExpressGifts;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Decide, por producto del carrito, si el cliente recibe el descuento O el obsequio
 * (nunca los dos) y verifica que el prorrateo del obsequio sea posible.
 *
 * Reglas:
 *  1. Producto sin descuento + bonificacion calificada  -> obsequio.
 *  2. Producto con descuento + bonificacion calificada  -> gana lo mas favorable para el
 *     cliente (ahorro del descuento vs. valor de catalogo del obsequio). Empate: descuento.
 *  3. Si el obsequio resultante no se puede prorratear (alguna linea quedaria en cero o
 *     bajo el piso), no se aplica ningun obsequio, se conservan los descuentos y se avisa.
 *
 * En Express la bandera allow_discounts de la bonificacion se ignora: la regla es siempre
 * "uno u otro".
 */
class ExpressGiftPlanner
{
    public function __construct(private readonly GiftProrationService $proration)
    {
    }

    public static function isEnabled(): bool
    {
        return (bool) config('express_gifts.enabled', true);
    }

    /**
     * @param  iterable<int|string, array<string, mixed>>  $cart  Filas con product_id, quantity y variation_id opcional.
     * @return array{
     *     blocked: array<int, true>,
     *     denied: array<int, true>,
     *     granted: array<int, true>,
     *     decisions: array<int, array<string, mixed>>,
     *     messages: array<int, string>,
     *     proration: array<string, mixed>|null
     * }
     */
    public function plan(iterable $cart, bool $hasOrders): array
    {
        $previous = ExpressGiftContext::current();
        ExpressGiftContext::activate(ExpressGiftContext::MODE_PLANNING);

        try {
            return $this->buildPlan(collect($cart)->values()->all(), $hasOrders);
        } finally {
            ExpressGiftContext::deactivate();
            if ($previous !== null) {
                app()->instance(ExpressGiftContext::class, $previous);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function buildPlan(array $rows, bool $hasOrders): array
    {
        $plan = [
            'blocked' => [],
            'denied' => [],
            'granted' => [],
            'decisions' => [],
            'messages' => [],
            'proration' => null,
            'failed_gift_keys' => [],
        ];

        // Aggregate by product (all variations count together, like classic bonifications).
        $byProduct = [];
        foreach ($rows as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $byProduct[$productId]['cart_units'] = ($byProduct[$productId]['cart_units'] ?? 0) + (int) ($row['quantity'] ?? 1);
        }

        $products = Product::with(['brand.vendor', 'tax', 'items', 'bonifications'])
            ->whereIn('id', array_keys($byProduct))
            ->get()
            ->keyBy('id');

        $giftRequests = []; // key => ['qty','unit_value','tax_rate']
        foreach ($byProduct as $productId => $info) {
            /** @var Product|null $product */
            $product = $products[$productId] ?? null;
            if (! $product || $product->bonifications->isEmpty()) {
                continue;
            }

            $packageQty = max(1, (int) ($product->package_quantity ?? 1));
            $units = $info['cart_units'] * $packageQty;

            $qualified = [];
            $giftCatalogValue = 0.0;
            foreach ($product->bonifications as $bonification) {
                $buy = (int) $bonification->buy;
                if ($buy <= 0) {
                    continue;
                }
                $qty = (int) floor($units / $buy * $bonification->get);
                if ($qty <= 0) {
                    continue;
                }
                if ($bonification->max !== null && $qty > (int) $bonification->max) {
                    $qty = (int) $bonification->max;
                }

                $giftProduct = Product::with(['items', 'tax'])->find($bonification->product_id);
                if (! $giftProduct) {
                    continue;
                }

                $unitValue = $bonification->giftInvoiceUnitValue();
                $catalogUnit = $this->catalogUnitPrice($giftProduct);
                $giftCatalogValue += ($catalogUnit > 0 ? $catalogUnit : $unitValue) * $qty;

                $qualified[] = [
                    'key' => $productId.':'.$bonification->id,
                    'qty' => $qty,
                    'unit_value' => $unitValue,
                    'tax_rate' => (float) ($giftProduct->tax->tax ?? 0),
                    'scope' => $bonification->giftProrationScope(),
                    'trigger_product_id' => $productId,
                    'bonification_id' => (int) $bonification->id,
                ];
            }

            if ($qualified === []) {
                continue;
            }

            $final = $product->getFinalPriceForUser($hasOrders);
            $discountSavings = max(0.0, (float) ($final['totalDiscount'] ?? 0)) * $info['cart_units'];

            if ($discountSavings <= 0) {
                $decision = 'gift';
            } elseif ($discountSavings >= $giftCatalogValue) {
                $decision = 'discount';
            } else {
                $decision = 'gift_over_discount';
            }

            $plan['decisions'][$productId] = [
                'product' => $product->name,
                'decision' => $decision,
                'discount_savings' => round($discountSavings, 2),
                'gift_value' => round($giftCatalogValue, 2),
            ];

            if ($decision === 'discount') {
                $plan['denied'][$productId] = true;
                $plan['messages'][] = "En \"{$product->name}\" se aplicó el descuento porque es más favorable que el obsequio.";

                continue;
            }

            $plan['granted'][$productId] = true;
            if ($decision === 'gift_over_discount') {
                $plan['blocked'][$productId] = true;
                $plan['messages'][] = "En \"{$product->name}\" se aplicó el obsequio porque es más favorable que el descuento.";
            }
            foreach ($qualified as $gift) {
                $giftRequests[$gift['key']] = $gift;
            }
        }

        if ($giftRequests === []) {
            return $plan;
        }

        // Feasibility check: prorate against lines that stay discount-free.
        $lines = $this->eligibleLines($rows, $products, $plan['blocked'], $hasOrders);
        $result = $this->proration->prorateScoped($lines, array_values($giftRequests));
        $plan['proration'] = $result;
        $plan['failed_gift_keys'] = $result['failed'] ?? [];

        if ($plan['failed_gift_keys'] !== []) {
            Log::warning('Express gifts: some prorations were not feasible', [
                'code' => $result['code'],
                'reason' => $result['reason'],
                'failed' => array_keys($plan['failed_gift_keys']),
            ]);

            $giftsLeftByProduct = [];
            foreach ($giftRequests as $key => $gift) {
                $productId = (int) $gift['trigger_product_id'];
                if (! isset($plan['failed_gift_keys'][$key])) {
                    $giftsLeftByProduct[$productId] = true;
                }
            }

            foreach (array_keys($plan['granted']) as $productId) {
                if (isset($giftsLeftByProduct[$productId])) {
                    continue;
                }
                unset($plan['granted'][$productId], $plan['blocked'][$productId]);
                $plan['denied'][$productId] = true;
            }

            $plan['messages'][] = 'No se pudo aplicar el obsequio: '.($result['reason']
                ?: 'el prorrateo no cabe en los productos configurados.').' Se conservaron los descuentos de esos productos.';
        }

        return $plan;
    }

    /**
     * Lines (in invoice units) that carry no discount once the plan is applied.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, true>  $blocked
     * @return array<int, array{key: string, qty: int, unit_price: float, tax_rate: float}>
     */
    private function eligibleLines(array $rows, $products, array $blocked, bool $hasOrders): array
    {
        $previous = ExpressGiftContext::current();
        ExpressGiftContext::activate(ExpressGiftContext::MODE_ACTIVE, $blocked);

        try {
            $lines = [];
            foreach ($rows as $index => $row) {
                $product = $products[(int) ($row['product_id'] ?? 0)] ?? null;
                if (! $product) {
                    continue;
                }

                $discount = (float) ($product->getFinalPriceForUser($hasOrders)['discount'] ?? 0);
                if ($discount > 0) {
                    continue;
                }

                $variationId = $row['variation_id'] ?? null;
                $unitPrice = (float) $product->price;
                if ($variationId) {
                    $variation = $product->items->firstWhere('id', $variationId);
                    if ($variation && isset($variation->pivot->price)) {
                        $unitPrice = (float) $variation->pivot->price;
                    }
                } elseif ($product->items->isNotEmpty() && isset($product->items->first()->pivot->price)) {
                    $unitPrice = (float) $product->items->first()->pivot->price;
                }

                $packageQty = max(1, (int) ($product->package_quantity ?? 1));
                $qty = (int) ($row['quantity'] ?? 1) * ($product->calculate_package_price ? $packageQty : 1);

                $lines[] = [
                    'key' => 'cart:'.$index,
                    'product_id' => (int) $product->id,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'tax_rate' => (float) ($product->tax->tax ?? 0),
                ];
            }

            return $lines;
        } finally {
            ExpressGiftContext::deactivate();
            if ($previous !== null) {
                app()->instance(ExpressGiftContext::class, $previous);
            }
        }
    }

    private function catalogUnitPrice(Product $product): float
    {
        $variation = $product->items->first();
        if ($variation && isset($variation->pivot->price)) {
            return (float) $variation->pivot->price;
        }

        return (float) $product->price;
    }
}
