<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
});

it('shows the seller report accordion to admins and hides it from clients', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $client = User::factory()->create();
    actingAs($client)
        ->get(route('clients.orders.index'))
        ->assertOk()
        ->assertDontSee('id="seller-dashboard"', false);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    actingAs($admin)
        ->get(route('clients.orders.index'))
        ->assertOk()
        ->assertSee('id="seller-dashboard"', false)
        ->assertSee('id="dash-accordion"', false)
        ->assertSee('con IVA', false);
});

it('hides the mi ruta tab from client mi cuenta', function () {
    $client = User::factory()->create();

    actingAs($client)
        ->get(route('clients.orders.index'))
        ->assertOk()
        ->assertDontSee('data-tab-trigger="mi-ruta"', false)
        ->assertDontSee('data-tab-panel="mi-ruta"', false)
        ->assertSee('data-tab-trigger="addresses"', false)
        ->assertSee('Direcciones');
});

it('hides the direcciones tab from seller mi cuenta', function () {
    $seller = User::factory()->create();
    $seller->assignRole('seller');

    actingAs($seller)
        ->get(route('clients.orders.index'))
        ->assertOk()
        ->assertSee('data-tab-trigger="mi-ruta"', false)
        ->assertSee('data-tab-trigger="orders"', false)
        ->assertDontSee('data-tab-trigger="orders-today"', false)
        ->assertSee('Mi Ruta')
        ->assertSee('Pedidos')
        ->assertDontSee('Pedidos del dia')
        ->assertDontSee('data-tab-trigger="addresses"', false);
});

it('shows mi ruta and mis zonas but not direcciones to supervisor mi cuenta', function () {
    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    actingAs($supervisor)
        ->get(route('clients.orders.index'))
        ->assertOk()
        ->assertSee('data-tab-trigger="mis-rutas"', false)
        ->assertSee('data-tab-trigger="mi-ruta"', false)
        ->assertDontSee('data-tab-trigger="addresses"', false);
});
