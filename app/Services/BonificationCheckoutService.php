<?php

namespace App\Services;

use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Setting;

class BonificationCheckoutService
{
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
        if (! $variationItemId) {
            return $product;
        }

        $variationSku = self::selectedVariationSku($product, $variationItemId);
        if ($variationSku === null) {
            return $product;
        }

        return Product::query()
            ->where('sku', $variationSku)
            ->first() ?? $product;
    }

    public static function selectedVariationSku(Product $product, ?int $variationItemId): ?string
    {
        if (! $variationItemId) {
            return null;
        }

        $variation = $product->items()
            ->where('variation_items.id', $variationItemId)
            ->wherePivot('enabled', true)
            ->first();

        $sku = trim((string) ($variation?->pivot?->sku ?? ''));

        return $sku !== '' ? $sku : null;
    }

    /**
     * Variation to send on the gift (obsequio) line so SAP/XML gets a resolvable itemId.
     * When the parent has no base SKU, we must use an enabled line with a per-variation SKU.
     */
    public static function resolveGiftVariationItemId(Product $giftProduct, int $orderId): ?int
    {
        if (! self::giftProductHasEnabledItems($giftProduct)) {
            return null;
        }

        $parentSku = trim((string) ($giftProduct->sku ?? ''));

        $existing = OrderProduct::query()
            ->where('order_id', $orderId)
            ->where('product_id', $giftProduct->id)
            ->whereNotNull('variation_item_id')
            ->orderBy('id')
            ->first();
        if ($existing) {
            return $existing->variation_item_id;
        }

        if ($parentSku !== '') {
            return null;
        }

        return self::defaultVariationItemIdPreferringPivotSku($giftProduct);
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
