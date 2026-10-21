<?php

use App\Models\City;
use App\Models\DepartmentPlaceholderZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\State;
use App\Models\User;
use App\Models\Zone;
use App\Models\Brand;
use App\Models\Tax;
use App\Models\Vendor;
use App\Models\ShippingMethod;
use App\Services\DepartmentPlaceholderZoneService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function departmentPlaceholderFixtures(): array
{
    $state = State::create(['name' => 'Antioquia']);
    $medellin = City::create([
        'name' => 'Medellín',
        'state_id' => $state->id,
        'active' => true,
        'is_preferred' => true,
    ]);
    $bello = City::create([
        'name' => 'Bello',
        'state_id' => $state->id,
        'active' => true,
        'is_preferred' => false,
    ]);

    $placeholder = DepartmentPlaceholderZone::create([
        'state_id' => $state->id,
        'city_id' => $medellin->id,
        'zone' => '102',
        'route' => '1021',
        'day' => '1',
        'dane_code' => '05001000',
        'address' => 'Zona placeholder — Medellín',
        'enabled' => true,
    ]);

    return compact('state', 'medellin', 'bello', 'placeholder');
}

it('resolves the department placeholder from the client city even when it is not the capital', function () {
    ['bello' => $bello, 'placeholder' => $placeholder] = departmentPlaceholderFixtures();
    $client = User::factory()->create(['city_id' => $bello->id]);

    $resolved = app(DepartmentPlaceholderZoneService::class)->resolveForUser($client);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($placeholder->id)
        ->and($resolved->zone)->toBe('102');
});

it('attaches a placeholder sucursal at checkout when the client has no zone', function () {
    Setting::updateOrCreate(['key' => 'inventory_enabled'], ['name' => 'inv', 'value' => '0', 'show' => false]);
    Setting::updateOrCreate(['key' => 'min_amount'], ['name' => 'min', 'value' => '0', 'show' => false]);

    ['bello' => $bello] = departmentPlaceholderFixtures();
    $client = User::factory()->create(['city_id' => $bello->id]);

    $tax = Tax::create(['name' => 'IVA ph', 'tax' => 0]);
    $vendor = Vendor::create(['name' => 'V ph', 'slug' => 'v-ph', 'minimum_purchase' => 0, 'active' => 1]);
    $brand = Brand::create(['name' => 'B ph', 'slug' => 'b-ph', 'vendor_id' => $vendor->id]);
    $product = Product::create([
        'name' => 'PH product',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'PH-1',
        'slug' => 'ph-1',
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

    expect($client->zones()->count())->toBe(0);

    actingAs($client)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
            ],
        ])
        ->get(route('cart'))
        ->assertOk()
        ->assertDontSee('zona temporal por ciudad cabecera')
        ->assertDontSee('ciudad cabecera del departamento');

    $zone = $client->fresh()->zones()->first();
    expect($zone)->not->toBeNull()
        ->and($zone->is_placeholder)->toBeTrue()
        ->and($zone->zone)->toBe('102')
        ->and($zone->route)->toBe('1021')
        ->and($zone->dane_code)->toBe('05001000');

    actingAs($client)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
            ],
        ])
        ->post(route('cart.process'), [
            'zone_id' => $zone->id,
            'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
            'observations' => '',
        ])
        ->assertSessionMissing('error');

    $order = Order::query()->first();
    expect($order)->not->toBeNull()
        ->and($order->zone_id)->toBe($zone->id)
        ->and($order->zone_snapshot['zone'] ?? null)->toBe('102')
        ->and($order->zone_snapshot['route'] ?? null)->toBe('1021');
});

it('does not replace a real sucursal with the department placeholder', function () {
    ['bello' => $bello] = departmentPlaceholderFixtures();
    $client = User::factory()->create(['city_id' => $bello->id]);
    $real = Zone::create([
        'user_id' => $client->id,
        'route' => '9999',
        'zone' => '933',
        'day' => '5',
        'address' => 'Sucursal real',
        'code' => 'REAL-1',
        'is_placeholder' => false,
    ]);

    $resolved = app(DepartmentPlaceholderZoneService::class)->ensureZoneForUser($client->fresh('zones'));

    expect($resolved->id)->toBe($real->id)
        ->and($client->fresh()->zones()->count())->toBe(1);
});

it('does not attach a placeholder until zone and route are configured', function () {
    ['bello' => $bello, 'placeholder' => $placeholder] = departmentPlaceholderFixtures();
    $placeholder->update(['zone' => null, 'route' => null]);
    $client = User::factory()->create(['city_id' => $bello->id]);

    expect(app(DepartmentPlaceholderZoneService::class)->ensureZoneForUser($client))->toBeNull()
        ->and($client->fresh()->zones()->count())->toBe(0);
});

