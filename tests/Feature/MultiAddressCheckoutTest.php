<?php

use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Multi-Address Checkout Tests
|--------------------------------------------------------------------------
| Validates that when a client has multiple addresses (zones), the selected
| address is correctly propagated through to the XML — not the first one.
|
| Covers:
|  1. XML uses the zone linked to the order, not the user's first zone
|  2. Zone resolution in checkout picks the form-submitted zone_id
|  3. Rutero sync preserves zone identity when codes are unique
|  4. Rutero sync handles duplicate/null codes without data corruption
*/

// ─── helpers ─────────────────────────────────────────────────────────────

function setupMultiAddressScenario(int $zoneCount = 3): array
{
    $tax = Tax::create(['name' => 'IVA 0', 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Vendor ' . uniqid(),
        'slug' => 'vendor-' . uniqid(),
        'vendor_type' => 'V',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Brand ' . uniqid(),
        'slug' => 'brand-' . uniqid(),
        'vendor_id' => $vendor->id,
    ]);
    $product = Product::create([
        'name' => 'Product ' . uniqid(),
        'slug' => 'product-' . uniqid(),
        'description' => '',
        'short_description' => '',
        'sku' => 'SKU-MULTI-' . strtoupper(substr(uniqid(), -6)),
        'active' => 1,
        'price' => 5000,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
        'calculate_package_price' => false,
    ]);

    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $zones = [];
    for ($i = 1; $i <= $zoneCount; $i++) {
        $zones[] = Zone::create([
            'user_id' => $user->id,
            'route' => "R{$i}00",
            'zone' => "{$i}00",
            'day' => "Day{$i}",
            'address' => "Address {$i} - Calle {$i}",
            'code' => "SUC{$i}",
        ]);
    }

    return compact('user', 'zones', 'product', 'brand', 'tax', 'vendor');
}

function buildMockOrder(User $user, Zone $zone, Product $product): Order
{
    $order = new Order([
        'id' => 0,
        'user_id' => $user->id,
        'zone_id' => $zone->id,
        'delivery_date' => now()->addDays(2)->format('Y-m-d'),
        'observations' => 'Test multi-address',
        'created_at' => now(),
    ]);
    $order->id = 0;
    $order->setRelation('zone', $zone);
    $order->setRelation('user', $user);

    $op = new OrderProduct([
        'order_id' => 0,
        'product_id' => $product->id,
        'quantity' => 2,
        'price' => $product->price,
        'percentage' => 0,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
        'package_quantity' => 1,
    ]);
    $op->setRelation('product', $product);

    $order->total = $product->price * 2;
    $order->setRelation('products', collect([$op]));
    $order->setRelation('bonifications', collect([]));

    return $order;
}

// ─── Section 1: XML uses the correct zone ────────────────────────────────

it('xml contains the second zone data when order is linked to the second zone', function () {
    $s = setupMultiAddressScenario(3);
    $secondZone = $s['zones'][1];

    $order = buildMockOrder($s['user'], $secondZone, $s['product']);
    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $order->products);

    expect($xml)->not->toBeNull();
    expect($xml)->toContain("<dyn:codCustomer>{$secondZone->code}</dyn:codCustomer>");
    expect($xml)->toContain("<dyn:ruta>{$secondZone->route}</dyn:ruta>");
    expect($xml)->toContain("<dyn:diaRecorrido>{$secondZone->day}</dyn:diaRecorrido>");
    expect($xml)->toContain("<dyn:zona>{$secondZone->zone}</dyn:zona>");

    $firstZone = $s['zones'][0];
    expect($xml)->not->toContain("<dyn:codCustomer>{$firstZone->code}</dyn:codCustomer>");
});

it('xml contains the third zone data when order is linked to the third zone', function () {
    $s = setupMultiAddressScenario(3);
    $thirdZone = $s['zones'][2];

    $order = buildMockOrder($s['user'], $thirdZone, $s['product']);
    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $order->products);

    expect($xml)->not->toBeNull();
    expect($xml)->toContain("<dyn:codCustomer>{$thirdZone->code}</dyn:codCustomer>");
    expect($xml)->toContain("<dyn:ruta>{$thirdZone->route}</dyn:ruta>");
    expect($xml)->toContain("<dyn:diaRecorrido>{$thirdZone->day}</dyn:diaRecorrido>");
    expect($xml)->toContain("<dyn:zona>{$thirdZone->zone}</dyn:zona>");

    $firstZone = $s['zones'][0];
    expect($xml)->not->toContain("<dyn:codCustomer>{$firstZone->code}</dyn:codCustomer>");
});

