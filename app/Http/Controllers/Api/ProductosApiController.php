<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductosApiController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with([
            'brand',
            'categories',
            'labels',
            'tax',
            'variation',
            'images',
            'inventories'
        ])->where('active', true);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->get('category_id'));
            });
        }

        if ($request->has('brand_id')) {
            $query->where('brand_id', $request->get('brand_id'));
        }

        if ($request->has('sku')) {
            $query->where('sku', $request->get('sku'));
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->get('min_price'));
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->get('max_price'));
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');

        if (in_array($sortBy, ['name', 'price', 'created_at', 'sales_count'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100); // Max 100 items per page
        $products = $query->paginate($perPage);

        // Transform data to include computed properties
        $products->transform(function ($product) use ($request) {
            $bodegaCode = $request->get('bodega_code');

            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'short_description' => $product->short_description,
                'sku' => $product->sku,
                'slug' => $product->slug,
                'price' => $product->price,
                'final_price' => $product->finalPrice,
                'discount' => $product->discount,
                'delivery_days' => $product->delivery_days,
                'quantity_min' => $product->quantity_min,
                'quantity_max' => $product->quantity_max,
                'step' => $product->step,
                'package_quantity' => $product->package_quantity,
                'safety_stock' => $product->safety_stock,
                'sales_count' => $product->sales_count,
                'inventory' => $bodegaCode ? $product->getSharedInventoryForBodega($bodegaCode) : null,
                'brand' => $product->brand,
                'categories' => $product->categories,
                'labels' => $product->labels,
                'tax' => $product->tax,
                'variation' => $product->variation,
                'images' => $product->images,
                'image' => $product->image,
                'created_at' => $product->created_at,
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
     * Display the specified product.
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $product->load([
            'brand',
            'categories',
            'labels',
            'tax',
            'variation',
            'images',
            'inventories',
            'combinations',
            'items',
            'related'
        ]);

        $bodegaCode = $request->get('bodega_code');

        return response()->json([
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'short_description' => $product->short_description,
                'technical_specifications' => $product->technical_specifications,
                'warranty' => $product->warranty,
                'other_information' => $product->other_information,
                'sku' => $product->sku,
                'slug' => $product->slug,
                'price' => $product->price,
                'final_price' => $product->finalPrice,
                'discount' => $product->discount,
                'delivery_days' => $product->delivery_days,
                'quantity_min' => $product->quantity_min,
                'quantity_max' => $product->quantity_max,
                'step' => $product->step,
                'package_quantity' => $product->package_quantity,
                'safety_stock' => $product->safety_stock,
                'sales_count' => $product->sales_count,
                'inventory' => $bodegaCode ? $product->getSharedInventoryForBodega($bodegaCode) : null,
                'brand' => $product->brand,
                'categories' => $product->categories,
                'labels' => $product->labels,
                'tax' => $product->tax,
                'variation' => $product->variation,
                'images' => $product->images,
                'combinations' => $product->combinations,
                'items' => $product->items,
                'related' => $product->related,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ]
        ]);
    }
}