it('saves the master placeholder list from admin', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    ['placeholder' => $placeholder, 'medellin' => $medellin] = departmentPlaceholderFixtures();

    actingAs($admin)
        ->put(route('department-placeholder-zones.update'), [
            'rows' => [
                [
                    'id' => $placeholder->id,
                    'zone' => '110',
                    'route' => '1100',
                    'day' => '2',
                    'dane_code' => '05001000',
                    'address' => 'Cabecera Medellín',
                    'enabled' => '1',
                ],
            ],
        ])
        ->assertRedirect();

    $placeholder->refresh();
    expect($placeholder->zone)->toBe('110')
        ->and($placeholder->route)->toBe('1100')
        ->and($placeholder->address)->toBe('Cabecera Medellín');

    actingAs($admin)
        ->get(route('department-placeholder-zones.index'))
        ->assertOk()
        ->assertSee('Zonas placeholder por departamento')
        ->assertSee($medellin->name);
});

it('fills cabecera zone and route defaults so the catalog is ready without admin edits', function () {
    $state = State::create(['name' => 'Antioquia']);
    $medellin = City::create([
        'name' => 'Medellín',
        'state_id' => $state->id,
        'active' => true,
        'is_preferred' => true,
    ]);

    $service = app(DepartmentPlaceholderZoneService::class);
    $service->syncCatalogFromPreferredCities();
    $row = $service->ensureCatalogForCity($medellin);

    expect($row->isReady())->toBeTrue()
        ->and($row->zone)->toBe('102')
        ->and($row->route)->toBe('1020')
        ->and($row->dane_code)->toBe('05001000');
});

it('completes express checkout on a placeholder sucursal when Coordinadora quote fails', function () {
    Setting::updateOrCreate(['key' => 'inventory_enabled'], ['name' => 'inv', 'value' => '1', 'show' => false]);
    Setting::updateOrCreate(['key' => 'min_amount'], ['name' => 'min', 'value' => '0', 'show' => false]);
    Setting::updateOrCreate(['key' => 'express_48h_enabled'], ['name' => '48h', 'value' => '1', 'show' => false]);
    Cache::forget('setting_express_48h_enabled');

    ['bello' => $bello, 'medellin' => $medellin, 'placeholder' => $placeholder] = departmentPlaceholderFixtures();
    $express = ShippingMethod::query()->where('code', 'express')->firstOrFail();
    $express->update(['enabled' => true, 'restrict_cities' => true]);
    $express->cities()->sync([$bello->id => ['enabled' => true], $medellin->id => ['enabled' => true]]);
    $bello->update(['force_delivery_date_enabled' => false]);

    $client = User::factory()->create(['city_id' => $bello->id]);
    $tax = Tax::create(['name' => 'IVA ph express', 'tax' => 0]);
    $vendor = Vendor::create(['name' => 'V ph express', 'slug' => 'v-ph-ex', 'minimum_purchase' => 0, 'active' => 1]);
    $brand = Brand::create(['name' => 'B ph express', 'slug' => 'b-ph-ex', 'vendor_id' => $vendor->id]);
    $product = Product::create([
        'name' => 'PH express product',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'PH-EX-1',
        'slug' => 'ph-ex-1',
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
        'coordinadora_weight_kg' => 0.5,
        'coordinadora_height_cm' => 10,
        'coordinadora_width_cm' => 8,
        'coordinadora_length_cm' => 12,
    ]);

    Http::fake([
        '*' => Http::response(['isError' => true, 'message' => 'down'], 500),
    ]);

    actingAs($client)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
            ],
        ])
        ->get(route('cart'))
        ->assertOk()
        ->assertDontSee('zona temporal por ciudad cabecera')
        ->assertDontSee('ciudad cabecera del departamento');

    $zone = $client->fresh()->zones()->first();
    expect($zone)->not->toBeNull()->and($zone->isPlaceholder())->toBeTrue();

    actingAs($client)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
            ],
        ])
        ->getJson('/api/shipping-quote/express?zone_id='.$zone->id)
        ->assertOk()
        ->assertJson(['success' => true, 'fallback' => true, 'shipping_cost' => 0]);

    actingAs($client)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
            ],
        ])
        ->post(route('cart.process'), [
            'zone_id' => $zone->id,
            'delivery_method' => Order::DELIVERY_METHOD_EXPRESS,
            'observations' => '',
        ])
        ->assertSessionMissing('error');

    $order = Order::query()->first();
    expect($order)->not->toBeNull()
        ->and($order->delivery_method)->toBe(Order::DELIVERY_METHOD_EXPRESS)
        ->and($order->shipping_provider)->toBe(Order::SHIPPING_PROVIDER_COORDINADORA)
        ->and($order->zone_id)->toBe($zone->id);
});

it('prepares the express city pilot from the artisan command', function () {
    ['medellin' => $medellin] = departmentPlaceholderFixtures();

    $this->artisan('express:prepare-demo', ['city' => 'Medellín'])
        ->assertSuccessful();

    $express = ShippingMethod::query()->where('code', 'express')->firstOrFail();
    expect(Setting::isExpress48hEnabled())->toBeTrue()
        ->and($express->restrict_cities)->toBeTrue()
        ->and($express->fresh()->isAllowedForCity($medellin->id))->toBeTrue()
        ->and($medellin->fresh()->force_delivery_date_enabled)->toBeFalse();
});
