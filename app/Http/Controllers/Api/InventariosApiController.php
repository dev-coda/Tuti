<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductInventory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventariosApiController extends Controller
{
    /**
     * Display inventory for all products.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductInventory::with(['product' => function ($q) {
            $q->select('id', 'name', 'sku', 'active');
        }]);

        // Apply filters
        if ($request->has('bodega_code')) {
            $query->where('bodega_code', $request->get('bodega_code'));
        }

        if ($request->has('product_id')) {
            $query->where('product_id', $request->get('product_id'));
        }

        if ($request->has('product_ids')) {
            $productIds = explode(',', $request->get('product_ids'));
            $query->whereIn('product_id', $productIds);
        }

        if ($request->has('sku')) {
            $query->whereHas('product', function ($q) use ($request) {
                $q->where('sku', $request->get('sku'));
            });
        }

        if ($request->has('skus')) {
            $skus = explode(',', $request->get('skus'));
            $query->whereHas('product', function ($q) use ($skus) {
                $q->whereIn('sku', $skus);
            });
        }

        if ($request->has('min_available')) {
            $query->where('available', '>=', $request->get('min_available'));
        }

        if ($request->has('max_available')) {
            $query->where('available', '<=', $request->get('max_available'));
        }

        // Only show inventory for active products
        if ($request->get('active_products_only', true)) {
            $query->whereHas('product', function ($q) {
                $q->where('active', true);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'product_id');
        $sortDirection = $request->get('sort_direction', 'asc');

        if (in_array($sortBy, ['product_id', 'bodega_code', 'available', 'physical', 'reserved'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Pagination
        $perPage = min($request->get('per_page', 50), 200); // Max 200 items per page
        $inventory = $query->paginate($perPage);

        // Transform data
        $inventory->transform(function ($item) {
            return [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? null,
                'product_sku' => $item->product->sku ?? null,
                'bodega_code' => $item->bodega_code,
                'available' => $item->available,
                'physical' => $item->physical,
                'reserved' => $item->reserved,
                'updated_at' => $item->updated_at,
            ];
        });

        return response()->json([
            'data' => $inventory->items(),
            'pagination' => [
                'current_page' => $inventory->currentPage(),
                'last_page' => $inventory->lastPage(),
                'per_page' => $inventory->perPage(),
                'total' => $inventory->total(),
            ]
        ]);
    }

    /**
     * Display inventory for a specific product.
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $query = $product->inventories();

        // Filter by bodega if specified
        if ($request->has('bodega_code')) {
            $query->where('bodega_code', $request->get('bodega_code'));
        }

        $inventories = $query->get();

        // If no specific bodega requested, also include shared inventory calculation
        $inventoryData = $inventories->map(function ($inventory) use ($product) {
            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'bodega_code' => $inventory->bodega_code,
                'available' => $inventory->available,
                'physical' => $inventory->physical,
                'reserved' => $inventory->reserved,
                'shared_inventory' => $product->getSharedInventoryForBodega($inventory->bodega_code),
                'updated_at' => $inventory->updated_at,
            ];
        });

        return response()->json([
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'safety_stock' => $product->safety_stock,
                    'inventory_opt_out' => $product->inventory_opt_out,
                ],
                'inventories' => $inventoryData,
                'total_available' => $inventories->sum('available'),
                'total_physical' => $inventories->sum('physical'),
                'total_reserved' => $inventories->sum('reserved'),
            ]
        ]);
    }

    /**
     * Get inventory summary by bodega.
     */
    public function byBodega(Request $request, string $bodegaCode): JsonResponse
    {
        $query = ProductInventory::with(['product' => function ($q) {
            $q->select('id', 'name', 'sku', 'active', 'safety_stock');
        }])->where('bodega_code', $bodegaCode);

        // Only show inventory for active products
        if ($request->get('active_products_only', true)) {
            $query->whereHas('product', function ($q) {
                $q->where('active', true);
            });
        }

        // Apply additional filters
        if ($request->has('low_stock_only')) {
            $query->whereHas('product', function ($q) {
                $q->whereColumn('available', '<=', 'safety_stock');
            });
        }

        if ($request->has('out_of_stock_only')) {
            $query->where('available', '<=', 0);
        }

        $perPage = min($request->get('per_page', 50), 200);
        $inventory = $query->paginate($perPage);

        // Calculate summary statistics
        $totalProducts = $query->count();
        $outOfStock = ProductInventory::where('bodega_code', $bodegaCode)
            ->where('available', '<=', 0)
            ->whereHas('product', function ($q) {
                $q->where('active', true);
            })->count();

        $lowStock = ProductInventory::where('bodega_code', $bodegaCode)
            ->whereHas('product', function ($q) {
                $q->where('active', true)
                    ->whereColumn('available', '<=', 'safety_stock')
                    ->where('safety_stock', '>', 0);
            })->count();

        $inventory->transform(function ($item) {
            $product = $item->product;
            return [
                'product_id' => $item->product_id,
                'product_name' => $product->name ?? null,
                'product_sku' => $product->sku ?? null,
                'bodega_code' => $item->bodega_code,
                'available' => $item->available,
                'physical' => $item->physical,
                'reserved' => $item->reserved,
                'safety_stock' => $product->safety_stock ?? 0,
                'is_low_stock' => $product && $item->available <= $product->safety_stock,
                'is_out_of_stock' => $item->available <= 0,
                'updated_at' => $item->updated_at,
            ];
        });

        return response()->json([
            'bodega_code' => $bodegaCode,
            'summary' => [
                'total_products' => $totalProducts,
                'out_of_stock' => $outOfStock,
                'low_stock' => $lowStock,
            ],
            'data' => $inventory->items(),
            'pagination' => [
                'current_page' => $inventory->currentPage(),
                'last_page' => $inventory->lastPage(),
                'per_page' => $inventory->perPage(),
                'total' => $inventory->total(),
            ]
        ]);
    }
}
