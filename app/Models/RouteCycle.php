<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'route',
        'cycle',
    ];

    /**
     * Get cycle for a specific route
     */
    public static function getCycleForRoute(string $route): ?string
    {
        $routeCycle = self::where('route', $route)->first();
        return $routeCycle ? $routeCycle->cycle : null;
    }
}
