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
                'document' => '900111111',
            ],
            [
                'code' => 'SUC9',
                'zone' => '301',
                'route' => '5678',
                'day' => '2',
                'address' => 'Calle 9',
                'name' => 'Cliente Nuevo',
                'document' => '900999999',
                'business_name' => 'Negocio Nuevo',
                'phone' => '8870000',
            ],
        ]), 200),
    ]);

    $summary = app(RuteroZoneSyncService::class)->syncFromRuteros(['301']);

    expect($summary['zones_processed'])->toBe(1)
        ->and($summary['catalog_routes_created'])->toBe(2)
        ->and($summary['client_zone_rows_updated'])->toBe(1)
        ->and($summary['clients_created'])->toBe(1)
        ->and($summary['zones_created'])->toBe(1)
        ->and($summary['ruteros_unmatched'])->toBe(0)
        ->and(ZoneRoute::where('zone', '301')->pluck('route')->sort()->values()->all())->toBe(['1234', '5678']);

    $clientZone = Zone::where('code', 'SUC1')->first();
    expect($clientZone->route)->toBe('1234')
        ->and($clientZone->day)->toBe('5');

    $created = User::query()->where('document', '900999999')->first();
    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('Cliente Nuevo')
        ->and($created->business_name)->toBe('Negocio Nuevo')
        ->and($created->client_status)->toBe(User::CLIENT_STATUS_CLIENTE)
        ->and((int) $created->status_id)->toBe(User::ACTIVE);

    $newZone = Zone::where('code', 'SUC9')->first();
    expect($newZone)->not->toBeNull()
        ->and($newZone->user_id)->toBe($created->id)
        ->and($newZone->zone)->toBe('301')
        ->and($newZone->route)->toBe('5678')
        ->and($newZone->sucursal_uid)->toBe('cust:SUC9');
});

it('attaches a new sucursal to an existing client matched by document without duplicating the user', function () {
    config(['microsoft.resource' => 'https://dynamics.test']);

    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    $client = User::factory()->create([
        'document' => '900123456',
        'name' => 'Tienda Existente',
        'client_status' => User::CLIENT_STATUS_CLIENTE,
        'status_id' => User::ACTIVE,
    ]);
    Zone::create([
        'user_id' => $client->id,
        'zone' => '301',
        'route' => '1111',
        'day' => '1',
        'address' => 'Sucursal A',
        'code' => 'SUC-A',
    ]);

    Http::fake([
        'https://dynamics.test*' => Http::response(fakeGetRuterosSoap([
            [
                'code' => 'SUC-B',
                'zone' => '301',
                'route' => '2222',
                'day' => '3',
                'address' => 'Sucursal B',
                'name' => 'Tienda Existente',
                'document' => '900123456',
            ],
        ]), 200),
    ]);

    $summary = app(RuteroZoneSyncService::class)->syncFromRuteros(['301']);

    expect($summary['clients_created'])->toBe(0)
        ->and($summary['zones_created'])->toBe(1)
        ->and(User::where('document', '900123456')->count())->toBe(1)
        ->and($client->fresh()->zones()->count())->toBe(2);

    $branchB = Zone::where('code', 'SUC-B')->first();
    expect($branchB)->not->toBeNull()
        ->and($branchB->user_id)->toBe($client->id)
        ->and($branchB->sucursal_uid)->toBe('cust:SUC-B');
});

it('is idempotent: a second zone sync does not recreate clients or zones', function () {
    config(['microsoft.resource' => 'https://dynamics.test']);

    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    $payload = fakeGetRuterosSoap([
        [
            'code' => 'SUC9',
            'zone' => '301',
            'route' => '5678',
            'day' => '2',
            'address' => 'Calle 9',
            'name' => 'Cliente Nuevo',
            'document' => '900999999',
        ],
    ]);

    Http::fake([
        'https://dynamics.test*' => Http::response($payload, 200),
    ]);

    $service = app(RuteroZoneSyncService::class);
    $first = $service->syncFromRuteros(['301']);
    $second = $service->syncFromRuteros(['301']);

    expect($first['clients_created'])->toBe(1)
        ->and($first['zones_created'])->toBe(1)
        ->and($second['clients_created'])->toBe(0)
        ->and($second['zones_created'])->toBe(0)
        ->and(User::where('document', '900999999')->count())->toBe(1)
        ->and(Zone::where('code', 'SUC9')->count())->toBe(1);
});

it('leaves unmatched ruteros without identification number', function () {
    config(['microsoft.resource' => 'https://dynamics.test']);

    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    Http::fake([
        'https://dynamics.test*' => Http::response(fakeGetRuterosSoap([
            [
                'code' => 'SUC9',
                'zone' => '301',
                'route' => '5678',
                'day' => '2',
                'address' => 'Calle 9',
                'name' => 'Sin Documento',
            ],
        ]), 200),
    ]);

    $summary = app(RuteroZoneSyncService::class)->syncFromRuteros(['301']);

    expect($summary['ruteros_unmatched'])->toBe(1)
        ->and($summary['clients_created'])->toBe(0)
        ->and($summary['zones_created'])->toBe(0)
        ->and(Zone::where('code', 'SUC9')->exists())->toBeFalse();
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
                'document' => '900111111',
            ],
        ]), 200),
    ]);

    $summary = app(RuteroZoneSyncService::class)->syncFromRuteros(['301'], updateClients: true, dryRun: true);

    expect($summary['catalog_routes_seen'])->toBe(1)
        ->and($summary['catalog_routes_created'])->toBe(0)
        ->and($summary['clients_created'])->toBe(1)
        ->and($summary['zones_created'])->toBe(1)
        ->and(ZoneRoute::count())->toBe(0)
        ->and(User::where('document', '900111111')->exists())->toBeFalse()
        ->and(Zone::where('code', 'SUC1')->exists())->toBeFalse();
});
