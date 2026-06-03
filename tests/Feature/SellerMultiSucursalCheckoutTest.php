<?php

use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Repositories\OrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/**
 * @return array{ seller: User, client: User, product: Product, zones: array<int, Zone> }
 */
function createSellerAndClientWithProductAndThreeZones(): array
{
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $seller = User::factory()->create();
    $seller->assignRole('seller');

    // No document: skip rutero HTTP sync in CartController::processOrder
    $client = User::factory()->create(['document' => null]);

    $tax = Tax::create(['name' => 'IVA-SELL-SUC-'.uniqid(), 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'V-SELL-'.uniqid(),
        'slug' => 'v-sell-'.uniqid(),
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'B-SELL-'.uniqid(),
        'slug' => 'b-sell-'.uniqid(),
        'vendor_id' => $vendor->id,
    ]);
    $product = Product::create([
        'name' => 'Product Sucursal Test',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'SKU-SELL-SUC-'.strtoupper(substr(uniqid(), -6)),
        'slug' => 'p-sell-suc-'.uniqid(),
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

    $zones = [];
    for ($i = 1; $i <= 3; $i++) {
        $zones[] = Zone::create([
            'user_id' => $client->id,
            'route' => "R{$i}00",
            'zone' => (string) (300 + $i), // e.g. 301, 302, 303 — no collision with bodega tests
            'day' => "Dia{$i}",
            'address' => "Calle Sucursal {$i} — D{$i}",
            'code' => "SUC{$i}",
        ]);
    }

    Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '0', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'force_delivery_date_enabled'],
        ['name' => 'Force delivery', 'value' => '0', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'min_amount'],
        ['name' => 'Min amount', 'value' => '0', 'show' => false]
    );

    return compact('seller', 'client', 'product', 'zones');
}

it('seller checkout persists selected non-first client sucursal and xml matches that zone', function () {
    $s = createSellerAndClientWithProductAndThreeZones();
    $firstZone = $s['zones'][0];
    $selected = $s['zones'][1];

    expect($firstZone->id)->not->toBe($selected->id);

    session([
        'user_id' => $s['client']->id,
        'cart' => [
            [
                'product_id' => $s['product']->id,
                'quantity' => 1,
                'variation_id' => null,
            ],
        ],
    ]);

    $response = actingAs($s['seller'])->post(route('cart.process'), [
        'zone_id' => (string) $selected->id,
        'sucursal_code' => $selected->code,
        'sucursal_route' => $selected->route,
        'sucursal_zone' => $selected->zone,
        'sucursal_day' => $selected->day,
        'sucursal_address' => $selected->address,
        'delivery_method' => 'tronex',
        'observations' => 'Checkout sucursal',
    ]);

    $response->assertRedirect();

    $order = Order::latest('id')->first();
    expect($order)->not->toBeNull();
    expect($order->zone_id)->toBe($selected->id);
    expect($order->user_id)->toBe($s['client']->id);

    $order->load(['zone', 'products']);
    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false);
    expect($xml)->not->toBeNull();
    expect($xml)->toContain("<dyn:codCustomer>{$selected->code}</dyn:codCustomer>");
    expect($xml)->toContain("<dyn:ruta>{$selected->route}</dyn:ruta>");
    expect($xml)->toContain("<dyn:zona>{$selected->zone}</dyn:zona>");
    expect($xml)->not->toContain("<dyn:codCustomer>{$firstZone->code}</dyn:codCustomer>");
});

it('seller checkout trusts posted zone_id when hidden sucursal fields are stale (first zone)', function () {
    $s = createSellerAndClientWithProductAndThreeZones();
    $firstZone = $s['zones'][0];
    $selected = $s['zones'][2];

    session([
        'user_id' => $s['client']->id,
        'zone_id' => $firstZone->id, // stale (simulates another client or prior selection)
        'cart' => [
            [
                'product_id' => $s['product']->id,
                'quantity' => 1,
                'variation_id' => null,
            ],
        ],
    ]);

    $response = actingAs($s['seller'])->post(route('cart.process'), [
        'zone_id' => (string) $selected->id,
        // Stale: still the first branch fields (mismatch with zone_id on purpose)
        'sucursal_code' => $firstZone->code,
        'sucursal_route' => $firstZone->route,
        'sucursal_zone' => $firstZone->zone,
        'sucursal_day' => $firstZone->day,
        'sucursal_address' => $firstZone->address,
        'delivery_method' => 'tronex',
    ]);

    $response->assertRedirect();
    $order = Order::latest('id')->first();
    expect($order)->not->toBeNull();
    expect($order->zone_id)->toBe($selected->id);
});

it('checkout selects the sucursal by stable sucursal_uid even when posted zone_id is stale', function () {
    $s = createSellerAndClientWithProductAndThreeZones();
    $selected = $s['zones'][2];
    $staleZoneId = $s['zones'][0]->id;

    expect($selected->sucursal_uid)->toBe('cust:'.$selected->code);

    session([
        'user_id' => $s['client']->id,
        'cart' => [
            [
                'product_id' => $s['product']->id,
                'quantity' => 1,
                'variation_id' => null,
            ],
        ],
    ]);

    $response = actingAs($s['seller'])->post(route('cart.process'), [
        // Stale row id (e.g. cached page / churned ids), but the stable identity is correct.
        'zone_id' => (string) $staleZoneId,
        'sucursal_uid' => $selected->sucursal_uid,
        'delivery_method' => 'tronex',
    ]);

    $response->assertRedirect();
    $order = Order::latest('id')->first();
    expect($order)->not->toBeNull();
    expect($order->zone_id)->toBe($selected->id);
    expect($order->zone_snapshot['zone'] ?? null)->toBe($selected->zone);
});

