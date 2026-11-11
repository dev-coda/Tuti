<?php

use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supernumerario', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
});

function clientCardFixture(User $staff, string $zoneCode, string $route): array
{
    $todayDow = Carbon::now('America/Bogota')->dayOfWeek;
    $client = User::factory()->create([
        'name' => 'Cliente Tarjeta',
        'document' => '901234567',
        'business_name' => 'Tienda Tarjeta',
        'email' => 'tarjeta@example.com',
    ]);
    $zone = Zone::create([
        'user_id' => $client->id,
        'zone' => $zoneCode,
        'route' => $route,
        'day' => (string) $todayDow,
        'address' => 'Calle 1 # 2-3',
        'code' => 'RUT-CARD',
    ]);

    return [$client, $zone];
}

it('shows the three client card actions to a seller in mi ruta', function () {
    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');
    [$client, $zone] = clientCardFixture($seller, '301', 'R100');

    actingAs($seller)->get('/ordenes?tab=mi-ruta&ruta=R100')
        ->assertOk()
        ->assertSee('Cliente Tarjeta')
        ->assertSee('Actualizar datos')
        ->assertSee('Agregar sucursal')
        ->assertSee('Crear pedido')
        ->assertSee('Pedidos anteriores');
});

it('shows the three client card actions to a supernumerario in mi ruta', function () {
    $staff = User::factory()->create(['zone' => null]);
    $staff->assignRole('supernumerario');
    [$client, $zone] = clientCardFixture($staff, '301', 'R100');

    actingAs($staff)->get('/ordenes?tab=mi-ruta&zona=301&ruta=R100')
        ->assertOk()
        ->assertSee('Cliente Tarjeta')
        ->assertSee('Actualizar datos')
        ->assertSee('Agregar sucursal')
        ->assertSee('Crear pedido');
});

it('lets a seller open actualizar datos and agregar sucursal for the card client', function () {
    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');
    [$client, $zone] = clientCardFixture($seller, '301', 'R100');

    actingAs($seller)->get(route('client-data-updates.edit', $zone))
        ->assertOk()
        ->assertSee('Actualizar datos');

    actingAs($seller)->get(route('new-client.create', ['mode' => 'sucursal', 'document' => '901234567', 'return' => 'mi-ruta']))
        ->assertOk()
        ->assertSee('Agregar sucursal')
        ->assertSee('901234567');
});

it('lets a supernumerario open actualizar datos and agregar sucursal for any-zone client', function () {
    $staff = User::factory()->create(['zone' => null]);
    $staff->assignRole('supernumerario');
    [$client, $zone] = clientCardFixture($staff, '301', 'R100');

    actingAs($staff)->get(route('client-data-updates.edit', $zone))
        ->assertOk()
        ->assertSee('Actualizar datos');

    actingAs($staff)->get(route('new-client.create', ['mode' => 'sucursal', 'document' => '901234567', 'return' => 'mi-ruta']))
        ->assertOk()
        ->assertSee('Agregar sucursal')
        ->assertSee('901234567');

    actingAs($staff)->get(route('new-client.existing-client', ['document' => '901234567']))
        ->assertOk()
        ->assertJsonPath('found', true);
});

it('shows the three actions to a single-zone supernumerario and lets him open them', function () {
    $staff = User::factory()->create(['zone' => '301', 'covers_all_zones' => false]);
    $staff->assignRole('supernumerario');
    [$client, $zone] = clientCardFixture($staff, '301', 'R100');

    actingAs($staff)->get('/ordenes?tab=mi-ruta&ruta=R100')
        ->assertOk()
        ->assertSee('Actualizar datos')
        ->assertSee('Agregar sucursal')
        ->assertSee('Crear pedido');

    actingAs($staff)->get(route('client-data-updates.edit', $zone))
        ->assertOk();
});

it('offers a zone picker to an all-zones supernumerario adding a sucursal', function () {
    $staff = User::factory()->create(['zone' => null]);
    $staff->assignRole('supernumerario');
    clientCardFixture($staff, '301', 'R100');
    \App\Models\ZoneRoute::create(['zone' => '301', 'route' => '1001']);

    actingAs($staff)->get(route('new-client.create', ['mode' => 'sucursal', 'document' => '901234567', 'return' => 'mi-ruta']))
        ->assertOk()
        ->assertSee('Seleccione zona...')
        ->assertSee('301');
});

it('lets seller and supernumerario hit crear pedido (setclient) route', function () {
    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');
    clientCardFixture($seller, '301', 'R100');

    // Validation-level proof the route is reachable for both staff roles;
    // a full setclient POST exercises external rutero reconciliation.
    actingAs($seller)->post(route('seller.setclient'), [])
        ->assertSessionHasErrors(['document']);

    $staff = User::factory()->create(['zone' => null]);
    $staff->assignRole('supernumerario');
    actingAs($staff)->post(route('seller.setclient'), [])
        ->assertSessionHasErrors(['document']);
});
