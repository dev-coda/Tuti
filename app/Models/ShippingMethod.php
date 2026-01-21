<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'enabled',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope for enabled shipping methods
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Get all enabled shipping methods ordered by sort_order
     */
    public static function getEnabled()
    {
        return self::enabled()
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Check if a specific shipping method is enabled
     */
    public static function isEnabled(string $code): bool
    {
        return self::where('code', $code)->where('enabled', true)->exists();
    }
}
