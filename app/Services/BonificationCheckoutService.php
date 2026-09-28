<?php

namespace App\Services;

use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Setting;

class BonificationCheckoutService
{
    /**
     * Product IDs that must not receive brand/vendor/coupon discounts because the cart
     * qualifies for a bonification with allow_discounts=false.
     *
     * Same rule as checkout: only when the customer actually qualifies (buy/get threshold).
     *
     * @param  iterable<int, array<string, mixed>>  $cart
     * @return array<int, true> product_id => true
     */
    public static function discountBlockedProductIds(iterable $cart): array
    {
        $quantities = [];

        foreach ($cart as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $tempProduct = Product::find($productId);
            if (! $tempProduct) {
                continue;
            }

            $packageQuantity = (int) ($tempProduct->package_quantity ?? 1);
            $quantities[$productId] = ($quantities[$productId] ?? 0)
                + ((int) ($row['quantity'] ?? 1)) * $packageQuantity;
        }

        $blocked = [];

        foreach ($quantities as $productId => $aggregatedIndividualItems) {
            $product = Product::with('bonifications')->find($productId);
            if (! $product || $product->bonifications->isEmpty()) {
                continue;
            }

            foreach ($product->bonifications as $bonification) {
                $buy = (int) $bonification->buy;
                if ($buy <= 0) {
                    continue;
                }

                $bonificationQuantity = floor($aggregatedIndividualItems / $buy * $bonification->get);
                if ($bonificationQuantity > 0 && ! $bonification->allow_discounts) {
                    $blocked[$productId] = true;
                    break;
                }
            }
        }

        return $blocked;
    }

    /**
     * Last cart key per product_id (cart order preserved) so bonifications are applied after
     * all order lines and inventory updates for that product in the current loop.
     */
    public static function lastCartKeyByProductId(array $cart): array
    {
        $last = [];
        foreach ($cart as $k => $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            if ($pid > 0) {
                $last[$pid] = $k;
            }
        }

        return $last;
    }

    /**
     * Match CartController: product safety stock if set, otherwise global minimum inventory.
     */
    public static function effectiveInventoryFloor(Product $product): int
    {
        $product->loadMissing('categories');
        $safety = (int) $product->getEffectiveSafetyStock();
        $globalMin = (int) (Setting::getByKey('global_minimum_inventory') ?? 5);

        return $safety > 0 ? $safety : $globalMin;
    }

    /**
     * Enforce the same floor rule as paid lines: after gifting, disponible may not go below
     * safety or global minimum.
     */
    public static function minRequestedUnitsGivenAvailable(
        int $disponible,
        Product $giftProduct,
        int $requestedUnits
    ): int {
        if ($requestedUnits <= 0) {
            return 0;
        }
        if (! $giftProduct->isInventoryManaged()) {
            return $requestedUnits;
        }
        $floor = self::effectiveInventoryFloor($giftProduct);
        $maxGivable = max(0, $disponible - $floor);

        return min($requestedUnits, $maxGivable);
    }

    /**
     * True only when inventory can satisfy the full requested bonification quantity while
     * keeping the same minimum floor used by checkout inventory rules.
     */
    public static function hasEnoughStockForRequestedUnits(
        int $disponible,
        Product $giftProduct,
        int $requestedUnits
    ): bool {
        return self::minRequestedUnitsGivenAvailable($disponible, $giftProduct, $requestedUnits) >= $requestedUnits;
    }

    /**
     * Human-readable reason for the inventory floor applied to a product.
     *
     * @return array{floor: int, label: string, uses_product_safety: bool}
     */
    public static function inventoryFloorDetails(Product $product): array
    {
        $product->loadMissing('categories');
        $safety = (int) $product->getEffectiveSafetyStock();
        $globalMin = (int) (Setting::getByKey('global_minimum_inventory') ?? 5);
        $usesProductSafety = $safety > 0;
        $floor = $usesProductSafety ? $safety : $globalMin;

        return [
            'floor' => $floor,
            'label' => $usesProductSafety
                ? "stock de seguridad del producto ({$floor})"
                : "mínimo global de inventario ({$floor})",
            'uses_product_safety' => $usesProductSafety,
        ];
    }

