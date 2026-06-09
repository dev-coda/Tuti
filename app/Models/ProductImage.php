<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'path',
        'position',
        'variation_item_id',
    ];

    public function variationItem()
    {
        return $this->belongsTo(VariationItem::class);
    }
}
