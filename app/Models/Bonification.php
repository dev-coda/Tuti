<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bonification extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'buy',
        'get',
        'product_id',
        'max',
        'allow_discounts'
    ];

    protected $casts = [
        'allow_discounts' => 'boolean',
    ];


    public function product(){
        return $this->belongsTo(Product::class);
    }


    public function products(){
        return $this->belongsToMany(Product::class)->orderBy('name');
    }

}
