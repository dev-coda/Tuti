<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
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
        ->assertSee('Mi Ruta')
        ->assertDontSee('data-tab-trigger="addresses"', false);
});

it('hides mi ruta and direcciones tabs from supervisor mi cuenta', function () {
    $supervisor = User::factory()->create();
    $supervisor->assignRole('supervisor');

    actingAs($supervisor)
        ->get(route('clients.orders.index'))
        ->assertOk()
        ->assertSee('data-tab-trigger="mis-rutas"', false)
        ->assertDontSee('data-tab-trigger="mi-ruta"', false)
        ->assertDontSee('data-tab-trigger="addresses"', false);
});
