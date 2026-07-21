<?php

use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
});

function makeOriginOrder(User $client, ?User $seller = null, float $total = 1000, ?Zone $zone = null): Order
{
    return Order::create([
        'user_id' => $client->id,
        'seller_id' => $seller?->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => $total,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zone?->id,
        'zone_snapshot' => $zone ? [
            'id' => $zone->id,
            'code' => $zone->code,
            'route' => $zone->route,
            'zone' => $zone->zone,
            'day' => $zone->day,
            'address' => $zone->address,
        ] : null,
    ]);
}

function makeClientZone(User $client, string $zone, string $route = '0001'): Zone
{
    return $client->zones()->create([
        'zone' => $zone,
        'route' => $route,
        'day' => 'Lunes',
        'address' => 'Calle 1 # 2-3',
        'code' => 'C' . $client->id,
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

it('shows autonomous client orders from the seller zona on the seller mi cuenta page', function () {
    $seller = User::factory()->create(['zone' => '933']);
    $seller->assignRole('seller');

    $client = User::factory()->create();
    $zone = makeClientZone($client, '933');

    // Placed by the client alone (no seller_id) in the seller's zona.
    makeOriginOrder($client, null, 1000, $zone);

    actingAs($seller);

    get(route('clients.orders.index'))
        ->assertOk()
        ->assertSee('Autónomo');
});

it('does not show orders from other zonas to the seller', function () {
    $seller = User::factory()->create(['zone' => '933']);
    $seller->assignRole('seller');

    $client = User::factory()->create(['name' => 'Cliente Zona Ajena']);
    $zone = makeClientZone($client, '750');

    makeOriginOrder($client, null, 1000, $zone);

    actingAs($seller);

    get(route('clients.orders.index'))
        ->assertOk()
        ->assertDontSee('Cliente Zona Ajena');
});

it('falls back to the zone snapshot when the zone row was pruned', function () {
    $seller = User::factory()->create(['zone' => '933']);
    $seller->assignRole('seller');

    $client = User::factory()->create(['name' => 'Cliente Snapshot']);
    $zone = makeClientZone($client, '933');
    $order = makeOriginOrder($client, null, 1000, $zone);

    // Rutero sync can prune zone rows; the snapshot keeps the order visible.
    $order->update(['zone_id' => null]);
    $zone->delete();

    actingAs($seller);

    get(route('clients.orders.index'))
        ->assertOk()
        ->assertSee('Cliente Snapshot')
        ->assertSee('Autónomo');
});

it('lets the seller open the detail of an autonomous order in their zona', function () {
    $seller = User::factory()->create(['zone' => '933']);
    $seller->assignRole('seller');

    $client = User::factory()->create();
    $zone = makeClientZone($client, '933');
    $order = makeOriginOrder($client, null, 1000, $zone);

    actingAs($seller);

    get(route('clients.orders.show', $order))
        ->assertOk()
        ->assertSee('Pedido');
});
