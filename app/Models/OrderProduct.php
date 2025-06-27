<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'discount',
        'variation_item_id',
        'is_bonification',
        'percentage',
        'package_quantity',
    ];



    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variationItem()
    {

        return $this->belongsTo(VariationItem::class, 'variation_item_id');
    }
}