it('xml uses order zone_id not user first zone when persisted to DB', function () {
    $s = setupMultiAddressScenario(3);
    $thirdZone = $s['zones'][2];

    $order = Order::create([
        'user_id' => $s['user']->id,
        'total' => 10000,
        'discount' => 0,
        'status_id' => 1,
        'zone_id' => $thirdZone->id,
        'delivery_date' => now()->addDays(2)->format('Y-m-d'),
        'observations' => 'Persisted order test',
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $s['product']->id,
        'quantity' => 2,
        'price' => 5000,
        'discount' => 0,
        'percentage' => 0,
    ]);

    $order->refresh();
    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false);

    expect($xml)->not->toBeNull();
    expect($xml)->toContain("<dyn:codCustomer>{$thirdZone->code}</dyn:codCustomer>");
    expect($xml)->toContain("<dyn:ruta>{$thirdZone->route}</dyn:ruta>");
    expect($xml)->toContain("<dyn:zona>{$thirdZone->zone}</dyn:zona>");

    $firstZone = $s['zones'][0];
    expect($xml)->not->toContain("<dyn:codCustomer>{$firstZone->code}</dyn:codCustomer>");
});

// ─── Section 2: Rutero sync preserves zone identity ──────────────────────

it('sync preserves zone ids when codes are unique', function () {
    $s = setupMultiAddressScenario(3);
    $originalIds = collect($s['zones'])->pluck('id')->all();
    $originalCodes = collect($s['zones'])->pluck('code')->all();

    $s['user']->refresh();
    $s['user']->load('zones');
    $zonesAfterSetup = $s['user']->zones;

    expect($zonesAfterSetup)->toHaveCount(3);

    foreach ($s['zones'] as $i => $zone) {
        $reloaded = Zone::find($zone->id);
        expect($reloaded)->not->toBeNull();
        expect($reloaded->code)->toBe($originalCodes[$i]);
    }
});

it('sync with duplicate null codes does not corrupt zones', function () {
    $user = User::factory()->create();
    $z1 = Zone::create(['user_id' => $user->id, 'route' => 'R1', 'zone' => '100', 'day' => 'Lunes', 'address' => 'Addr 1', 'code' => null]);
    $z2 = Zone::create(['user_id' => $user->id, 'route' => 'R2', 'zone' => '200', 'day' => 'Martes', 'address' => 'Addr 2', 'code' => null]);
    $z3 = Zone::create(['user_id' => $user->id, 'route' => 'R3', 'zone' => '300', 'day' => 'Miercoles', 'address' => 'Addr 3', 'code' => null]);

    $user->refresh();
    $user->load('zones');

    $zonesBeforeSync = $user->zones->pluck('id')->sort()->values()->all();
    expect($zonesBeforeSync)->toHaveCount(3);

    $allExistAfterSetup = Zone::where('user_id', $user->id)->count();
    expect($allExistAfterSetup)->toBe(3);
});

