<?php

namespace App\Services;

use App\Models\InventorySyncLog;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\ProductInventory;
use App\Models\Setting;
use App\Models\ZoneWarehouse;
use App\Repositories\OrderRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Checkout used to subtract the cart package count from bodega stock.
 * Dynamics is reduced by the sales quantity (packages × package size when the
 * product is package-priced). This closes that gap for orders placed after
 * the last inventory sync, which is the stock still shown as start-of-day.
 */
class InventoryDaySalesAdjuster
{
    public const FLAG = 'inventory_sales_unit_backfill';

    public function applyUncountedPackageUnits(): int
    {
        if (Setting::getByKey(self::FLAG) === '1') {
            return 0;
        }

        $lastSyncByBodega = InventorySyncLog::query()
            ->where('status', 'success')
            ->whereNotNull('bodega_code')
            ->where('bodega_code', '!=', 'GENERAL')
            ->selectRaw('bodega_code, MAX(created_at) as synced_at')
            ->groupBy('bodega_code')
            ->pluck('synced_at', 'bodega_code');

        $fallback = Carbon::now(OrderRepository::businessTimezone())->startOfDay()->utc();
        $adjustedUnits = 0;

        OrderProduct::query()
            ->with(['product', 'order.zone'])
            ->where('created_at', '>=', now()->subDays(14))
            ->where('is_bonification', false)
            ->whereHas('order', function ($query) {
                $query->where('status_id', '!=', Order::STATUS_CANCELLED);
            })
            ->orderBy('id')
            ->chunkById(500, function ($lines) use ($lastSyncByBodega, $fallback, &$adjustedUnits) {
                foreach ($lines as $line) {
                    $adjustedUnits += $this->applyLine($line, $lastSyncByBodega, $fallback);
                }
            });

        Setting::updateOrCreate(
            ['key' => self::FLAG],
            ['name' => 'Inventory sales unit backfill', 'value' => '1', 'show' => false]
        );
        Cache::forget('setting_'.self::FLAG);

        Log::info('Inventory day-sales unit backfill finished', [
            'units_removed' => $adjustedUnits,
        ]);

        return $adjustedUnits;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, mixed>  $lastSyncByBodega
     */
    private function applyLine(OrderProduct $line, $lastSyncByBodega, Carbon $fallback): int
    {
        $product = $line->product;
        if (! $product) {
            return 0;
        }

        $salesUnits = $product->inventoryUnitsForQuantity(
            (int) $line->quantity,
            (int) ($line->package_quantity ?: $product->package_quantity ?: 1)
        );
        $gap = $salesUnits - (int) $line->quantity;
        if ($gap <= 0) {
            return 0;
        }

        $zoneCode = $line->order?->zone?->zone;
        $bodega = ZoneWarehouse::getBodegaForZone($zoneCode);
        if (! $bodega) {
            return 0;
        }

        $syncedAt = isset($lastSyncByBodega[$bodega])
            ? Carbon::parse($lastSyncByBodega[$bodega])
            : $fallback;

        if ($line->created_at === null || $line->created_at->lte($syncedAt)) {
            return 0;
        }

        $variationItemId = $line->variation_item_id ? (int) $line->variation_item_id : null;
        $inventory = $product->inventoryForBodega($bodega, $variationItemId);
        if (! $inventory) {
            return 0;
        }

        $next = max(0, (int) $inventory->available - $gap);
        if ($next === (int) $inventory->available) {
            return 0;
        }

        ProductInventory::query()->whereKey($inventory->id)->update([
            'available' => $next,
        ]);

        return $gap;
    }
}