it('falls back to posted zone_id when sucursal_uid is empty (pre-backfill / cached page)', function () {
    $s = createSellerAndClientWithProductAndThreeZones();
    $selected = $s['zones'][1];

    session([
        'user_id' => $s['client']->id,
        'cart' => [
            [
                'product_id' => $s['product']->id,
                'quantity' => 1,
                'variation_id' => null,
            ],
        ],
    ]);

    $response = actingAs($s['seller'])->post(route('cart.process'), [
        'zone_id' => (string) $selected->id,
        'sucursal_uid' => '', // page rendered before backfill: no stable id available
        'delivery_method' => 'tronex',
    ]);

    $response->assertRedirect();
    $order = Order::latest('id')->first();
    expect($order)->not->toBeNull();
    expect($order->zone_id)->toBe($selected->id);
});

it('disambiguates duplicate sucursal_uid using the posted fingerprint', function () {
    $s = createSellerAndClientWithProductAndThreeZones();

    // Two uncoded rows sharing an address collapse to the same address-based identity.
    // (Coded duplicates are impossible: zones has a UNIQUE(user_id, code) constraint.)
    $dupA = Zone::create([
        'user_id' => $s['client']->id,
        'route' => 'RDUP-A', 'zone' => '777', 'day' => 'DiaA',
        'address' => 'Calle Dup Compartida', 'code' => null,
    ]);
    $dupB = Zone::create([
        'user_id' => $s['client']->id,
        'route' => 'RDUP-B', 'zone' => '778', 'day' => 'DiaB',
        'address' => 'Calle Dup Compartida', 'code' => null,
    ]);
    expect($dupA->sucursal_uid)->toBe($dupB->sucursal_uid);

    session([
        'user_id' => $s['client']->id,
        'cart' => [
            [
                'product_id' => $s['product']->id,
                'quantity' => 1,
                'variation_id' => null,
            ],
        ],
    ]);

    $response = actingAs($s['seller'])->post(route('cart.process'), [
        'zone_id' => (string) $dupA->id, // ambiguous; fingerprint should decide
        'sucursal_uid' => $dupB->sucursal_uid,
        'sucursal_route' => $dupB->route,
        'sucursal_zone' => $dupB->zone,
        'sucursal_day' => $dupB->day,
        'sucursal_address' => $dupB->address,
        'delivery_method' => 'tronex',
    ]);

    $response->assertRedirect();
    $order = Order::latest('id')->first();
    expect($order)->not->toBeNull();
    expect($order->zone_id)->toBe($dupB->id);
});

it('xml keeps checkout sucursal when zone row changes after order creation', function () {
    $s = createSellerAndClientWithProductAndThreeZones();
    $selected = $s['zones'][1];
    $expectedCode = $selected->code;
    $expectedRoute = $selected->route;
    $expectedZone = $selected->zone;

    session([
        'user_id' => $s['client']->id,
        'cart' => [
            [
                'product_id' => $s['product']->id,
                'quantity' => 1,
                'variation_id' => null,
            ],
        ],
    ]);

    $response = actingAs($s['seller'])->post(route('cart.process'), [
        'zone_id' => (string) $selected->id,
        'sucursal_code' => $selected->code,
        'sucursal_route' => $selected->route,
        'sucursal_zone' => $selected->zone,
        'sucursal_day' => $selected->day,
        'sucursal_address' => $selected->address,
        'delivery_method' => 'tronex',
    ]);

    $response->assertRedirect();
    $order = Order::latest('id')->first();
    expect($order)->not->toBeNull();
    expect($order->zone_id)->toBe($selected->id);
    expect($order->zone_snapshot)->toBeArray();
    expect($order->zone_snapshot['code'] ?? null)->toBe($expectedCode);
    expect($order->zone_snapshot['route'] ?? null)->toBe($expectedRoute);
    expect($order->zone_snapshot['zone'] ?? null)->toBe($expectedZone);

    // Simulate later rutero sync remapping the same zone row to another sucursal.
    $selected->update([
        'code' => 'CHANGED-CODE',
        'route' => 'CHANGED-ROUTE',
        'zone' => '999',
        'day' => 'CHANGED-DAY',
        'address' => 'CHANGED-ADDRESS',
    ]);

    $order->refresh();
    $order->load(['zone', 'products']);
    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false);

    expect($xml)->toContain("<dyn:codCustomer>{$expectedCode}</dyn:codCustomer>");
    expect($xml)->toContain("<dyn:ruta>{$expectedRoute}</dyn:ruta>");
    expect($xml)->toContain("<dyn:zona>{$expectedZone}</dyn:zona>");
    expect($xml)->not->toContain('<dyn:codCustomer>CHANGED-CODE</dyn:codCustomer>');
});

it('multi-zone user does not fall back to session zone_id when request omits zone_id', function () {
    $s = createSellerAndClientWithProductAndThreeZones();
    $first = $s['zones'][0];

    session([
        'user_id' => $s['client']->id,
        'zone_id' => $first->id, // would wrongly pick first if we still used session fallback
        'cart' => [
            [
                'product_id' => $s['product']->id,
                'quantity' => 1,
                'variation_id' => null,
            ],
        ],
    ]);

    $second = $s['zones'][1];
    $response = actingAs($s['seller'])->post(route('cart.process'), [
        // no zone_id — but full fingerprint for second
        'sucursal_code' => $second->code,
        'sucursal_route' => $second->route,
        'sucursal_zone' => $second->zone,
        'sucursal_day' => $second->day,
        'sucursal_address' => $second->address,
        'delivery_method' => 'tronex',
    ]);

    $response->assertRedirect();
    $order = Order::latest('id')->first();
    expect($order->zone_id)->toBe($second->id);
});
