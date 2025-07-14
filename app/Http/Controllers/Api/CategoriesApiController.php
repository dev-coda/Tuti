<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
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

        // Get featured categories instead of hardcoded ones
        $featuredCategoryIds = DB::table('featured_categories')
            ->orderBy('position')
            ->pluck('category_id');

        if ($featuredCategoryIds->isEmpty()) {
            // If no featured categories, fallback to hardcoded ones (for backward compatibility)
            $query = Category::where('active', 1)
                ->whereIn('id', [3, 17, 4])
                ->take(3);
        } else {
            // Get featured categories maintaining the order
            $query = Category::whereIn('id', $featuredCategoryIds)
                ->where('active', 1)
                ->take(3);
        }

        // Debug the SQL query
        Log::info('Categories SQL Query: ' . $query->toSql());

        $categories = $query->get();

        // If we have featured categories, sort them by position
        if (!$featuredCategoryIds->isEmpty()) {
            $categories = $categories->sortBy(function ($category) use ($featuredCategoryIds) {
                return $featuredCategoryIds->search($category->id);
            })->values();
        }

        // Debug categories count
        Log::info('Categories fetched: ' . $categories->count());

        if ($categories->isEmpty()) {
            Log::info('No categories found');
            return response()->json([
                'message' => 'No categories found',
                'categories' => []
            ]);
        }

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
            ->take(3)
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
