<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'active',
        'image',
        'parent_id',
        'inventory_opt_out',
        'default_sort_order',
        'highlighted_brand_ids',
        'enable_highlighting'
    ];

    protected $casts = [
        'active' => 'boolean',
        'enable_highlighting' => 'boolean',
        'highlighted_brand_ids' => 'array',
    ];

    // Constants for sort order options
    const SORT_MOST_RECENT = 'most_recent';
    const SORT_PRICE_ASC = 'price_asc';
    const SORT_PRICE_DESC = 'price_desc';
    const SORT_NAME_ASC = 'name_asc';
    const SORT_NAME_DESC = 'name_desc';
    const SORT_BEST_SELLING = 'best_selling';


    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class);
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }


    public function products()
    {
        return $this->belongsToMany(Product::class);
    }

    public function highlightedProducts()
    {
        return $this->hasMany(ProductHighlight::class)->where('active', true)->orderBy('position');
    }

    public function highlightedBrands()
    {
        return $this->belongsToMany(Brand::class, 'category_highlighted_brands', 'category_id', 'brand_id');
    }

    public function ImageUrl($size)
    {
        return $this->image;
    }

    /**
     * Get the sort order options for display
     */
    public static function getSortOrderOptions(): array
    {
        return [
            self::SORT_MOST_RECENT => 'Más recientes',
            self::SORT_PRICE_ASC => 'Precio: menor a mayor',
            self::SORT_PRICE_DESC => 'Precio: mayor a menor',
            self::SORT_NAME_ASC => 'Nombre: A-Z',
            self::SORT_NAME_DESC => 'Nombre: Z-A',
            self::SORT_BEST_SELLING => 'Más vendidos',
        ];
    }

    /**
     * Get products with category-specific sorting and highlighting
     */
    public function getOrderedProducts($additionalFilters = null)
    {
        $query = $this->products()->active();

        // Apply additional filters if provided
        if ($additionalFilters) {
            $query = $additionalFilters($query);
        }

        // Get highlighted products first if highlighting is enabled
        $highlightedProductIds = [];
        $highlightedByBrandIds = [];

        if ($this->enable_highlighting) {
            // Get specifically highlighted products (up to 4 positions)
            $highlightedProductIds = $this->highlightedProducts()
                ->pluck('product_id')
                ->toArray();

            // Get products from highlighted brands
            if (!empty($this->highlighted_brand_ids)) {
                $highlightedByBrandIds = $this->products()
                    ->active()
                    ->whereIn('brand_id', $this->highlighted_brand_ids)
                    ->whereNotIn('id', $highlightedProductIds) // Exclude already highlighted products
                    ->pluck('id')
                    ->toArray();
            }
        }

        // Apply category's default sorting
        $query = $this->applySorting($query);

        // If highlighting is enabled, we need to handle the order specially
        if ($this->enable_highlighting && (!empty($highlightedProductIds) || !empty($highlightedByBrandIds))) {
            $allProducts = $query->get();

            // Separate highlighted and regular products
            $highlighted = $allProducts->whereIn('id', array_merge($highlightedProductIds, $highlightedByBrandIds));
            $regular = $allProducts->whereNotIn('id', array_merge($highlightedProductIds, $highlightedByBrandIds));

            // Sort highlighted products by position (specific highlights first, then brand highlights)
            $sortedHighlighted = collect();

            // Add specifically highlighted products in position order
            foreach ($highlightedProductIds as $productId) {
                $product = $highlighted->where('id', $productId)->first();
                if ($product) {
                    $sortedHighlighted->push($product);
                }
            }

            // Add brand highlighted products
            foreach ($highlightedByBrandIds as $productId) {
                $product = $highlighted->where('id', $productId)->first();
                if ($product) {
                    $sortedHighlighted->push($product);
                }
            }

            return $sortedHighlighted->merge($regular);
        }

        return $query->get();
    }

    /**
     * Apply the category's default sorting to a query
     */
    private function applySorting($query)
    {
        switch ($this->default_sort_order) {
            case self::SORT_MOST_RECENT:
                return $query->orderBy('created_at', 'desc');
            case self::SORT_PRICE_ASC:
                return $query->orderBy('price', 'asc');
            case self::SORT_PRICE_DESC:
                return $query->orderBy('price', 'desc');
            case self::SORT_NAME_ASC:
                return $query->orderBy('name', 'asc');
            case self::SORT_NAME_DESC:
                return $query->orderBy('name', 'desc');
            case self::SORT_BEST_SELLING:
                return $query->orderBy('sales_count', 'desc');
            default:
                return $query->orderBy('created_at', 'desc');
        }
    }
}
