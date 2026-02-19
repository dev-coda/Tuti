<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpsellZoneProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'upsell_zone_id',
        'product_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function zone()
    {
        return $this->belongsTo(UpsellZone::class, 'upsell_zone_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
