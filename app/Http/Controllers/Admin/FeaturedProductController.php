<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeaturedProduct;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FeaturedProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $featuredProducts = FeaturedProduct::with(['product' => function ($query) {
            $query->with(['brand', 'categories', 'images']);
        }])
            ->orderBy('position')
            ->get();

        // Get or create the setting for most sold products toggle using firstOrCreate
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

        // Get or create the setting for section title using firstOrCreate
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

        // Convert string to boolean properly
        $useMostSold = $useMostSoldSetting->value === '1' || $useMostSoldSetting->value === 1 || $useMostSoldSetting->value === true;
        $sectionTitle = $sectionTitleSetting->value;

        Log::info('Index method - setting value', [
            'raw_value' => $useMostSoldSetting->value,
            'type' => gettype($useMostSoldSetting->value),
            'converted_boolean' => $useMostSold,
            'section_title' => $sectionTitle
        ]);

        $context = compact('featuredProducts', 'useMostSold', 'sectionTitle');
        return view('featured-products.index', $context);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id|unique:featured_products,product_id'
        ]);

        // Get the highest position
        $maxPosition = FeaturedProduct::max('position') ?? 0;

        FeaturedProduct::create([
            'product_id' => $request->product_id,
            'position' => $maxPosition + 1
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FeaturedProduct $featuredProduct)
    {
        $featuredProduct->delete();

        // Reorder positions
        FeaturedProduct::where('position', '>', $featuredProduct->position)
            ->decrement('position');

        return back()->with('success', 'Producto eliminado de destacados');
    }

    /**
     * Search products for adding to featured
     */
    public function search(Request $request)
    {
        $query = $request->get('q');

        // Get IDs of already featured products
        $featuredIds = FeaturedProduct::pluck('product_id');

        $products = Product::where('active', 1)
            ->whereNotIn('id', $featuredIds)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%");
            })
            ->with(['brand', 'images'])
            ->limit(10)
            ->get();

        return response()->json($products);
    }

    /**
     * Toggle the most sold products setting
     */
    public function toggleMostSold(Request $request)
    {
        // Debug the incoming request
        Log::info('Toggle request received', [
            'use_most_sold' => $request->use_most_sold,
            'type' => gettype($request->use_most_sold),
            'all_data' => $request->all()
        ]);

        // Convert to proper boolean/string value
        $value = $request->use_most_sold === true || $request->use_most_sold === 'true' || $request->use_most_sold === 1 || $request->use_most_sold === '1' ? '1' : '0';

        Log::info('Setting value to: ' . $value);

        $setting = Setting::firstOrCreate(
            ['key' => 'use_most_sold_products'],
            [
                'name' => 'Usar productos más vendidos',
                'value' => $value,
                'show' => false
            ]
        );

        // Update the value and ensure other fields are set
        $setting->update([
            'name' => 'Usar productos más vendidos',
            'value' => $value,
            'show' => false
        ]);

        Log::info('Setting saved', ['setting' => $setting->toArray()]);

        return response()->json(['success' => true, 'value' => $value]);
    }

    /**
     * Update the section title
     */
    public function updateTitle(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255'
        ]);

        $setting = Setting::firstOrCreate(
            ['key' => 'featured_products_section_title'],
            [
                'name' => 'Título de la sección de productos destacados',
                'value' => $request->title,
                'show' => false
            ]
        );

        // Update the value and ensure other fields are set
        $setting->update([
            'name' => 'Título de la sección de productos destacados',
            'value' => $request->title,
            'show' => false
        ]);

        return response()->json(['success' => true, 'title' => $setting->value]);
    }
}
