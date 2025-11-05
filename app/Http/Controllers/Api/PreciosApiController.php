<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiPaginationTrait;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PreciosApiController extends Controller
{
    use ApiPaginationTrait;

    /**
     * Display product prices.
     * 
     * Query Parameters:
     * - product_ids: Comma-separated product IDs
     * - skus: Comma-separated SKUs
     * - category_id: Filter by category ID
     * - brand_id: Filter by brand ID
     * - min_price: Minimum price filter
     * - max_price: Maximum price filter
     * - sort_by/order_by: Sort field (price, name, sku, updated_at)
     * - sort_direction/order: Sort direction (asc, desc)
     * - per_page: Items per page (default: 50, max: 200)
     * - limit: Maximum number of items to return (for non-paginated)
     * - offset: Number of items to skip (for non-paginated)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::where('active', true);

        // Apply filters
        if ($request->has('product_ids')) {
            $productIds = explode(',', $request->get('product_ids'));
            $query->whereIn('id', $productIds);
        }

        if ($request->has('skus')) {
            $skus = explode(',', $request->get('skus'));
            $query->whereIn('sku', $skus);
        }

        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->get('category_id'));
            });
        }

        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->get('brand_id'));
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->get('min_price'));
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->get('max_price'));
        }

        // Apply sorting and pagination/limit-offset
        $result = $this->applyPaginationAndSorting(
            $query,
            ['id', 'sku', 'name', 'price', 'discount', 'updated_at'], // Sortable fields
            'sku', // Default sort field
            'asc', // Default direction
            50, // Default per page
            200 // Max per page
        );

        // Transform to price-focused format
        $transformer = function ($product) {
            return [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'base_price' => $product->price,
                'discount' => $product->discount,
                'final_price' => $product->finalPrice,
                'tax_rate' => $product->tax ? $product->tax->tax : 0,
                'price_with_tax' => $product->finalPrice['price'] * (1 + ($product->tax ? $product->tax->tax / 100 : 0)),
                'package_quantity' => $product->package_quantity,
                'calculate_package_price' => $product->calculate_package_price,
                'updated_at' => $product->updated_at,
            ];
        };

        return $this->jsonResponse($result, $transformer);
    }

    /**
     * Display prices for a specific product.
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $product->load(['tax', 'items']);

        $data = [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'base_price' => $product->price,
            'discount' => $product->discount,
            'final_price' => $product->finalPrice,
            'tax_rate' => $product->tax ? $product->tax->tax : 0,
            'price_with_tax' => $product->finalPrice['price'] * (1 + ($product->tax ? $product->tax->tax / 100 : 0)),
            'package_quantity' => $product->package_quantity,
            'calculate_package_price' => $product->calculate_package_price,
            'updated_at' => $product->updated_at,
        ];

        // Include variation item prices if available
        if ($product->items->isNotEmpty()) {
            $data['variations'] = $product->items->map(function ($item) {
                return [
                    'variation_item_id' => $item->id,
                    'variation_name' => $item->name,
                    'variation_price' => $item->pivot->price,
                    'variation_sku' => $item->pivot->sku,
                    'enabled' => $item->pivot->enabled,
                ];
            });
        }

        return response()->json(['data' => $data]);
    }
}
