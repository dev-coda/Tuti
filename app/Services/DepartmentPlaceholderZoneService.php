<?php

namespace App\Services;

use App\Models\City;
use App\Models\DepartmentPlaceholderZone;
use App\Models\User;
use App\Models\Zone;
use App\Services\Shipping\DaneCodeService;

class DepartmentPlaceholderZoneService
{
    /**
     * Known cabecera logistics so a demo/pilot works before ops fills the admin list.
     * Zone codes match existing bodega mappings in config/zone_warehouses.php.
     *
     * @var array<string, array{zone: string, route: string, dane: string}>
     */
    private const DEFAULTS_BY_STATE = [
        'antioquia' => ['zone' => '102', 'route' => '1020', 'dane' => '05001000'],
        'bogota d.c.' => ['zone' => '933', 'route' => '9330', 'dane' => '11001000'],
        'bogotá d.c.' => ['zone' => '933', 'route' => '9330', 'dane' => '11001000'],
        'cundinamarca' => ['zone' => '933', 'route' => '9330', 'dane' => '11001000'],
        'meta' => ['zone' => '933', 'route' => '9330', 'dane' => '50001000'],
        'cordoba' => ['zone' => '509', 'route' => '5090', 'dane' => '23001000'],
        'córdoba' => ['zone' => '509', 'route' => '5090', 'dane' => '23001000'],
        'valle del cauca' => ['zone' => '621', 'route' => '6210', 'dane' => '76001000'],
        'risaralda' => ['zone' => '605', 'route' => '6050', 'dane' => '66001000'],
        'norte de santander' => ['zone' => '409', 'route' => '4090', 'dane' => '54001000'],
        'santander' => ['zone' => '405', 'route' => '4050', 'dane' => '68001000'],
        'atlantico' => ['zone' => '711', 'route' => '7110', 'dane' => '08001000'],
        'atlántico' => ['zone' => '711', 'route' => '7110', 'dane' => '08001000'],
        'bolivar' => ['zone' => '717', 'route' => '7170', 'dane' => '13001000'],
        'bolívar' => ['zone' => '717', 'route' => '7170', 'dane' => '13001000'],
        'cesar' => ['zone' => '705', 'route' => '7050', 'dane' => '20001000'],
    ];

    /**
     * Ensure one catalog row exists per preferred (main) city / department.
     */
    public function syncCatalogFromPreferredCities(): int
    {
        $created = 0;

        $cities = City::query()
            ->preferred()
            ->with('state')
            ->orderBy('name')
            ->get();

        foreach ($cities as $city) {
            if (! $city->state_id) {
                continue;
            }

            $row = DepartmentPlaceholderZone::query()->firstOrNew(['state_id' => $city->state_id]);
            $wasNew = ! $row->exists;
            $defaults = $this->defaultsForState($city->state?->name);

            if ($wasNew || ! $row->city_id) {
                $row->city_id = $city->id;
            }
            if (! $row->dane_code) {
                $row->dane_code = $defaults['dane']
                    ?? DaneCodeService::forCity($city->name, $city->state?->name);
            }
            if (! $row->zone && isset($defaults['zone'])) {
                $row->zone = $defaults['zone'];
            }
            if (! $row->route && isset($defaults['route'])) {
                $row->route = $defaults['route'];
            }
            if (! $row->address) {
                $row->address = 'Zona placeholder — '.$city->name;
            }
            if ($row->enabled === null) {
                $row->enabled = true;
            }

            $row->save();
            if ($wasNew) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @return array{zone?: string, route?: string, dane?: string}|null
     */
    public function defaultsForState(?string $stateName): ?array
    {
        $key = $this->normalizeName($stateName);
        if ($key === '') {
            return null;
        }

        return self::DEFAULTS_BY_STATE[$key] ?? null;
    }

    private function normalizeName(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    public function ensureCatalogForCity(City $city): ?DepartmentPlaceholderZone
    {
        $city->loadMissing('state');
        if (! $city->state_id) {
            return null;
        }

        $defaults = $this->defaultsForState($city->state?->name);
        $row = DepartmentPlaceholderZone::query()->firstOrNew(['state_id' => $city->state_id]);
        if (! $row->city_id) {
            $row->city_id = $city->id;
        }
        if (! $row->dane_code) {
            $row->dane_code = $defaults['dane']
                ?? DaneCodeService::forCity($city->name, $city->state?->name);
        }
        if (! $row->zone && isset($defaults['zone'])) {
            $row->zone = $defaults['zone'];
        }
        if (! $row->route && isset($defaults['route'])) {
            $row->route = $defaults['route'];
        }
        if (! $row->address) {
            $row->address = 'Zona placeholder — '.$city->name;
        }
        $row->enabled = true;
        $row->save();

        return $row->fresh(['city', 'state']);
    }

    public function resolveForUser(?User $user): ?DepartmentPlaceholderZone
    {
        $cityId = $user?->resolvedCityId();
        if (! $cityId) {
            return null;
        }

        $city = ($user->relationLoaded('city') && $user->city && (int) $user->city->id === $cityId)
            ? $user->city
            : City::query()->with('state')->find($cityId);

        if (! $city?->state_id) {
            return null;
        }

        $placeholder = DepartmentPlaceholderZone::query()
            ->with(['city', 'state'])
            ->where('state_id', $city->state_id)
            ->first();

        return $placeholder?->isReady() ? $placeholder : null;
    }

    /**
     * Attach (or refresh) a placeholder sucursal when the client has no real zone.
     */
    public function ensureZoneForUser(User $user): ?Zone
    {
        $user->loadMissing('zones');

        $real = $user->zones->first(fn (Zone $zone) => ! $zone->isPlaceholder());
        if ($real) {
            return $real;
        }

        $placeholder = $this->resolveForUser($user);
        if (! $placeholder) {
            return null;
        }

        $existing = $user->zones->first(fn (Zone $zone) => $zone->isPlaceholder());
        $attributes = $this->zoneAttributes($placeholder);

        if ($existing) {
            $existing->update($attributes);

            return $existing->fresh();
        }

        $zone = $user->zones()->create($attributes);
        $user->unsetRelation('zones');

        return $zone;
    }

    public function isPlaceholderZone(Zone $zone): bool
    {
        return $zone->isPlaceholder();
    }

    /**
     * @return array<string, mixed>
     */
    private function zoneAttributes(DepartmentPlaceholderZone $placeholder): array
    {
        $city = $placeholder->city;
        $dane = $placeholder->dane_code
            ?: DaneCodeService::forCity($city?->name, $placeholder->state?->name);

        $attributes = [
            'zone' => strtoupper(trim((string) $placeholder->zone)),
            'route' => trim((string) $placeholder->route),
            'day' => $placeholder->day ?: '',
            'address' => $placeholder->address
                ?: ('Zona placeholder — '.($city?->name ?? 'ciudad cabecera')),
            'code' => 'PH-'.$placeholder->state_id,
            'dane_code' => $dane,
            'fulfillment_provider_48h' => Zone::FULFILLMENT_PROVIDER_COORDINADORA,
            'shipping_standard_enabled' => true,
            'shipping_express_enabled' => true,
        ];

        if (Zone::supportsIsPlaceholder()) {
            $attributes['is_placeholder'] = true;
        }

        return $attributes;
    }
}
