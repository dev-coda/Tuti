<?php

use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Jobs\ProcessOrderAsync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    config(['microsoft.resource' => 'https://dynamics.test']);

    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft token', 'value' => 'test-token', 'show' => false]
    );
});

it('allows admin to sync rutero by document and promote pending client', function () {
    Http::fake([
        'https://dynamics.test*' => Http::response(fakeGetRuterosSoap([
            [
                'code' => 'CUST-1110286609',
                'zone' => '933',
                'route' => '1234',
                'day' => 'LUNES',
                'address' => 'Calle 1 # 2-3',
                'name' => 'Cliente Rutero Real',
            ],
        ])),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $client = User::factory()->create([
        'document' => '1110286609',
        'name' => 'Prospecto 1110286609',
        'client_status' => User::CLIENT_STATUS_PENDIENTE,
        'status_id' => User::PENDING,
    ]);

    Zone::create([
        'user_id' => $client->id,
        'route' => '',
        'zone' => '933',
        'day' => '',
        'address' => 'Pendiente de sincronización rutero',
        'code' => null,
    ]);

    $this->actingAs($admin)
        ->post(route('users.sync-rutero-by-document'), ['document' => '1110286609'])
        ->assertRedirect(route('users.edit', $client))
        ->assertSessionHas('success');

    $client->refresh();

    expect($client->client_status)->toBe(User::CLIENT_STATUS_CLIENTE)
        ->and($client->status_id)->toBe(User::ACTIVE)
        ->and($client->name)->toBe('Cliente Rutero Real');
});

it('allows admin to sync rutero from client edit page', function () {
    Http::fake([
        'https://dynamics.test*' => Http::response(fakeGetRuterosSoap([
            [
                'code' => 'CUST-900999888',
                'zone' => '933',
                'route' => '5678',
                'day' => 'MARTES',
                'address' => 'Carrera 10',
                'name' => 'Cliente Edit Sync',
            ],
        ])),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $client = User::factory()->create([
        'document' => '900999888',
        'client_status' => User::CLIENT_STATUS_PENDIENTE,
        'status_id' => User::PENDING,
    ]);

    $this->actingAs($admin)
        ->post(route('users.sync-rutero', $client))
        ->assertRedirect()
        ->assertSessionHas('success');

    $client->refresh();

    expect($client->client_status)->toBe(User::CLIENT_STATUS_CLIENTE)
        ->and($client->zones()->where('code', 'CUST-900999888')->exists())->toBeTrue();
});

it('returns error when document is not found locally', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->post(route('users.sync-rutero-by-document'), ['document' => '000000000'])
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('transmits draft orders after manual rutero sync promotes client', function () {
    Bus::fake([ProcessOrderAsync::class]);

    Http::fake([
        'https://dynamics.test*' => Http::response(fakeGetRuterosSoap([
            [
                'code' => 'CUST-DRAFT-1',
                'zone' => '933',
                'route' => '1234',
                'day' => 'LUNES',
                'address' => 'Calle Draft',
                'name' => 'Cliente Draft Sync',
            ],
        ])),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $client = User::factory()->create([
        'document' => '800444555',
        'client_status' => User::CLIENT_STATUS_PENDIENTE,
        'status_id' => User::PENDING,
    ]);

    $zone = Zone::create([
        'user_id' => $client->id,
        'route' => '1234',
        'zone' => '933',
        'day' => 'LUNES',
        'address' => 'Calle Draft',
        'code' => null,
    ]);

    $tax = Tax::create(['name' => 'IVA-ADMIN-'.uniqid(), 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'V-ADMIN-'.uniqid(),
        'slug' => 'v-admin-'.uniqid(),
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'B-ADMIN-'.uniqid(),
        'slug' => 'b-admin-'.uniqid(),
        'vendor_id' => $vendor->id,
    ]);
    $product = Product::create([
        'name' => 'Product Admin Draft',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'SKU-ADMIN-'.strtoupper(substr(uniqid(), -6)),
        'slug' => 'p-admin-'.uniqid(),
        'active' => 1,
        'price' => 10_000,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    $order = Order::create([
        'user_id' => $client->id,
        'total' => 10000,
        'discount' => 0,
        'status_id' => Order::STATUS_DRAFT,
        'zone_id' => $zone->id,
        'zone_snapshot' => [
            'id' => $zone->id,
            'code' => null,
            'route' => $zone->route,
            'zone' => $zone->zone,
            'day' => $zone->day,
            'address' => $zone->address,
        ],
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'shipping_provider' => Order::SHIPPING_PROVIDER_TRONEX,
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 10_000,
        'discount' => 0,
    ]);

    $this->actingAs($admin)
        ->post(route('users.sync-rutero', $client))
        ->assertRedirect()
        ->assertSessionHas('success');

    $client->refresh();
    $order->refresh();

    expect($client->client_status)->toBe(User::CLIENT_STATUS_CLIENTE)
        ->and($order->status_id)->toBe(Order::STATUS_PENDING);

    Bus::assertDispatched(ProcessOrderAsync::class);
});
