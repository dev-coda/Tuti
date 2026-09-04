<?php

namespace App\Services;

use App\Models\City;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Models\Zone;

/**
 * Explicit checklist of every gate that can hide Entrega Especial for a client city.
 */
class ExpressVisibilityDebugger
{
    /**
     * @return array{
     *     visible: bool,
     *     city_id: int|null,
     *     city_name: string|null,
     *     city_code: string|null,
     *     city_source: string|null,
     *     mode: string,
     *     checks: list<array{key: string, ok: bool, label: string, detail: string}>
     * }
     */
    public function forUser(User $user, ?Zone $zone = null): array
    {
        $resolvedId = $user->resolvedCityId();
        $source = $user->city_id
            ? 'users.city_id'
            : ($user->city_code ? 'users.city_code→DANE→cities.id' : null);

        $result = $this->forCity($resolvedId, $zone);
        $result['city_code'] = $user->city_code ? (string) $user->city_code : null;
        $result['city_source'] = $source;

        if ($result['city_name'] === null && $user->city_code) {
            $result['checks'] = array_map(function (array $check) use ($user, $resolvedId) {
                if ($check['key'] !== 'city_allowed') {
                    return $check;
                }

                if ($resolvedId === null) {
                    $check['ok'] = false;
                    $check['detail'] = 'Cliente tiene city_code='.$user->city_code.' pero no mapea a ninguna ciudad del catálogo (cities).';
                }

                return $check;
            }, $result['checks']);
            $result['visible'] = collect($result['checks'])->every(fn (array $c) => $c['ok']);
        }

        return $result;
    }

    /**
     * @return array{
     *     visible: bool,
     *     city_id: int|null,
     *     city_name: string|null,
     *     city_code: string|null,
     *     city_source: string|null,
     *     mode: string,
     *     checks: list<array{key: string, ok: bool, label: string, detail: string}>
     * }
     */
    public function forCity(?int $cityId, ?Zone $zone = null): array
    {
        $city = $cityId ? City::query()->with('state')->find($cityId) : null;
        $express = ShippingMethod::query()->where('code', 'express')->first();
        $envDisabled = (bool) config('services.coordinadora.express_48h_disabled');
        $settingOn = Setting::getByKeyWithDefault('express_48h_enabled', '0');
        $settingEnabled = $settingOn === '1' || $settingOn === 1 || $settingOn === true;
        $methodEnabled = (bool) ($express?->enabled);
        $restrictCities = (bool) ($express?->restrict_cities);
        $cityAllowed = $express ? $express->isAllowedForCity($cityId) : false;
        $zoneAllows = $zone ? $zone->allowsShippingMethod('express') : true;

        $cityDetail = $this->cityDetail($express, $cityId, $restrictCities, $cityAllowed);
        $mode = $restrictCities ? 'allowlist' : 'opt-out';

        $checks = [
            [
                'key' => 'env_kill_switch',
                'ok' => ! $envDisabled,
                'label' => 'Variable de entorno',
                'detail' => $envDisabled
                    ? 'COORDINADORA_EXPRESS_48H_DISABLED=true fuerza Entrega Especial apagada en todo el ambiente.'
                    : 'Kill-switch de entorno inactivo.',
            ],
            [
                'key' => 'global_setting',
                'ok' => $settingEnabled && ! $envDisabled,
                'label' => 'Ajuste global (Admin → Configuración)',
                'detail' => $settingEnabled
                    ? 'express_48h_enabled=1'
                    : 'express_48h_enabled está en 0. Actívalo en Configuración → Entrega Express 48h.',
            ],
            [
                'key' => 'method_enabled',
                'ok' => $methodEnabled,
                'label' => 'Método express habilitado',
                'detail' => $methodEnabled
                    ? 'shipping_methods.express.enabled=1'
                    : 'El método express está deshabilitado en Métodos de envío.',
            ],
            [
                'key' => 'city_allowed',
                'ok' => $cityAllowed,
                'label' => 'Disponible en la ciudad del cliente',
                'detail' => $cityDetail,
            ],
            [
                'key' => 'zone_allowed',
                'ok' => $zoneAllows,
                'label' => 'Dirección / sucursal permite express',
                'detail' => $zone
                    ? ($zoneAllows
                        ? 'Zona #'.$zone->id.' tiene shipping_express_enabled activo.'
                        : 'Zona #'.$zone->id.' tiene shipping_express_enabled=0 (solo esta dirección).')
                    : 'Sin zona seleccionada — no aplica filtro por dirección.',
            ],
        ];

        $visible = collect($checks)->every(fn (array $c) => $c['ok']);

        return [
            'visible' => $visible,
            'city_id' => $city?->id,
            'city_name' => $city ? trim($city->name.($city->state ? ' / '.$city->state->name : '')) : null,
            'city_code' => null,
            'city_source' => $cityId ? 'cities.id' : null,
            'mode' => $mode,
            'checks' => $checks,
        ];
    }

    private function cityDetail(?ShippingMethod $express, ?int $cityId, bool $restrictCities, bool $cityAllowed): string
    {
        if (! $express) {
            return 'No existe el método de envío express.';
        }

        if ($cityId === null) {
            return $restrictCities
                ? 'Modo piloto (allowlist): sin ciudad resuelta (ni city_id ni city_code mapeable) → express oculto.'
                : 'Sin ciudad resuelta → se asume permitido (modo opt-out).';
        }

        $override = $express->cities()->where('cities.id', $cityId)->first();

        if ($restrictCities) {
            if ($cityAllowed) {
                return 'Modo piloto: ciudad #'.$cityId.' en la lista permitida. Otras ciudades no cambian.';
            }

            return $override
                ? 'Modo piloto: la ciudad #'.$cityId.' tiene pivot enabled=0.'
                : 'Modo piloto: la ciudad #'.$cityId.' no está en la allowlist. Actívala sola sin tocar las demás.';
        }

        if ($cityAllowed) {
            return $override
                ? 'Modo global: ciudad #'.$cityId.' con override enabled=1.'
                : 'Modo global: ciudad #'.$cityId.' sin override → permitida por defecto.';
        }

        return 'Modo global: ciudad #'.$cityId.' excluida (pivot enabled=0). El resto de ciudades no se modifica.';
    }
}
