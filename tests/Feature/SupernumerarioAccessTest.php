<?php

use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supernumerario', 'guard_name' => 'web']);
});

it('blocks supernumerario from the admin zone', function () {
    $user = User::factory()->create();
    $user->assignRole('supernumerario');

    actingAs($user);

    get(route('dashboard'))->assertStatus(302);
    get(route('users.index'))->assertStatus(302);
    get(route('supernumerarios.index'))->assertStatus(302);
});

it('allows supernumerario to use seller setclient route', function () {
    $user = User::factory()->create();
    $user->assignRole('supernumerario');

    actingAs($user);

    $this->post(route('seller.setclient'), [])
        ->assertSessionHasErrors(['document']);
});

it('shows seller mi cuenta tabs and every zone in mi ruta', function () {
    $staff = User::factory()->create(['zone' => null]);
    $staff->assignRole('supernumerario');

    $todayDow = Carbon::now('America/Bogota')->dayOfWeek;

    $clientA = User::factory()->create(['name' => 'Cliente Zona 101']);
    Zone::create([
        'user_id' => $clientA->id,
        'zone' => '101',
        'route' => 'R101',
        'day' => (string) $todayDow,
        'address' => 'Calle 1',
        'code' => 'C101',
    ]);

    $clientB = User::factory()->create(['name' => 'Cliente Zona 202']);
    Zone::create([
        'user_id' => $clientB->id,
        'zone' => '202',
        'route' => 'R202',
        'day' => (string) $todayDow,
        'address' => 'Calle 2',
        'code' => 'C202',
    ]);

    actingAs($staff)->get(route('clients.orders.index', ['tab' => 'mi-ruta']))
        ->assertOk()
        ->assertSee('data-tab-trigger="mi-ruta"', false)
        ->assertSee('data-tab-trigger="orders-today"', false)
        ->assertDontSee('data-tab-trigger="addresses"', false)
        ->assertSee('101')
        ->assertSee('202')
        ->assertSee('Selecciona una zona');

    actingAs($staff)->get(route('clients.orders.index', ['tab' => 'mi-ruta', 'zona' => '101']))
        ->assertOk()
        ->assertSee('R101')
        ->assertDontSee('R202');
});

it('lets supernumerario see orders from every zone', function () {
    $staff = User::factory()->create();
    $staff->assignRole('supernumerario');

    $clientA = User::factory()->create(['name' => 'Cliente Visible A']);
    $zoneA = $clientA->zones()->create([
        'zone' => '101',
        'route' => '0001',
        'day' => 'Lunes',
        'address' => 'Calle 1',
        'code' => 'C101',
    ]);

    $clientB = User::factory()->create(['name' => 'Cliente Visible B']);
    $zoneB = $clientB->zones()->create([
        'zone' => '909',
        'route' => '0099',
        'day' => 'Martes',
        'address' => 'Calle 9',
        'code' => 'C909',
    ]);

    Order::create([
        'user_id' => $clientA->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 1500,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zoneA->id,
        'zone_snapshot' => ['zone' => '101', 'route' => '0001', 'code' => 'C101'],
    ]);

    Order::create([
        'user_id' => $clientB->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 2200,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zoneB->id,
        'zone_snapshot' => ['zone' => '909', 'route' => '0099', 'code' => 'C909'],
    ]);

    actingAs($staff)->get(route('clients.orders.index', ['tab' => 'orders']))
        ->assertOk()
        ->assertSee('Cliente Visible A')
        ->assertSee('Cliente Visible B');
});

it('keeps regular sellers limited to their assigned zone in mi ruta', function () {
    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    $clientHome = User::factory()->create(['name' => 'Cliente Zona Propia']);
    Zone::create([
        'user_id' => $clientHome->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => '1',
        'address' => 'Calle 1',
        'code' => 'RUT-1',
    ]);

    $clientOther = User::factory()->create(['name' => 'Cliente Otra Zona']);
    Zone::create([
        'user_id' => $clientOther->id,
        'zone' => '999',
        'route' => 'R900',
        'day' => '1',
        'address' => 'Calle 9',
        'code' => 'RUT-9',
    ]);

    actingAs($seller)->get(route('clients.orders.index', ['tab' => 'mi-ruta']))
        ->assertOk()
        ->assertSee('R100')
        ->assertDontSee('R900')
        ->assertDontSee('Selecciona una zona');
});
