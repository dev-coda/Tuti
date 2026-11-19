<?php

namespace App\Services\Inventory;

use App\Models\InventorySyncLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Setting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class InventoryIntelligence
{
    public const DEFAULT_WINDOW_DAYS = 30;

    public const DEFAULT_COVER_DAYS = 21;

    /** @var array<int, int> */
    private const SOLD_STATUSES = [
        Order::STATUS_PROCESSED,
        Order::STATUS_SHIPPED,
        Order::STATUS_DELIVERED,
    ];

    private ?int $globalFloor = null;

    public function windowDays(mixed $days): int
    {
        $days = (int) $days;

        if ($days <= 0) {
            $days = self::DEFAULT_WINDOW_DAYS;
        }

        return min(90, max(7, $days));
    }

    public function coverDays(mixed $days): int
    {
        $days = (int) $days;

        if ($days <= 0) {
            $days = self::DEFAULT_COVER_DAYS;
        }

        return min(60, max(7, $days));
    }

    public function globalFloor(): int
    {
        return $this->globalFloor ??= max(0, (int) (Setting::getByKey('global_minimum_inventory') ?? 5));
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(int $windowDays, int $coverDays, ?string $bodega = null): array
    {
        $floor = $this->floorSql();
        $units = 'COALESCE(sales.units, 0)';
        $available = 'COALESCE(stock.available, 0)';

        $stats = $this->productQuery($windowDays, $bodega)
            ->select(DB::raw('COUNT(*) as product_count'))
            ->selectRaw("COALESCE(SUM({$available}), 0) as units_on_hand")
            ->selectRaw("SUM(CASE WHEN {$available} <= 0 THEN 1 ELSE 0 END) as stockouts")
            ->selectRaw("SUM(CASE WHEN {$available} > 0 AND {$available} <= {$floor} THEN 1 ELSE 0 END) as below_floor")
            ->selectRaw("SUM(CASE WHEN {$available} > {$floor} AND {$units} = 0 THEN 1 ELSE 0 END) as dead")
            ->selectRaw("SUM(CASE WHEN {$available} > {$floor} AND {$units} > 0 AND {$available} * {$windowDays} < {$coverDays} * {$units} THEN 1 ELSE 0 END) as low_cover")
            ->first();

        $lineStats = $this->lineQuery($bodega)
            ->select(DB::raw('COUNT(*) as line_count'))
            ->selectRaw('COALESCE(SUM(product_inventories.available), 0) as units_on_hand')
            ->selectRaw("SUM(CASE WHEN product_inventories.available <= 0 THEN 1 ELSE 0 END) as stockouts")
            ->selectRaw("SUM(CASE WHEN product_inventories.available > 0 AND product_inventories.available <= {$floor} THEN 1 ELSE 0 END) as below_floor")
            ->first();

        $lastSync = InventorySyncLog::query()->latest('id')->first();
        $enabled = Setting::getByKey('inventory_enabled');

        return [
            'window_days' => $windowDays,
            'cover_days' => $coverDays,
            'global_floor' => $this->globalFloor(),
            'inventory_enabled' => $enabled === '1' || $enabled === 1 || $enabled === true,
            'products' => (int) ($stats->product_count ?? 0),
            'units_on_hand' => (int) ($stats->units_on_hand ?? 0),
            'stockouts' => (int) ($stats->stockouts ?? 0),
            'below_floor' => (int) ($stats->below_floor ?? 0),
            'dead' => (int) ($stats->dead ?? 0),
            'low_cover' => (int) ($stats->low_cover ?? 0),
            'lines' => (int) ($lineStats->line_count ?? 0),
            'line_units' => (int) ($lineStats->units_on_hand ?? 0),
            'line_stockouts' => (int) ($lineStats->stockouts ?? 0),
            'line_below_floor' => (int) ($lineStats->below_floor ?? 0),
            'last_sync' => $lastSync ? [
                'bodega' => $lastSync->bodega_code,
                'status' => $lastSync->status,
                'at' => $lastSync->created_at?->timezone(InventorySyncLog::reportTimezone())->format('Y-m-d H:i'),
            ] : null,
        ];
    }

    /**
     * @param  array{q?: string, bodega?: string, status?: string}  $filters
     */
    public function stock(array $filters, int $perPage = 30): LengthAwarePaginator
    {
        $query = $this->lines($filters['bodega'] ?? null);
        $this->searchLines($query, (string) ($filters['q'] ?? ''));
        $this->applyLineStatus($query, (string) ($filters['status'] ?? 'all'));

        return $query
            ->orderBy('products.name')
            ->orderBy('product_inventories.bodega_code')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => $this->presentLine($row));
    }

    /**
     * @return array<int, string>
     */
    public function warehouseCodes(): array
    {
        return ProductInventory::query()
            ->select('bodega_code')
            ->distinct()
            ->orderBy('bodega_code')
            ->pluck('bodega_code')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function warehouses(): array
    {
        $floor = $this->floorSql();

        return $this->lines()
            ->select('product_inventories.bodega_code')
            ->selectRaw('COUNT(*) as line_count')
            ->selectRaw('COALESCE(SUM(product_inventories.available), 0) as units_on_hand')
            ->selectRaw('SUM(CASE WHEN product_inventories.available <= 0 THEN 1 ELSE 0 END) as stockouts')
            ->selectRaw("SUM(CASE WHEN product_inventories.available > 0 AND product_inventories.available <= {$floor} THEN 1 ELSE 0 END) as below_floor")
            ->groupBy('product_inventories.bodega_code')
            ->orderBy('product_inventories.bodega_code')
            ->get()
            ->map(fn ($row) => [
                'bodega' => $row->bodega_code,
                'lines' => (int) $row->line_count,
                'units' => (int) $row->units_on_hand,
                'stockouts' => (int) $row->stockouts,
                'below_floor' => (int) $row->below_floor,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function reorder(?string $bodega, int $windowDays, int $coverDays, int $limit = 25): array
    {
        $gap = "(({$coverDays} * COALESCE(sales.units, 0) * 1.0 / {$windowDays}) - COALESCE(stock.available, 0))";

        return $this->rankedProducts($bodega, $windowDays, $coverDays, $limit, function ($query) use ($windowDays, $coverDays, $gap) {
            $query->where(function ($inner) use ($windowDays, $coverDays) {
                $inner->whereRaw($this->productStatusSql('stockout'))
                    ->orWhereRaw($this->productStatusSql('below_floor'))
                    ->orWhereRaw($this->productStatusSql('low_cover', $windowDays, $coverDays));
            })->whereRaw('COALESCE(sales.units, 0) > 0')
                ->orderByRaw($gap.' DESC');
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function deadStock(?string $bodega, int $windowDays, int $coverDays, int $limit = 25): array
    {
        return $this->rankedProducts($bodega, $windowDays, $coverDays, $limit, function ($query) {
            $query->whereRaw($this->productStatusSql('dead'))
                ->orderByDesc('stock.available');
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function attention(?string $bodega, int $windowDays, int $coverDays, int $limit = 12): array
    {
        return $this->rankedProducts($bodega, $windowDays, $coverDays, $limit, function ($query) {
            $query->where(function ($inner) {
                $inner->whereRaw($this->productStatusSql('stockout'))
                    ->orWhereRaw($this->productStatusSql('below_floor'));
            })->orderBy('stock.available');
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function movers(?string $bodega, int $windowDays, int $coverDays, int $limit = 10): array
    {
        return $this->rankedProducts($bodega, $windowDays, $coverDays, $limit, function ($query) {
            $query->whereRaw('COALESCE(sales.units, 0) > 0')
                ->orderByDesc('sales.units');
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findProducts(string $term, int $windowDays, int $coverDays, int $limit = 5): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $like = '%'.$term.'%';
        $ids = Product::query()
            ->where(fn ($query) => $this->constrainManaged($query))
            ->where('products.active', true)
            ->where(function ($query) use ($like) {
                $query->where('products.name', 'like', $like)
                    ->orWhere('products.sku', 'like', $like)
                    ->orWhereHas('inventories', function ($inventory) use ($like) {
                        $inventory->where('source_sku', 'like', $like)
                            ->orWhere('bodega_code', 'like', $like);
                    });
            })
            ->orderBy('products.name')
            ->limit($limit)
            ->pluck('products.id');

        if ($ids->isEmpty()) {
            return [];
        }

        $rows = $this->products($windowDays, null)
            ->whereIn('products.id', $ids)
            ->get();

        $lines = $this->lines()
            ->whereIn('product_inventories.product_id', $ids)
            ->orderBy('product_inventories.bodega_code')
            ->get()
            ->groupBy(fn ($line) => (int) $line->product_id);

        return $rows->map(function ($row) use ($windowDays, $coverDays, $lines) {
            $product = $this->presentProduct($row, $windowDays, $coverDays);
            $product['warehouses'] = ($lines[(int) $row->id] ?? collect())
                ->map(fn ($line) => $this->presentLine($line))
                ->values()
                ->all();

            return $product;
        })->values()->all();
    }

    /**
     * @param  callable(Builder): void  $rank
     * @return array<int, array<string, mixed>>
     */
    private function rankedProducts(?string $bodega, int $windowDays, int $coverDays, int $limit, callable $rank): array
    {
        $query = $this->products($windowDays, $this->blankToNull($bodega));
        $rank($query);

        return $query
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->presentProduct($row, $windowDays, $coverDays))
            ->all();
    }

    private function products(int $windowDays, ?string $bodega): Builder
    {
        return $this->productQuery($windowDays, $bodega)->select([
            'products.id',
            'products.name',
            'products.sku',
            'products.safety_stock',
            'products.active',
            'stock.available',
            'stock.physical',
            'stock.reserved',
            'stock.bodega_count',
            'category_floors.category_safety',
            DB::raw('COALESCE(sales.units, 0) as units_sold'),
        ]);
    }

    private function productQuery(int $windowDays, ?string $bodega): Builder
    {
        $since = now()->subDays($windowDays);

        $sales = DB::table('order_products')
            ->join('orders', 'orders.id', '=', 'order_products.order_id')
            ->whereIn('orders.status_id', self::SOLD_STATUSES)
            ->where('orders.created_at', '>=', $since)
            ->where(function ($query) {
                $query->where('order_products.is_bonification', false)
                    ->orWhereNull('order_products.is_bonification');
            })
            ->groupBy('order_products.product_id')
            ->select([
                'order_products.product_id',
                DB::raw('SUM(order_products.quantity) as units'),
            ]);

        return Product::query()
            ->where(fn ($query) => $this->constrainManaged($query))
            ->where('products.active', true)
            ->joinSub($this->stockSubquery($bodega), 'stock', 'stock.product_id', '=', 'products.id')
            ->leftJoinSub($sales, 'sales', 'sales.product_id', '=', 'products.id')
            ->leftJoinSub($this->categoryFloorSubquery(), 'category_floors', 'category_floors.product_id', '=', 'products.id');
    }

    private function lines(?string $bodega = null): Builder
    {
        return $this->lineQuery($bodega)->select([
            'product_inventories.id',
            'product_inventories.product_id',
            'product_inventories.bodega_code',
            'product_inventories.available',
            'product_inventories.physical',
            'product_inventories.reserved',
            'product_inventories.source_sku',
            'products.name',
            'products.sku',
            'products.safety_stock',
            'products.active',
            'variation_items.name as variation_name',
            'category_floors.category_safety',
        ]);
    }

    private function lineQuery(?string $bodega = null): Builder
    {
        return ProductInventory::query()
            ->join('products', 'products.id', '=', 'product_inventories.product_id')
            ->leftJoin('variation_items', 'variation_items.id', '=', 'product_inventories.variation_item_id')
            ->leftJoinSub($this->categoryFloorSubquery(), 'category_floors', 'category_floors.product_id', '=', 'products.id')
            ->where(fn ($query) => $this->constrainManaged($query))
            ->when($this->blankToNull($bodega), fn ($query, $code) => $query->where('product_inventories.bodega_code', $code));
    }

    private function stockSubquery(?string $bodega): QueryBuilder
    {
        return DB::table('product_inventories')
            ->when($this->blankToNull($bodega), fn ($query, $code) => $query->where('bodega_code', $code))
            ->groupBy('product_id')
            ->select([
                'product_id',
                DB::raw('SUM(available) as available'),
                DB::raw('SUM(physical) as physical'),
                DB::raw('SUM(reserved) as reserved'),
                DB::raw('COUNT(DISTINCT bodega_code) as bodega_count'),
            ]);
    }

    private function categoryFloorSubquery(): QueryBuilder
    {
        return DB::table('category_product')
            ->join('categories', 'categories.id', '=', 'category_product.category_id')
            ->groupBy('category_product.product_id')
            ->select([
                'category_product.product_id',
                DB::raw('MAX(COALESCE(categories.safety_stock, 0)) as category_safety'),
            ]);
    }

    private function constrainManaged(Builder|QueryBuilder $query): void
    {
        $query->where(function ($inner) {
            $inner->whereNull('products.inventory_opt_out')
                ->orWhere('products.inventory_opt_out', 0);
        })->whereNotExists(function ($inner) {
            $inner->select(DB::raw('1'))
                ->from('category_product')
                ->join('categories', 'categories.id', '=', 'category_product.category_id')
                ->whereColumn('category_product.product_id', 'products.id')
                ->where(function ($category) {
                    $category->where('categories.inventory_opt_out', 1)
                        ->orWhereRaw("UPPER(TRIM(categories.name)) = 'OFERTAS'");
                });
        });
    }

    private function searchLines(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';
        $query->where(function ($inner) use ($like) {
            $inner->where('products.name', 'like', $like)
                ->orWhere('products.sku', 'like', $like)
                ->orWhere('product_inventories.source_sku', 'like', $like)
                ->orWhere('variation_items.name', 'like', $like)
                ->orWhere('product_inventories.bodega_code', 'like', $like);
        });
    }

    private function applyLineStatus(Builder $query, string $status): void
    {
        $floor = $this->floorSql();
        $available = 'product_inventories.available';

        $sql = match ($status) {
            'stockout' => "{$available} <= 0",
            'below_floor' => "{$available} > 0 AND {$available} <= {$floor}",
            'in_stock' => "{$available} > {$floor}",
            default => null,
        };

        if ($sql !== null) {
            $query->whereRaw($sql);
        }
    }

    private function productStatusSql(string $status, int $windowDays = self::DEFAULT_WINDOW_DAYS, int $coverDays = self::DEFAULT_COVER_DAYS): string
    {
        $floor = $this->floorSql();
        $available = 'COALESCE(stock.available, 0)';
        $units = 'COALESCE(sales.units, 0)';

        return match ($status) {
            'stockout' => "{$available} <= 0",
            'below_floor' => "{$available} > 0 AND {$available} <= {$floor}",
            'dead' => "{$available} > {$floor} AND {$units} = 0",
            'low_cover' => "{$available} > {$floor} AND {$units} > 0 AND {$available} * {$windowDays} < {$coverDays} * {$units}",
            default => '1 = 1',
        };
    }

    private function floorSql(): string
    {
        $global = $this->globalFloor();

        return "CASE WHEN COALESCE(products.safety_stock, 0) > 0 THEN products.safety_stock WHEN COALESCE(category_floors.category_safety, 0) > 0 THEN category_floors.category_safety ELSE {$global} END";
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLine(object $row): array
    {
        $available = (int) $row->available;
        $floor = $this->floorFor($row);
        $status = $available <= 0 ? 'stockout' : ($available <= $floor ? 'below_floor' : 'in_stock');
        [$label, $class] = $this->statusMeta($status);

        return [
            'product_id' => (int) $row->product_id,
            'name' => $row->name,
            'sku' => $row->source_sku ?: $row->sku,
            'variation' => $row->variation_name,
            'bodega' => $row->bodega_code,
            'available' => $available,
            'physical' => (int) $row->physical,
            'reserved' => (int) $row->reserved,
            'floor' => $floor,
            'active' => (bool) $row->active,
            'status' => $status,
            'status_label' => $label,
            'status_class' => $class,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentProduct(object $row, int $windowDays, int $coverDays): array
    {
        $available = (int) $row->available;
        $units = (int) $row->units_sold;
        $floor = $this->floorFor($row);
        $daily = $windowDays > 0 ? $units / $windowDays : 0.0;
        $cover = $units > 0 && $daily > 0 ? round($available / $daily, 1) : null;
        $goal = $units > 0 ? max((int) ceil($daily * $coverDays), $floor) : 0;
        $suggest = $goal > $available ? $goal - $available : 0;
        $status = $this->classifyProduct($available, $units, $floor, $windowDays, $coverDays);
        [$label, $class] = $this->statusMeta($status);

        return [
            'product_id' => (int) $row->id,
            'name' => $row->name,
            'sku' => $row->sku,
            'available' => $available,
            'physical' => (int) $row->physical,
            'reserved' => (int) $row->reserved,
            'bodega_count' => (int) $row->bodega_count,
            'floor' => $floor,
            'units_sold' => $units,
            'daily' => round($daily, 2),
            'cover_days' => $cover,
            'suggest' => $suggest,
            'status' => $status,
            'status_label' => $label,
            'status_class' => $class,
        ];
    }

    private function classifyProduct(int $available, int $units, int $floor, int $windowDays, int $coverDays): string
    {
        if ($available <= 0) {
            return 'stockout';
        }

        if ($available <= $floor) {
            return 'below_floor';
        }

        if ($units <= 0) {
            return 'dead';
        }

        if ($available * $windowDays < $coverDays * $units) {
            return 'low_cover';
        }

        return 'healthy';
    }

    private function floorFor(object $row): int
    {
        $product = (int) ($row->safety_stock ?? 0);
        if ($product > 0) {
            return $product;
        }

        $category = (int) ($row->category_safety ?? 0);
        if ($category > 0) {
            return $category;
        }

        return $this->globalFloor();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function statusMeta(string $status): array
    {
        return match ($status) {
            'stockout' => ['Agotado', 'bg-red-100 text-red-800'],
            'below_floor' => ['Bajo piso', 'bg-orange-100 text-orange-800'],
            'low_cover' => ['Cobertura corta', 'bg-amber-100 text-amber-900'],
            'dead' => ['Sin rotación', 'bg-slate-100 text-slate-700'],
            'healthy' => ['Cubre la demanda', 'bg-green-100 text-green-800'],
            default => ['En piso', 'bg-green-100 text-green-800'],
        };
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
