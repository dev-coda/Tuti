<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoneRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone',
        'route',
    ];
}

