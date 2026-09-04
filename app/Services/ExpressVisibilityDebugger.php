<?php

namespace App\Services;

use App\Models\City;
use App\Models\Setting;
use App\Models\ShippingMethod;
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
                ? 'Modo piloto (allowlist): sin ciudad de cliente → express oculto.'
                : 'Sin ciudad de cliente → se asume permitido (modo opt-out).';
        }

        $override = $express->cities()->where('cities.id', $cityId)->first();
        $pivot = $override?->pivot?->enabled;

        if ($restrictCities) {
            if ($cityAllowed) {
                return 'Modo piloto: ciudad en la lista permitida (pivot enabled=1). Otras ciudades no cambian.';
            }

            return $override
                ? 'Modo piloto: la ciudad tiene pivot enabled=0.'
                : 'Modo piloto: la ciudad no está en la allowlist. Actívala sola sin tocar las demás.';
        }

        if ($cityAllowed) {
            return $override
                ? 'Modo global: ciudad con override enabled=1.'
                : 'Modo global: sin override → permitida por defecto.';
        }

        return 'Modo global: ciudad excluida (pivot enabled=0). El resto de ciudades no se modifica.';
    }
}
