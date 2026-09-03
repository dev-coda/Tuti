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
        'restrict_cities',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'restrict_cities' => 'boolean',
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
     * Whether this method is offered in a city.
     *
     * restrict_cities = false (opt-out): missing row = allowed.
     * restrict_cities = true (allowlist / pilot): only explicit enabled rows.
     */
    public function isAllowedForCity(?int $cityId): bool
    {
        if ($this->restrict_cities) {
            if ($cityId === null) {
                return false;
            }

            $override = $this->cityOverride($cityId);

            return $override !== null && self::pivotEnabled($override->pivot->enabled);
        }

        if ($cityId === null) {
            return true;
        }

        $override = $this->cityOverride($cityId);
        if (! $override) {
            return true;
        }

        return self::pivotEnabled($override->pivot->enabled);
    }

    private function cityOverride(int $cityId): ?City
    {
        return $this->cities()
            ->where('cities.id', $cityId)
            ->first();
    }

    private static function pivotEnabled(mixed $enabled): bool
    {
        return $enabled !== false && $enabled !== 0 && $enabled !== '0';
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
