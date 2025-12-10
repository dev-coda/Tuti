<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderProductBonification extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_product_id',
        'bonification_id',
        'product_id',
        'variation_item_id',
        'quantity',
        'order_id',
    ];

    public function product(){
        return $this->belongsTo(Product::class);
    }

    public function bonification(){
        return $this->belongsTo(Bonification::class);
    }

    /**
     * Accessor for percentage (bonifications always have 0% discount)
     */
    public function getPercentageAttribute()
    {
        return 0;
    }

    /**
     * Accessor for package_quantity (bonifications are always individual items)
     */
    public function getPackageQuantityAttribute()
    {
        return 1;
    }

    /**
     * Accessor for price (bonifications are always free)
     */
    public function getPriceAttribute()
    {
        return 0;
    }
}
