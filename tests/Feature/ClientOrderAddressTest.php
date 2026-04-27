<?php

use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows the delivery address used by the order instead of the client first address', function () {
    $client = User::factory()->create();

    $firstZone = Zone::create([
        'user_id' => $client->id,
        'route' => 'R-FIRST',
        'zone' => '101',
        'day' => 'Monday',
        'address' => 'FIRST ADDRESS SHOULD NOT SHOW',
        'code' => 'FIRST',
    ]);

    $selectedZone = Zone::create([
        'user_id' => $client->id,
        'route' => 'R-SELECTED',
        'zone' => '202',
        'day' => 'Tuesday',
        'address' => 'SELECTED ORDER ADDRESS',
        'code' => 'SELECTED',
    ]);

    $order = Order::create([
        'user_id' => $client->id,
        'zone_id' => $selectedZone->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 1000,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
    ]);

    actingAs($client)
        ->get(route('clients.orders.show', $order))
        ->assertOk()
        ->assertSee('SELECTED ORDER ADDRESS')
        ->assertSee('202')
        ->assertSee('R-SELECTED')
        ->assertSee('SELECTED')
        ->assertDontSee('FIRST ADDRESS SHOULD NOT SHOW')
        ->assertDontSee('R-FIRST');

    expect($firstZone->id)->not->toBe($selectedZone->id);
});
