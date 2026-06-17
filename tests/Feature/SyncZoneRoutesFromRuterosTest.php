<?php

use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneRoute;
use App\Models\ZoneWarehouse;
use App\Services\RuteroZoneSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft token', 'value' => 'test-token', 'show' => false]
    );
});

it('discovers zones from sellers, clients, warehouses, and existing catalog', function () {
    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    $client = User::factory()->create();
    Zone::create([
        'user_id' => $client->id,
        'zone' => '933',
        'route' => 'R100',
        'day' => '1',
        'address' => 'Calle 1',
        'code' => 'SUC1',
    ]);

    ZoneWarehouse::create(['zone_code' => '900', 'bodega_code' => 'BOD-A']);
    ZoneRoute::create(['zone' => '001', 'route' => '1234']);

    $zones = app(RuteroZoneSyncService::class)->discoverZoneCodes();

    expect($zones)->toContain('301', '933', '900', '001');
});

it('imports zone routes and updates client zone rows from getRuteros', function () {
    config(['microsoft.resource' => 'https://dynamics.test']);

    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    $client = User::factory()->create();
    Zone::create([
        'user_id' => $client->id,
        'zone' => '301',
        'route' => 'OLD1',
        'day' => '1',
        'address' => 'Calle 1',
        'code' => 'SUC1',
    ]);

    Http::fake([
        'https://dynamics.test*' => Http::response(fakeGetRuterosSoap([
            [
                'code' => 'SUC1',
                'zone' => '301',
                'route' => '1234',
                'day' => '5',
                'address' => 'Calle 1',
                'name' => 'Cliente Uno',
            ],
            [
                'code' => 'SUC9',
                'zone' => '301',
                'route' => '5678',
                'day' => '2',
                'address' => 'Calle 9',
                'name' => 'Cliente Nuevo',
            ],
        ]), 200),
    ]);

    $summary = app(RuteroZoneSyncService::class)->syncFromRuteros(['301']);

    expect($summary['zones_processed'])->toBe(1)
        ->and($summary['catalog_routes_created'])->toBe(2)
        ->and($summary['client_zone_rows_updated'])->toBe(1)
        ->and(ZoneRoute::where('zone', '301')->pluck('route')->sort()->values()->all())->toBe(['1234', '5678']);

    $clientZone = Zone::where('code', 'SUC1')->first();
    expect($clientZone->route)->toBe('1234')
        ->and($clientZone->day)->toBe('5');
});

it('supports dry run without writing catalog or client rows', function () {
    config(['microsoft.resource' => 'https://dynamics.test']);

    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    Http::fake([
        'https://dynamics.test*' => Http::response(fakeGetRuterosSoap([
            [
                'code' => 'SUC1',
                'zone' => '301',
                'route' => '1234',
                'day' => '5',
                'address' => 'Calle 1',
                'name' => 'Cliente Uno',
            ],
        ]), 200),
    ]);

    $summary = app(RuteroZoneSyncService::class)->syncFromRuteros(['301'], updateClients: true, dryRun: true);

    expect($summary['catalog_routes_seen'])->toBe(1)
        ->and($summary['catalog_routes_created'])->toBe(0)
        ->and(ZoneRoute::count())->toBe(0);
});
