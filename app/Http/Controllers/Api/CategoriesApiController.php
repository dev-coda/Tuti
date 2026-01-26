<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FeaturedCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

class CategoriesApiController extends Controller
{
    public function featured()
    {
        // Check if we should use most popular categories
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

        // Convert string to boolean properly
        $useMostPopular = $useMostPopularSetting->value === '1' || $useMostPopularSetting->value === 1 || $useMostPopularSetting->value === true;

        Log::info('API featured categories method - setting value', [
            'raw_value' => $useMostPopularSetting->value,
            'type' => gettype($useMostPopularSetting->value),
            'converted_boolean' => $useMostPopular
        ]);

        if ($useMostPopular) {
            // Return most popular categories
            return $this->mostPopular();
        }

        // Debug total categories count
        $totalCategories = Category::count();
        Log::info('Total categories in database: ' . $totalCategories);

        // Get featured categories with their customizations
        $featuredCategories = FeaturedCategory::with(['category'])
            ->orderBy('position')
            ->take(4)
            ->get();

        Log::info('Featured categories found: ' . $featuredCategories->count());

        if ($featuredCategories->isEmpty()) {
            // If no featured categories, fallback to hardcoded ones (for backward compatibility)
            $categories = Category::where('active', 1)
                ->whereIn('id', [3, 17, 4])
                ->take(4)
                ->get();

            $mappedCategories = $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'image' => $category->image ? asset('storage/' . $category->image) : null,
                    'url' => route('category', [
                        'slug' => $category->slug,
                        'slug2' => 'productos',
                        'order' => '1',
                        'category_id' => $category->id,
                        'brand_id' => '0'
                    ]),
                ];
            });
        } else {
            // Map featured categories with custom fields
            $mappedCategories = $featuredCategories->map(function ($featured) {
                $category = $featured->category;

                // Use custom values when available, otherwise fallback to category values
                $displayTitle = $featured->custom_title ?: $category->name;

                // Fix image URL construction to avoid 404s
                $displayImage = null;
                if ($featured->custom_image) {
                    $displayImage = asset('storage/' . $featured->custom_image);
                } elseif ($category->image) {
                    $displayImage = asset('storage/' . $category->image);
                }

                $displayUrl = $featured->custom_url ?: route('category', [
                    'slug' => $category->slug,
                    'slug2' => 'productos',
                    'order' => '1',
                    'category_id' => $category->id,
                    'brand_id' => '0'
                ]);

                return [
                    'id' => $category->id,
                    'name' => $displayTitle,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'image' => $displayImage,
                    'url' => $displayUrl,
                    // Include original category data for reference if needed
                    'original_name' => $category->name,
                    'is_customized' => !empty($featured->custom_title) || !empty($featured->custom_image) || !empty($featured->custom_url),
                ];
            });
        }

        $response = [
            'count' => $mappedCategories->count(),
            'categories' => $mappedCategories
        ];

        Log::info('Categories API Response:', $response);

        return response()->json($response);
    }

    public function mostPopular()
    {
        // Debug log
        Log::info('Fetching most popular categories');

        // Get category IDs sorted by total products count
        $mostPopularCategoryIds = DB::table('category_product')
            ->select('category_id', DB::raw('COUNT(product_id) as total_products'))
            ->groupBy('category_id')
            ->orderBy('total_products', 'desc')
            ->take(4)
            ->pluck('category_id');

        // If no categories with products, fallback to hardcoded ones
        if ($mostPopularCategoryIds->isEmpty()) {
            Log::info('No categories with products found, returning hardcoded categories instead');
            return $this->featured();
        }

        // Get categories maintaining the order of most popular
        $categories = Category::whereIn('id', $mostPopularCategoryIds)
            ->where('active', 1)
            ->get()
            ->sortBy(function ($category) use ($mostPopularCategoryIds) {
                return array_search($category->id, $mostPopularCategoryIds->toArray());
            })
            ->values();

        // Debug categories count
        Log::info('Most popular categories fetched: ' . $categories->count());

        $mappedCategories = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image' => $category->image ? asset('storage/' . $category->image) : null,
                'url' => route('category', $category->slug),
            ];
        });

        $response = [
            'count' => $categories->count(),
            'categories' => $mappedCategories
        ];

        Log::info('Most popular categories API Response:', $response);

        return response()->json($response);
    }

    /**
     * Get the section title setting
     */
    public function getSectionTitle()
    {
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

        return response()->json([
            'title' => $sectionTitleSetting->value
        ]);
    }
}
