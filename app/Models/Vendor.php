<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image',
        'banner',
        'slug',
        'minimum_purchase',
        'minimum_discount_amount',
        'active',
        'discount',
        'first_purchase_only',
        'vendor_type',
    ];

    public function brands()
    {
        return $this->hasMany(Brand::class);
    }
}
