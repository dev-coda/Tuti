<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProductsApiController extends Controller
{
    public function latest()
    {
        // Check if we should use most sold products
        $useMostSoldSetting = Setting::firstOrCreate(
            ['key' => 'use_most_sold_products'],
            [
                'name' => 'Usar productos más vendidos',
                'value' => '0',
                'show' => false
            ]
        );

        // Update the name and show fields if they're null
        if (is_null($useMostSoldSetting->name)) {
            $useMostSoldSetting->update([
                'name' => 'Usar productos más vendidos',
                'show' => false
            ]);
        }

        // Convert string to boolean properly
        $useMostSold = $useMostSoldSetting->value === '1' || $useMostSoldSetting->value === 1 || $useMostSoldSetting->value === true;

        Log::info('API latest method - setting value', [
            'raw_value' => $useMostSoldSetting->value,
            'type' => gettype($useMostSoldSetting->value),
            'converted_boolean' => $useMostSold
        ]);

        if ($useMostSold) {
            // Return most sold products
            return $this->mostSold();
        }

        // Debug total products count
        $totalProducts = Product::count();
        Log::info('Total products in database: ' . $totalProducts);

        // Get featured products instead of latest
        $featuredProductIds = DB::table('featured_products')
            ->orderBy('position')
            ->pluck('product_id');

        if ($featuredProductIds->isEmpty()) {
            // If no featured products, fallback to latest
            $query = Product::with(['brand', 'categories', 'images'])
                ->latest()->where('active', 1)
                ->take(12);
        } else {
            // Get featured products maintaining the order
            $query = Product::with(['brand', 'categories', 'images'])
                ->whereIn('id', $featuredProductIds)
                ->where('active', 1);
        }

        // Debug the SQL query
        Log::info('SQL Query: ' . $query->toSql());

        $products = $query->get();

        // If we have featured products, sort them by position
        if (!$featuredProductIds->isEmpty()) {
            $products = $products->sortBy(function ($product) use ($featuredProductIds) {
                return $featuredProductIds->search($product->id);
            })->values();
        }

        // Debug products count
        Log::info('Products fetched: ' . $products->count());

        if ($products->isEmpty()) {
            Log::info('No products found');
            return response()->json([
                'message' => 'No products found',
                'products' => []
            ]);
        }

        $mappedProducts = $products->map(function ($product) {
            $finalPrice = $product->finalPrice;
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => $finalPrice['price'],
                'image' => $product->images->first()
                    ? asset('storage/' . $product->images->first()->path)
                    : null,
                'url' => route('product', $product->slug),
                'brand' => $product->brand ? [
                    'name' => $product->brand->name
                ] : null,
                'category' => $product->category ? [
                    'name' => $product->category->name
                ] : null,
                'final_price' => [
                    'price' => $finalPrice['price'],
                    'old' => $finalPrice['old'],
                    'has_discount' => $finalPrice['discount'] > 0,
                    'discount' => $finalPrice['discount'],
                    'perItemPrice' => $finalPrice['perItemPrice'] ?? null
                ]
            ];
        });

        $response = [
            'count' => $products->count(),
            'products' => $mappedProducts
        ];

        Log::info('API Response:', $response);

        return response()->json($response);
    }

    public function mostSold()
    {
        // Debug log
        Log::info('Fetching most sold products');

        // Get product IDs sorted by total quantity sold
        $mostSoldProductIds = DB::table('order_products')
            ->select('product_id', DB::raw('SUM(quantity) as total_sold'))
            ->groupBy('product_id')
            ->orderBy('total_sold', 'desc')
            ->take(12)
            ->pluck('product_id');

        // If no sales yet, fallback to latest products
        if ($mostSoldProductIds->isEmpty()) {
            Log::info('No sales found, returning latest products instead');
            // Fallback to latest without the featured products logic to avoid infinite loop
            $query = Product::with(['brand', 'categories', 'images'])
                ->latest()->where('active', 1)
                ->take(12);
        } else {
            // Get products maintaining the order of most sold
            $products = Product::with(['brand', 'categories', 'images'])
                ->whereIn('id', $mostSoldProductIds)
                ->where('active', 1)
                ->get()
                ->sortBy(function ($product) use ($mostSoldProductIds) {
                    return array_search($product->id, $mostSoldProductIds->toArray());
                })
                ->values();

            // Debug products count
            Log::info('Most sold products fetched: ' . $products->count());

            $mappedProducts = $products->map(function ($product) {
                $finalPrice = $product->finalPrice;
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $finalPrice['price'],
                    'image' => $product->images->first()
                        ? asset('storage/' . $product->images->first()->path)
                        : null,
                    'url' => route('product', $product->slug),
                    'brand' => $product->brand ? [
                        'name' => $product->brand->name
                    ] : null,
                    'category' => $product->category ? [
                        'name' => $product->category->name
                    ] : null,
                    'final_price' => [
                        'price' => $finalPrice['price'],
                        'old' => $finalPrice['old'],
                        'has_discount' => $finalPrice['discount'] > 0,
                        'discount' => $finalPrice['discount'],
                        'perItemPrice' => $finalPrice['perItemPrice'] ?? null
                    ]
                ];
            });

            $response = [
                'count' => $products->count(),
                'products' => $mappedProducts
            ];

            Log::info('Most sold API Response:', $response);

            return response()->json($response);
        }

        // Handle fallback case properly
        $products = $query->get();

        if ($products->isEmpty()) {
            return response()->json([
                'message' => 'No products found',
                'products' => []
            ]);
        }

        $mappedProducts = $products->map(function ($product) {
            $finalPrice = $product->finalPrice;
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => $finalPrice['price'],
                'image' => $product->images->first()
                    ? asset('storage/' . $product->images->first()->path)
                    : null,
                'url' => route('product', $product->slug),
                'brand' => $product->brand ? [
                    'name' => $product->brand->name
                ] : null,
                'category' => $product->category ? [
                    'name' => $product->category->name
                ] : null,
                'final_price' => [
                    'price' => $finalPrice['price'],
                    'old' => $finalPrice['old'],
                    'has_discount' => $finalPrice['discount'] > 0,
                    'discount' => $finalPrice['discount'],
                    'perItemPrice' => $finalPrice['perItemPrice'] ?? null
                ]
            ];
        });

        return response()->json([
            'count' => $products->count(),
            'products' => $mappedProducts
        ]);
    }

    public function getSectionTitle()
    {
        $sectionTitleSetting = Setting::firstOrCreate(
            ['key' => 'featured_products_section_title'],
            [
                'name' => 'Título de la sección de productos destacados',
                'value' => 'Productos Destacados',
                'show' => false
            ]
        );

        // Update the name and show fields if they're null
        if (is_null($sectionTitleSetting->name)) {
            $sectionTitleSetting->update([
                'name' => 'Título de la sección de productos destacados',
                'show' => false
            ]);
        }

        return response()->json([
            'title' => $sectionTitleSetting->value
        ]);
    }
}
