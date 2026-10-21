<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'state_id',
        'active',
        'is_preferred',
        'force_delivery_date_enabled',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_preferred' => 'boolean',
        'force_delivery_date_enabled' => 'boolean',
    ];

    // Query Scopes for safe city filtering
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopePreferred($query)
    {
        return $query->where('is_preferred', true);
    }

    public function scopeForRegistration($query)
    {
        return $query->active()->preferred();
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function shippingMethods()
    {
        return $this->belongsToMany(ShippingMethod::class, 'city_shipping_method')
            ->withPivot('enabled')
            ->withTimestamps();
    }

    public function allowsForceDeliveryDate(): bool
    {
        return $this->force_delivery_date_enabled !== false
            && $this->force_delivery_date_enabled !== 0
            && $this->force_delivery_date_enabled !== '0';
    }

    /**
     * Map a Dynamics/DANE city_code (e.g. "05001") to a cities.id via states.csv.
     * Almost all rutero clients store city_code, not city_id.
     */
    public static function findIdByDaneCode(?string $cityCode): ?int
    {
        $normalized = \App\Services\Shipping\DaneCodeService::normalize($cityCode);
        if ($normalized === null) {
            return null;
        }

        $fiveDigit = substr($normalized, 0, 5);
        $map = self::daneCodeToIdMap();

        return $map[$fiveDigit] ?? null;
    }

    /**
     * @return array<string, int> 5-digit divipola => cities.id
     */
    private static function daneCodeToIdMap(): array
    {
        $map = [];
        foreach (static::query()->with('state')->get() as $city) {
            $dane = \App\Services\Shipping\DaneCodeService::forCity($city->name, $city->state?->name);
            if ($dane === null) {
                continue;
            }

            $fiveDigit = substr($dane, 0, 5);
            if (! isset($map[$fiveDigit]) || $city->is_preferred) {
                $map[$fiveDigit] = (int) $city->id;
            }
        }

        return $map;
    }

    /**
     * Match a catalog city by name (and optionally departamento), case-insensitive.
     */
    public static function findIdByNameAndState(?string $cityName, ?string $stateName = null): ?int
    {
        $cityName = trim((string) $cityName);
        if ($cityName === '') {
            return null;
        }

        $query = static::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($cityName, 'UTF-8')]);

        $stateName = trim((string) $stateName);
        if ($stateName !== '') {
            $query->whereHas(
                'state',
                fn ($state) => $state->whereRaw('LOWER(name) = ?', [mb_strtolower($stateName, 'UTF-8')])
            );
        }

        $id = $query->value('id');

        return $id !== null ? (int) $id : null;
    }
}