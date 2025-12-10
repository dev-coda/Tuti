<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'priority',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Products this tag applies to
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'tag_product', 'tag_id', 'product_id');
    }

    /**
     * Categories this tag applies to
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'tag_category', 'tag_id', 'category_id');
    }

    /**
     * Brands this tag applies to
     */
    public function brands()
    {
        return $this->belongsToMany(Brand::class, 'tag_brand', 'tag_id', 'brand_id');
    }

    /**
     * Bonifications this tag applies to
     */
    public function bonifications()
    {
        return $this->belongsToMany(Bonification::class, 'tag_bonification', 'tag_id', 'bonification_id');
    }

    /**
     * Scope for enabled tags
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Check if tag applies to a product
     */
    public function appliesToProduct(Product $product): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // Check if tag applies to specific products
        if ($this->products->contains($product->id)) {
            return true;
        }

        // Check if tag applies to product's categories
        if ($this->categories->isNotEmpty()) {
            $productCategoryIds = $product->categories->pluck('id');
            if ($this->categories->pluck('id')->intersect($productCategoryIds)->isNotEmpty()) {
                return true;
            }
        }

        // Check if tag applies to product's brand
        if ($this->brands->isNotEmpty() && $product->brand_id) {
            if ($this->brands->contains($product->brand_id)) {
                return true;
            }
        }

        // Check if tag applies to product's bonifications
        if ($this->bonifications->isNotEmpty()) {
            $productBonificationIds = $product->bonifications->pluck('id');
            if ($this->bonifications->pluck('id')->intersect($productBonificationIds)->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the tag for a product (lowest priority wins)
     */
    public static function getTagForProduct(Product $product): ?self
    {
        return self::enabled()
            ->with(['products', 'categories', 'brands', 'bonifications'])
            ->get()
            ->filter(function ($tag) use ($product) {
                return $tag->appliesToProduct($product);
            })
            ->sortBy('priority')
            ->first();
    }
}