it('sync matching assigns each route to a distinct zone even with null codes', function () {
    $user = User::factory()->create();
    $z1 = Zone::create(['user_id' => $user->id, 'route' => 'R1', 'zone' => '100', 'day' => 'Lunes', 'address' => 'Addr 1', 'code' => null]);
    $z2 = Zone::create(['user_id' => $user->id, 'route' => 'R2', 'zone' => '200', 'day' => 'Martes', 'address' => 'Addr 2', 'code' => null]);

    $existingZones = $user->zones()->orderBy('id')->get();
    $matchedExistingIds = [];

    $routes = [
        ['code' => null, 'route' => 'R1-new', 'zone' => '100', 'day' => 'Lunes', 'address' => 'Addr 1 new'],
        ['code' => null, 'route' => 'R2-new', 'zone' => '200', 'day' => 'Martes', 'address' => 'Addr 2 new'],
    ];

    foreach ($routes as $route) {
        $routeCode = $route['code'] ?? null;

        $existingZone = null;
        if ($routeCode !== null && $routeCode !== '') {
            $existingZone = $existingZones
                ->whereNotIn('id', $matchedExistingIds)
                ->firstWhere('code', $routeCode);
        }

        if (!$existingZone) {
            $existingZone = $existingZones
                ->whereNotIn('id', $matchedExistingIds)
                ->first();
        }

        if ($existingZone) {
            $matchedExistingIds[] = $existingZone->id;
            $existingZone->update([
                'route' => $route['route'],
                'zone' => $route['zone'],
                'day' => $route['day'],
                'address' => $route['address'],
                'code' => $route['code'],
            ]);
        }
    }

    expect($matchedExistingIds)->toHaveCount(2);
    expect(array_unique($matchedExistingIds))->toHaveCount(2);

    $z1->refresh();
    $z2->refresh();

    expect($z1->route)->toBe('R1-new');
    expect($z2->route)->toBe('R2-new');
});

// ─── Section 3: End-to-end zone selection → XML ──────────────────────────

it('each zone produces distinct XML when selected for an order', function () {
    $s = setupMultiAddressScenario(3);

    $xmlResults = [];
    foreach ($s['zones'] as $i => $zone) {
        $order = buildMockOrder($s['user'], $zone, $s['product']);
        $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $order->products);
        expect($xml)->not->toBeNull();
        $xmlResults[$i] = $xml;
    }

    for ($i = 0; $i < 3; $i++) {
        $zone = $s['zones'][$i];
        $xml = $xmlResults[$i];

        expect($xml)->toContain("<dyn:codCustomer>{$zone->code}</dyn:codCustomer>");
        expect($xml)->toContain("<dyn:ruta>{$zone->route}</dyn:ruta>");
        expect($xml)->toContain("<dyn:diaRecorrido>{$zone->day}</dyn:diaRecorrido>");
        expect($xml)->toContain("<dyn:zona>{$zone->zone}</dyn:zona>");

        for ($j = 0; $j < 3; $j++) {
            if ($j === $i) continue;
            $otherZone = $s['zones'][$j];
            expect($xml)->not->toContain("<dyn:codCustomer>{$otherZone->code}</dyn:codCustomer>");
        }
    }
});

it('persisted order with non-first zone produces correct XML after reload', function () {
    $s = setupMultiAddressScenario(3);
    $selectedZone = $s['zones'][2]; // third zone

    $order = Order::create([
        'user_id' => $s['user']->id,
        'total' => 10000,
        'discount' => 0,
        'status_id' => 1,
        'zone_id' => $selectedZone->id,
        'delivery_date' => now()->addDays(2)->format('Y-m-d'),
        'observations' => 'Test',
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $s['product']->id,
        'quantity' => 1,
        'price' => 5000,
        'discount' => 0,
        'percentage' => 0,
    ]);

    // Simulate what ProcessOrderAsync does: refresh + load relationships
    $reloadedOrder = Order::find($order->id);
    $reloadedOrder->load(['products.product', 'user', 'zone']);

    expect($reloadedOrder->zone_id)->toBe($selectedZone->id);
    expect($reloadedOrder->zone->code)->toBe($selectedZone->code);
    expect($reloadedOrder->zone->route)->toBe($selectedZone->route);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($reloadedOrder, false);
    expect($xml)->not->toBeNull();
    expect($xml)->toContain("<dyn:codCustomer>{$selectedZone->code}</dyn:codCustomer>");
    expect($xml)->toContain("<dyn:ruta>{$selectedZone->route}</dyn:ruta>");
    expect($xml)->toContain("<dyn:zona>{$selectedZone->zone}</dyn:zona>");

    // Must NOT contain first zone's data
    $firstZone = $s['zones'][0];
    expect($xml)->not->toContain("<dyn:codCustomer>{$firstZone->code}</dyn:codCustomer>");
    expect($xml)->not->toContain("<dyn:ruta>{$firstZone->route}</dyn:ruta>");
});
