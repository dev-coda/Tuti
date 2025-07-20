<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeaturedCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'position',
        'custom_image',
        'custom_title',
        'custom_url',
    ];

    /**
     * Get the category associated with this featured category.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the display title (custom title if set, otherwise category name)
     */
    public function getDisplayTitleAttribute()
    {
        return $this->custom_title ?: $this->category->name;
    }

    /**
     * Get the display image (custom image if set, otherwise category image)
     */
    public function getDisplayImageAttribute()
    {
        if ($this->custom_image) {
            return asset('storage/' . $this->custom_image);
        } elseif ($this->category->image) {
            return asset('storage/' . $this->category->image);
        }

        return null;
    }

    /**
     * Get the raw image path (for internal use)
     */
    public function getImagePathAttribute()
    {
        return $this->custom_image ?: $this->category->image;
    }

    /**
     * Get the display URL (custom URL if set, otherwise category route)
     */
    public function getDisplayUrlAttribute()
    {
        if ($this->custom_url) {
            return $this->custom_url;
        }

        // Return the category route using the category slug
        return route('category', [
            'slug' => $this->category->slug,
            'slug2' => 'productos',
            'order' => '1',
            'category_id' => $this->category_id,
            'brand_id' => '0'
        ]);
    }
}
