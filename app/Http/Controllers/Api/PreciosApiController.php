<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PreciosApiController extends Controller
{
    /**
     * Display product prices.
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

        // Pagination
        $perPage = min($request->get('per_page', 50), 200); // Max 200 items per page for prices
        $products = $query->paginate($perPage);

        // Transform to price-focused format
        $products->getCollection()->transform(function ($product) {
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
        });

        return response()->json([
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
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
