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

    public function cities()
    {
        return $this->belongsToMany(City::class, 'city_shipping_method')
            ->withPivot('enabled')
            ->withTimestamps();
    }

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

    /**
     * Whether this method is offered in a city. Missing override = allowed.
     */
    public function isAllowedForCity(?int $cityId): bool
    {
        if ($cityId === null) {
            return true;
        }

        $override = $this->cities()
            ->where('cities.id', $cityId)
            ->first();

        if (! $override) {
            return true;
        }

        return $override->pivot->enabled !== false
            && $override->pivot->enabled !== 0
            && $override->pivot->enabled !== '0';
    }

    /**
     * City-level allow check by method code. Unknown codes are not blocked.
     */
    public static function isCodeAllowedForCity(string $code, ?int $cityId): bool
    {
        $method = self::query()->where('code', $code)->first();
        if (! $method) {
            return true;
        }

        return $method->isAllowedForCity($cityId);
    }

    /**
     * @return array{standard: bool, express: bool}
     */
    public static function cityAvailabilityFlags(?int $cityId): array
    {
        $tronex = self::query()->where('code', 'tronex')->first();
        $express = self::query()->where('code', 'express')->first();

        return [
            'standard' => $tronex ? $tronex->isAllowedForCity($cityId) : true,
            'express' => $express ? $express->isAllowedForCity($cityId) : true,
        ];
    }
}
