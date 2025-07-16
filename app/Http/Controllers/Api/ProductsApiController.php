<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductsApiController extends Controller
{
    public function latest()
    {
        // Debug total products count
        $totalProducts = Product::count();
        Log::info('Total products in database: ' . $totalProducts);

        $query = Product::with(['brand', 'categories', 'images'])
            ->latest()->where('active', 1)
            ->take(12);

        // Debug the SQL query
        Log::info('SQL Query: ' . $query->toSql());

        $products = $query->get();

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
