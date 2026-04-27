<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'variation_item_id',
        'source_sku',
        'bodega_code',
        'available',
        'physical',
        'reserved',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variationItem()
    {
        return $this->belongsTo(VariationItem::class);
    }
}
