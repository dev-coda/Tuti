<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'active', 'image', 'parent_id', 'inventory_opt_out'];


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

    public function ImageUrl($size)
    {
        return $this->image;
    }
}