    /**
     * Clear Spanish message when a bonification gift cannot be fulfilled from zone stock.
     * Distinguishes "no inventory row", "raw shortage", and "blocked by safety/global floor"
     * so checkout does not look like a paid cart line failed.
     */
    public static function insufficientGiftStockMessage(
        Product $giftProduct,
        int $disponible,
        int $requestedUnits,
        ?string $variationLabel = null
    ): string {
        $name = trim((string) $giftProduct->name) !== '' ? $giftProduct->name : 'el producto de obsequio';
        $variationSuffix = $variationLabel
            ? " (variación: {$variationLabel})"
            : '';

        if ($disponible <= 0) {
            return "No se puede aplicar la bonificación: el obsequio «{$name}»{$variationSuffix}"
                .' no tiene inventario disponible en la bodega de tu zona.'
                ." Se requieren {$requestedUnits} unidad(es) de obsequio."
                .' Revisa el stock del producto de regalo (no el del producto comprado).';
        }

        $floor = self::inventoryFloorDetails($giftProduct);
        $maxGivable = max(0, $disponible - $floor['floor']);

        if ($maxGivable < $requestedUnits && $disponible >= $requestedUnits) {
            return "No se puede aplicar la bonificación: el obsequio «{$name}»{$variationSuffix}"
                ." requiere {$requestedUnits} unidad(es), hay {$disponible} disponible(s) en tu zona,"
                ." pero el {$floor['label']} deja solo {$maxGivable} unidad(es) entregables."
                .' Baja la cantidad del producto que activa la bonificación o espera reposición del obsequio.';
        }

        return "No se puede aplicar la bonificación: el obsequio «{$name}»{$variationSuffix}"
            ." requiere {$requestedUnits} unidad(es) y solo hay {$disponible} disponible(s) en tu zona"
            ." (entregables tras {$floor['label']}: {$maxGivable})."
            .' Revisa el stock del producto de regalo (no el del producto comprado).';
    }

    /**
     * Clear Spanish message when a paid cart line fails the final inventory check.
     */
    public static function insufficientPaidLineStockMessage(
        Product $product,
        int $disponible,
        int $requestedQty
    ): string {
        $name = trim((string) $product->name) !== '' ? $product->name : 'el producto';
        $floor = self::inventoryFloorDetails($product);
        $remainingAfter = $disponible - $requestedQty;

        if ($requestedQty > $disponible) {
            return "Inventario insuficiente para «{$name}»: solicitas {$requestedQty} unidad(es)"
                ." y solo hay {$disponible} disponible(s) en tu zona.";
        }

        if ($disponible <= $floor['floor']) {
            return "Inventario insuficiente para «{$name}»: hay {$disponible} disponible(s) en tu zona,"
                ." pero está por debajo o en el {$floor['label']}.";
        }

        return "Inventario insuficiente para «{$name}»: solicitas {$requestedQty} unidad(es),"
            ." hay {$disponible} disponible(s) en tu zona, y tras el pedido quedarían {$remainingAfter}"
            ." (debe quedar al menos el {$floor['label']}).";
    }

