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
    ];

    /**
     * Get the category associated with this featured category.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
