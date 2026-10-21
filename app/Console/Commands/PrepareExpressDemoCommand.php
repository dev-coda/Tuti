<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Services\DepartmentPlaceholderZoneService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PrepareExpressDemoCommand extends Command
{
    protected $signature = 'express:prepare-demo {city=Medellín : Ciudad piloto (cabecera o cualquier ciudad del departamento)}';

    protected $description = 'Enable Entrega Especial for one city, turn off force-date there, and ready department placeholder zones';

    public function handle(DepartmentPlaceholderZoneService $placeholders): int
    {
        $cityQuery = trim((string) $this->argument('city'));
        $city = City::query()
            ->with('state')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($cityQuery, 'UTF-8')])
            ->first();

        if (! $city) {
            $city = City::query()
                ->with('state')
                ->whereRaw('LOWER(name) like ?', ['%'.mb_strtolower($cityQuery, 'UTF-8').'%'])
                ->first();
        }

        if (! $city) {
            $this->error("No se encontró la ciudad \"{$cityQuery}\".");

            return self::FAILURE;
        }

        if (config('services.coordinadora.express_48h_disabled')) {
            $this->error('COORDINADORA_EXPRESS_48H_DISABLED=true bloquea express. Quítalo del .env y recarga config.');

            return self::FAILURE;
        }

        $placeholders->syncCatalogFromPreferredCities();
        $placeholders->ensureCatalogForCity($city);

        Setting::updateOrCreate(
            ['key' => 'express_48h_enabled'],
            ['name' => 'Entrega Express 48h', 'value' => '1', 'show' => false]
        );
        Cache::forget('setting_express_48h_enabled');

        $express = ShippingMethod::query()->where('code', 'express')->first();
        if (! $express) {
            $this->error('No existe el método de envío express.');

            return self::FAILURE;
        }

        $express->update(['enabled' => true, 'restrict_cities' => true]);
        $express->cities()->sync([$city->id => ['enabled' => true]]);

        $city->force_delivery_date_enabled = false;
        $city->save();

        $placeholder = $placeholders->resolveForUser(
            tap(new \App\Models\User, fn ($user) => $user->city_id = $city->id)
        );

        $this->info('Demo express lista.');
        $this->table(
            ['Check', 'Valor'],
            [
                ['Ciudad piloto', $city->name.' / '.($city->state?->name ?? '—')],
                ['Express 48h', 'activado'],
                ['Método express', 'habilitado + solo esta ciudad'],
                ['Forzar fecha en la ciudad', 'desactivado'],
                ['Placeholder zona', $placeholder?->zone ?? 'FALTA — llena Admin → Zonas placeholder'],
                ['Placeholder ruta', $placeholder?->route ?? 'FALTA'],
                ['Placeholder DANE', $placeholder?->dane_code ?? 'FALTA'],
            ]
        );

        $this->line('Usa un cliente de '.$city->name.' (con o sin sucursal). Si no tiene sucursal, el carrito crea la zona cabecera.');

        return $placeholder?->isReady() ? self::SUCCESS : self::FAILURE;
    }
}