    /**
     * Preview whether qualified bonification gifts can be fulfilled from zone stock,
     * after subtracting cart demand for the same gift SKU/variation.
     *
     * Intentionally not wired as a hard checkout gate: keep CartController's
     * lockForUpdate path as the sole authority to avoid false rejects from
     * pre-check vs in-transaction variation resolution drift.
     *
     * @param  array<int|string, array<string, mixed>>  $cart
     * @return string|null Error message, or null when stock is OK / inventory not enforced
     */
    public static function validateCartGiftStock(array $cart, ?string $bodega): ?string
    {
        if (! $bodega) {
            return null;
        }

        $productQuantities = [];
        $cartDemandByStockKey = [];

        foreach ($cart as $row) {
            $productId = (int) ($row['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $product = Product::with(['categories', 'items'])->find($productId);
            if (! $product) {
                continue;
            }

            $packageQuantity = (int) ($product->package_quantity ?? 1);
            $lineQty = (int) ($row['quantity'] ?? 0);
            $productQuantities[$productId] = ($productQuantities[$productId] ?? 0) + ($lineQty * $packageQuantity);

            $variationItemId = isset($row['variation_id']) ? (int) $row['variation_id'] : null;
            $stockKey = $productId.':'.($variationItemId ?: 'base');
            $cartDemandByStockKey[$stockKey] = ($cartDemandByStockKey[$stockKey] ?? 0) + $lineQty;
        }

        if (empty($productQuantities)) {
            return null;
        }

        $giftDemandByStockKey = [];
        $giftMetaByStockKey = [];

        $triggers = Product::with(['bonifications.product.items', 'bonifications.product.categories'])
            ->whereIn('id', array_keys($productQuantities))
            ->get()
            ->keyBy('id');

        foreach ($productQuantities as $productId => $aggregatedItems) {
            $trigger = $triggers->get($productId);
            if (! $trigger || $trigger->bonifications->isEmpty()) {
                continue;
            }

            foreach ($trigger->bonifications as $bonification) {
                $buy = (int) ($bonification->buy ?? 0);
                $get = (int) ($bonification->get ?? 0);
                if ($buy <= 0 || $get <= 0) {
                    continue;
                }

                $giftQty = (int) floor(($aggregatedItems / $buy) * $get);
                // Match CartController clamp order (including max=0 → no gift).
                if ($giftQty <= 0) {
                    continue;
                }
                if ($giftQty > (int) ($bonification->max ?? 0)) {
                    $giftQty = (int) $bonification->max;
                }
                if ($giftQty <= 0) {
                    continue;
                }

                $giftProduct = $bonification->product;
                if (! $giftProduct || ! $giftProduct->isInventoryManaged()) {
                    continue;
                }

                // Match checkout: if the gift is already a paid cart line with a variation,
                // resolveGiftVariationItemId will lock to that OrderProduct variation after insert.
                // Prefer the same variation here so pre-check does not false-reject/accept.
                $variationItemId = self::firstCartVariationItemIdForProduct($cart, (int) $giftProduct->id)
                    ?? self::resolveGiftVariationItemId(
                        $giftProduct,
                        0,
                        $bodega,
                        $giftQty
                    );
                $stockKey = ((int) $giftProduct->id).':'.($variationItemId ?: 'base');
                $giftDemandByStockKey[$stockKey] = ($giftDemandByStockKey[$stockKey] ?? 0) + $giftQty;
                $giftMetaByStockKey[$stockKey] = [
                    'product' => $giftProduct,
                    'variation_item_id' => $variationItemId,
                ];
            }
        }

        foreach ($giftDemandByStockKey as $stockKey => $requestedTotal) {
            $meta = $giftMetaByStockKey[$stockKey];
            /** @var Product $giftProduct */
            $giftProduct = $meta['product'];
            $variationItemId = $meta['variation_item_id'];
            $disponible = (int) $giftProduct->getInventoryForBodega($bodega, $variationItemId);
            $cartDemand = (int) ($cartDemandByStockKey[$stockKey] ?? 0);
            $remainingAfterCart = max(0, $disponible - $cartDemand);

            if (self::hasEnoughStockForRequestedUnits($remainingAfterCart, $giftProduct, $requestedTotal)) {
                continue;
            }

            $variationLabel = null;
            if ($variationItemId) {
                $item = $giftProduct->items->firstWhere('id', $variationItemId);
                $variationLabel = $item?->name;
            }

            $message = self::insufficientGiftStockMessage(
                $giftProduct,
                $remainingAfterCart,
                $requestedTotal,
                $variationLabel
            );

            if ($cartDemand > 0) {
                $message .= " Nota: el carrito ya reserva {$cartDemand} unidad(es) de este mismo producto.";
            }

            return $message;
        }

        return null;
    }

    /**
     * First non-null variation_id for a product already in the cart (cart insertion order).
     * Mirrors OrderProduct::orderBy('id')->first() once paid lines are written at checkout.
     */
    public static function firstCartVariationItemIdForProduct(array $cart, int $productId): ?int
    {
        if ($productId <= 0) {
            return null;
        }

        foreach ($cart as $row) {
            if ((int) ($row['product_id'] ?? 0) !== $productId) {
                continue;
            }

            $variationId = $row['variation_id'] ?? null;
            if ($variationId === null || $variationId === '' || (int) $variationId === 0) {
                continue;
            }

            return (int) $variationId;
        }

        return null;
    }

    public static function giftProductHasEnabledItems(Product $product): bool
    {
        return $product->items()->wherePivot('enabled', true)->exists();
    }

    public static function stockProductForSelectedVariation(Product $product, ?int $variationItemId): Product
    {
        return $product->stockProductForSelectedVariation($variationItemId);
    }

    public static function selectedVariationSku(Product $product, ?int $variationItemId): ?string
    {
        return $product->selectedVariationSku($variationItemId);
    }

    /**
     * Variation to send on the gift (obsequio) line so SAP/XML gets a resolvable itemId.
     *
     * Resolution order:
     *   1. Variation already chosen by the customer for this gift product in the same order.
     *   2. When inventory is enabled and a bodega is known: parent inventory if it satisfies
     *      the requested gift, otherwise the first enabled variation that does. This handles
     *      products whose parent has a (legacy/placeholder) SKU but is not actually stocked,
     *      while real inventory lives on per-variation SKUs.
     *   3. When the parent has no base SKU at all, fall back to any enabled variation with a
     *      per-variation SKU so SAP receives a resolvable itemId.
     */
    public static function resolveGiftVariationItemId(
        Product $giftProduct,
        int $orderId,
        ?string $bodega = null,
        int $requestedUnits = 0
    ): ?int {
        if (! self::giftProductHasEnabledItems($giftProduct)) {
            return null;
        }

        $existing = OrderProduct::query()
            ->where('order_id', $orderId)
            ->where('product_id', $giftProduct->id)
            ->whereNotNull('variation_item_id')
            ->orderBy('id')
            ->first();
        if ($existing) {
            return $existing->variation_item_id;
        }

        $parentSku = trim((string) ($giftProduct->sku ?? ''));

        if ($bodega && $requestedUnits > 0 && $giftProduct->isInventoryManaged()) {
            $parentAvailable = (int) $giftProduct->getInventoryForBodega($bodega, null);
            $parentEnough = self::hasEnoughStockForRequestedUnits(
                $parentAvailable,
                $giftProduct,
                $requestedUnits
            );

            if ($parentSku !== '' && $parentEnough) {
                return null;
            }

            $variationWithStock = self::firstEnabledVariationWithEnoughStock(
                $giftProduct,
                $bodega,
                $requestedUnits
            );
            if ($variationWithStock !== null) {
                return $variationWithStock;
            }

            if ($parentSku !== '') {
                return null;
            }
        }

        if ($parentSku !== '') {
            return null;
        }

        return self::defaultVariationItemIdPreferringPivotSku($giftProduct);
    }

    /**
     * First enabled variation whose own inventory at the given bodega can satisfy
     * the requested gift quantity (respecting safety/global-minimum floors).
     */
    public static function firstEnabledVariationWithEnoughStock(
        Product $giftProduct,
        string $bodega,
        int $requestedUnits
    ): ?int {
        if ($requestedUnits <= 0 || ! self::giftProductHasEnabledItems($giftProduct)) {
            return null;
        }

        $items = $giftProduct->items()
            ->wherePivot('enabled', true)
            ->get()
            ->sortBy('id')
            ->values();

        foreach ($items as $item) {
            $available = (int) $giftProduct->getInventoryForBodega($bodega, (int) $item->id);
            if (self::hasEnoughStockForRequestedUnits($available, $giftProduct, $requestedUnits)) {
                return (int) $item->id;
            }
        }

        return null;
    }

    /**
     * @return int|null VariationItem id
     */
    public static function defaultVariationItemIdPreferringPivotSku(Product $giftProduct): ?int
    {
        $items = $giftProduct->items()
            ->wherePivot('enabled', true)
            ->get()
            ->sortBy('id')
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        foreach ($items as $v) {
            if (! empty(trim((string) ($v->pivot->sku ?? '')))) {
                return (int) $v->id;
            }
        }

        $first = $items->first();

        return $first ? (int) $first->id : null;
    }
}
