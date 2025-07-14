<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeaturedCategory;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FeaturedCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $featuredCategories = FeaturedCategory::with(['category'])
            ->orderBy('position')
            ->get();

        // Get or create the setting for most popular categories toggle using firstOrCreate
        $useMostPopularSetting = Setting::firstOrCreate(
            ['key' => 'use_most_popular_categories'],
            [
                'name' => 'Usar categorías más populares',
                'value' => '0',
                'show' => false
            ]
        );

        // Update the name and show fields if they're null
        if (is_null($useMostPopularSetting->name)) {
            $useMostPopularSetting->update([
                'name' => 'Usar categorías más populares',
                'show' => false
            ]);
        }

        // Get or create the setting for section title using firstOrCreate
        $sectionTitleSetting = Setting::firstOrCreate(
            ['key' => 'featured_categories_section_title'],
            [
                'name' => 'Título de la sección de categorías destacadas',
                'value' => 'Categorías',
                'show' => false
            ]
        );

        // Update the name and show fields if they're null
        if (is_null($sectionTitleSetting->name)) {
            $sectionTitleSetting->update([
                'name' => 'Título de la sección de categorías destacadas',
                'show' => false
            ]);
        }

        // Convert string to boolean properly
        $useMostPopular = $useMostPopularSetting->value === '1' || $useMostPopularSetting->value === 1 || $useMostPopularSetting->value === true;
        $sectionTitle = $sectionTitleSetting->value;

        Log::info('Featured Categories Index method - setting value', [
            'raw_value' => $useMostPopularSetting->value,
            'type' => gettype($useMostPopularSetting->value),
            'converted_boolean' => $useMostPopular,
            'section_title' => $sectionTitle
        ]);

        $context = compact('featuredCategories', 'useMostPopular', 'sectionTitle');
        return view('featured-categories.index', $context);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id|unique:featured_categories,category_id'
        ]);

        // Get the highest position
        $maxPosition = FeaturedCategory::max('position') ?? 0;

        FeaturedCategory::create([
            'category_id' => $request->category_id,
            'position' => $maxPosition + 1
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FeaturedCategory $featuredCategory)
    {
        $featuredCategory->delete();

        // Reorder positions
        FeaturedCategory::where('position', '>', $featuredCategory->position)
            ->decrement('position');

        return back()->with('success', 'Categoría eliminada de destacadas');
    }

    /**
     * Search categories for adding to featured
     */
    public function search(Request $request)
    {
        $query = $request->get('q');

        // Get IDs of already featured categories
        $featuredIds = FeaturedCategory::pluck('category_id');

        $categories = Category::where('active', 1)
            ->whereNotIn('id', $featuredIds)
            ->where('name', 'like', "%{$query}%")
            ->limit(10)
            ->get();

        return response()->json($categories);
    }

    /**
     * Toggle the most popular categories setting
     */
    public function toggleMostPopular(Request $request)
    {
        // Debug the incoming request
        Log::info('Categories Toggle request received', [
            'use_most_popular' => $request->use_most_popular,
            'type' => gettype($request->use_most_popular),
            'all_data' => $request->all()
        ]);

        // Convert to proper boolean/string value
        $value = $request->use_most_popular === true || $request->use_most_popular === 'true' || $request->use_most_popular === 1 || $request->use_most_popular === '1' ? '1' : '0';

        Log::info('Setting categories value to: ' . $value);

        $setting = Setting::firstOrCreate(
            ['key' => 'use_most_popular_categories'],
            [
                'name' => 'Usar categorías más populares',
                'value' => $value,
                'show' => false
            ]
        );

        // Update the value and ensure other fields are set
        $setting->update([
            'name' => 'Usar categorías más populares',
            'value' => $value,
            'show' => false
        ]);

        Log::info('Categories Setting saved', ['setting' => $setting->toArray()]);

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
            ['key' => 'featured_categories_section_title'],
            [
                'name' => 'Título de la sección de categorías destacadas',
                'value' => $request->title,
                'show' => false
            ]
        );

        // Update the value and ensure other fields are set
        $setting->update([
            'name' => 'Título de la sección de categorías destacadas',
            'value' => $request->title,
            'show' => false
        ]);

        return response()->json(['success' => true, 'title' => $setting->value]);
    }
}
