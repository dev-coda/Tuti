<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
});

function makeOriginOrder(User $client, ?User $seller = null, float $total = 1000): Order
{
    return Order::create([
        'user_id' => $client->id,
        'seller_id' => $seller?->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => $total,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
    ]);
}

it('derives origin from seller_id', function () {
    $client = User::factory()->create();
    $seller = User::factory()->create();

    $sellerOrder = makeOriginOrder($client, $seller);
    $clientOrder = makeOriginOrder($client);

    expect($sellerOrder->origin)->toBe(Order::ORIGIN_RUTA)
        ->and($sellerOrder->origin_label)->toBe('RUTA')
        ->and($clientOrder->origin)->toBe(Order::ORIGIN_AUTONOMO)
        ->and($clientOrder->origin_label)->toBe('Autónomo');
});

it('shows origin badges in the admin orders table', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $client = User::factory()->create();
    $seller = User::factory()->create();
    $seller->assignRole('seller');

    makeOriginOrder($client, $seller);
    makeOriginOrder($client);

    actingAs($admin);

    get(route('orders.index'))
        ->assertOk()
        ->assertSee('Origen')
        ->assertSee('RUTA')
        ->assertSee('Autónomo');
});

it('shows origin badges on the seller mi cuenta orders page', function () {
    $client = User::factory()->create();
    $seller = User::factory()->create();
    $seller->assignRole('seller');

    makeOriginOrder($client, $seller);

    actingAs($seller);

    get(route('clients.orders.index'))
        ->assertOk()
        ->assertSee('RUTA');
});

it('does not show origin badges to plain clients', function () {
    $client = User::factory()->create();

    makeOriginOrder($client);

    actingAs($client);

    get(route('clients.orders.index'))
        ->assertOk()
        ->assertDontSee('Autónomo');
});
