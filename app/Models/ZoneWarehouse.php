<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoneWarehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_code',
        'bodega_code',
    ];
}
