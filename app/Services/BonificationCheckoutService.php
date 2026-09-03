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
     * Spanish message when Entrega Especial is blocked by cart bonifications.
     */
    public static function expressBlockedByBonificationsMessage(): string
    {
        return 'Tu carrito tiene productos no aptos para Entrega Especial por bonificaciones. Usa Entrega Standard o modifica el carrito.';
    }

    /**
     * Whether the cart currently qualifies for at least one bonification gift.
     * Same buy/get/max rules as checkout and the cart preview.
     *
     * @param  iterable<int, array<string, mixed>>  $cart
     */
    public static function cartHasQualifyingBonifications(iterable $cart): bool
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

            $packageQuantity = max(1, (int) ($tempProduct->package_quantity ?? 1));
            $quantities[$productId] = ($quantities[$productId] ?? 0)
                + ((int) ($row['quantity'] ?? 0)) * $packageQuantity;
        }

        foreach ($quantities as $productId => $aggregatedIndividualItems) {
            $product = Product::with('bonifications')->find($productId);
            if (! $product || $product->bonifications->isEmpty()) {
                continue;
            }

            foreach ($product->bonifications as $bonification) {
                $buy = (int) $bonification->buy;
                $get = (int) $bonification->get;
                if ($buy <= 0 || $get <= 0) {
                    continue;
                }

                $giftQuantity = (int) floor(($aggregatedIndividualItems / $buy) * $get);
                $max = (int) ($bonification->max ?? 0);
                if ($giftQuantity > $max) {
                    $giftQuantity = $max;
                }

                if ($giftQuantity > 0) {
                    return true;
                }
            }
        }

        return false;
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
