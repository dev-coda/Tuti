<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'bodega_code',
        'available',
        'physical',
        'reserved',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
