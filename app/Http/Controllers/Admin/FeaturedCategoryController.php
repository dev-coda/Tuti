<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeaturedCategory;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        // Prepare data for JavaScript
        $featuredCategoriesData = $featuredCategories->map(function ($featured) {
            return [
                'id' => $featured->id,
                'category' => [
                    'id' => $featured->category->id,
                    'name' => $featured->category->name,
                    'slug' => $featured->category->slug,
                    'image' => $featured->category->image, // Keep raw path for JavaScript null checking
                    'description' => $featured->category->description
                ],
                'custom_title' => $featured->custom_title,
                'custom_url' => $featured->custom_url,
                'custom_image' => $featured->custom_image
            ];
        });

        Log::info('Featured Categories Index method - setting value', [
            'raw_value' => $useMostPopularSetting->value,
            'type' => gettype($useMostPopularSetting->value),
            'converted_boolean' => $useMostPopular,
            'section_title' => $sectionTitle
        ]);

        $context = compact('featuredCategories', 'useMostPopular', 'sectionTitle', 'featuredCategoriesData');
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

        // Use ILIKE for case-insensitive search on PostgreSQL
        $categories = Category::whereNotIn('id', $featuredIds)
            ->where('name', 'ILIKE', "%{$query}%")
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'slug', 'image', 'parent_id']);

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

    /**
     * Update custom fields for featured category
     */
    public function updateCustomization(Request $request, FeaturedCategory $featuredCategory)
    {
        Log::info('Customization update started', [
            'featured_category_id' => $featuredCategory->id,
            'request_data' => $request->all(),
            'files' => $request->hasFile('custom_image') ? 'has_file' : 'no_file'
        ]);

        try {
            $request->validate([
                'custom_title' => 'nullable|string|max:255',
                'custom_url' => 'nullable|url|max:255',
                'custom_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            Log::info('Validation passed');

            $data = $request->only(['custom_title', 'custom_url']);

            // Handle custom image upload
            if ($request->hasFile('custom_image')) {
                Log::info('Processing image upload');

                // Delete old custom image if exists
                if ($featuredCategory->custom_image) {
                    Storage::disk('public')->delete($featuredCategory->custom_image);
                    Log::info('Deleted old image: ' . $featuredCategory->custom_image);
                }

                $image = $request->file('custom_image');
                $path = $image->store('featured-categories', 'public');
                $data['custom_image'] = $path;

                Log::info('New image stored at: ' . $path);
            }

            Log::info('Updating featured category with data:', $data);

            $featuredCategory->update($data);

            Log::info('Update successful');

            return response()->json([
                'success' => true,
                'message' => 'Personalización actualizada correctamente',
                'data' => [
                    'custom_title' => $featuredCategory->custom_title,
                    'custom_url' => $featuredCategory->custom_url,
                    'custom_image' => $featuredCategory->custom_image ? asset('storage/' . $featuredCategory->custom_image) : null
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Error de validación: ' . implode(', ', $e->validator->getMessageBag()->all()),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Exception in updateCustomization', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove custom image
     */
    public function removeCustomImage(FeaturedCategory $featuredCategory)
    {
        if ($featuredCategory->custom_image) {
            Storage::disk('public')->delete($featuredCategory->custom_image);
            $featuredCategory->update(['custom_image' => null]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Imagen personalizada eliminada'
        ]);
    }
}
