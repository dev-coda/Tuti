<?php

use App\Models\Brand;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\State;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function cityShippingFixtures(): array
{
    $state = State::create(['name' => 'Antioquia']);
    $medellin = City::create(['name' => 'Medellín', 'state_id' => $state->id, 'active' => true, 'is_preferred' => true]);
    $bogotaState = State::create(['name' => 'Cundinamarca']);
    $bogota = City::create(['name' => 'Bogotá', 'state_id' => $bogotaState->id, 'active' => true, 'is_preferred' => true]);

    $express = ShippingMethod::query()->where('code', 'express')->firstOrFail();
    $tronex = ShippingMethod::query()->where('code', 'tronex')->firstOrFail();

    return compact('medellin', 'bogota', 'express', 'tronex');
}

it('allows a shipping method in a city until it is explicitly disabled', function () {
    ['medellin' => $medellin, 'bogota' => $bogota, 'express' => $express] = cityShippingFixtures();

    expect($express->isAllowedForCity($medellin->id))->toBeTrue()
        ->and($express->isAllowedForCity($bogota->id))->toBeTrue()
        ->and(ShippingMethod::isCodeAllowedForCity('express', null))->toBeTrue();

    $express->cities()->sync([
        $medellin->id => ['enabled' => false],
    ]);

    expect($express->fresh()->isAllowedForCity($medellin->id))->toBeFalse()
        ->and($express->fresh()->isAllowedForCity($bogota->id))->toBeTrue()
        ->and(ShippingMethod::cityAvailabilityFlags($medellin->id)['express'])->toBeFalse()
        ->and(ShippingMethod::cityAvailabilityFlags($medellin->id)['standard'])->toBeTrue();
});

it('saves per-city shipping availability from admin', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    ['medellin' => $medellin, 'bogota' => $bogota, 'express' => $express] = cityShippingFixtures();

    actingAs($admin)
        ->put(route('shipping-methods.update', $express), [
            'name' => $express->name,
            'description' => $express->description,
            'sort_order' => $express->sort_order,
            'enabled' => 1,
            'city_enabled' => [
                $medellin->id => '0',
                $bogota->id => '1',
            ],
        ])
        ->assertRedirect(route('shipping-methods.index'));

    expect($express->fresh()->isAllowedForCity($medellin->id))->toBeFalse()
        ->and($express->fresh()->isAllowedForCity($bogota->id))->toBeTrue();

    actingAs($admin)
        ->get(route('shipping-methods.edit', $express))
        ->assertOk()
        ->assertSee('Disponibilidad por ciudad')
        ->assertSee('Medellín')
        ->assertSee('Bogotá');
});

it('hides a city-disabled method in the cart and rejects it at checkout', function () {
    Setting::updateOrCreate(['key' => 'inventory_enabled'], ['name' => 'inv', 'value' => '0', 'show' => false]);
    Setting::updateOrCreate(['key' => 'min_amount'], ['name' => 'min', 'value' => '0', 'show' => false]);
    Setting::updateOrCreate(['key' => 'express_48h_enabled'], ['name' => '48h', 'value' => '1', 'show' => false]);

    ['medellin' => $medellin, 'express' => $express] = cityShippingFixtures();
    $express->cities()->sync([$medellin->id => ['enabled' => false]]);

    $client = User::factory()->create(['city_id' => $medellin->id]);
    $zone = Zone::create([
        'user_id' => $client->id,
        'route' => 'R-CITY',
        'zone' => '933',
        'day' => '1',
        'address' => 'Calle Ciudad',
        'code' => 'CITY-1',
        'shipping_standard_enabled' => true,
        'shipping_express_enabled' => true,
    ]);

    $tax = Tax::create(['name' => 'IVA city ship', 'tax' => 0]);
    $vendor = Vendor::create(['name' => 'V city ship', 'slug' => 'v-city-ship', 'minimum_purchase' => 0, 'active' => 1]);
    $brand = Brand::create(['name' => 'B city ship', 'slug' => 'b-city-ship', 'vendor_id' => $vendor->id]);
    $product = Product::create([
        'name' => 'City ship product',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'CITY-SHIP-1',
        'slug' => 'city-ship-1',
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

    session()->put('cart', [
        ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
    ]);

    actingAs($client)
        ->get(route('cart'))
        ->assertOk()
        ->assertSee('"express":false', false)
        ->assertSee('"standard":true', false);

    actingAs($client)
        ->post(route('cart.process'), [
            'zone_id' => $zone->id,
            'delivery_method' => Order::DELIVERY_METHOD_EXPRESS,
            'observations' => '',
        ])
        ->assertSessionHas('error');

    expect((string) session('error'))->toContain('ciudad')
        ->and(Order::query()->count())->toBe(0);
});

it('uses the selected client city when a seller is checking out', function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Setting::updateOrCreate(['key' => 'inventory_enabled'], ['name' => 'inv', 'value' => '0', 'show' => false]);
    Setting::updateOrCreate(['key' => 'min_amount'], ['name' => 'min', 'value' => '0', 'show' => false]);

    ['medellin' => $medellin, 'bogota' => $bogota, 'tronex' => $tronex] = cityShippingFixtures();
    $tronex->cities()->sync([$medellin->id => ['enabled' => false]]);

    $seller = User::factory()->create(['city_id' => $bogota->id]);
    $seller->assignRole('seller');
    $client = User::factory()->create(['city_id' => $medellin->id]);

    expect(ShippingMethod::isCodeAllowedForCity('tronex', (int) $seller->city_id))->toBeTrue()
        ->and(ShippingMethod::isCodeAllowedForCity('tronex', (int) $client->city_id))->toBeFalse();

    $tax = Tax::create(['name' => 'IVA seller city', 'tax' => 0]);
    $vendor = Vendor::create(['name' => 'V seller city', 'slug' => 'v-seller-city', 'minimum_purchase' => 0, 'active' => 1]);
    $brand = Brand::create(['name' => 'B seller city', 'slug' => 'b-seller-city', 'vendor_id' => $vendor->id]);
    $product = Product::create([
        'name' => 'Seller city product',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'SELLER-CITY-1',
        'slug' => 'seller-city-1',
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
    $zone = Zone::create([
        'user_id' => $client->id,
        'route' => 'R-SEL',
        'zone' => '933',
        'day' => '1',
        'address' => 'Calle Seller Client',
        'code' => 'SEL-1',
    ]);

    actingAs($seller)
        ->withSession([
            'user_id' => $client->id,
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
            ],
        ])
        ->post(route('cart.process'), [
            'zone_id' => $zone->id,
            'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
            'observations' => '',
        ])
        ->assertSessionHas('error');

    expect((string) session('error'))->toContain('ciudad');
});

it('limits a shipping method to an allowlist of cities when restrict_cities is on', function () {
    ['medellin' => $medellin, 'bogota' => $bogota, 'express' => $express] = cityShippingFixtures();

    $express->update(['restrict_cities' => true]);
    $express->cities()->sync([
        $medellin->id => ['enabled' => true],
    ]);

    expect($express->fresh()->isAllowedForCity($medellin->id))->toBeTrue()
        ->and($express->fresh()->isAllowedForCity($bogota->id))->toBeFalse()
        ->and($express->fresh()->isAllowedForCity(null))->toBeFalse()
        ->and(ShippingMethod::cityAvailabilityFlags($medellin->id)['express'])->toBeTrue()
        ->and(ShippingMethod::cityAvailabilityFlags($bogota->id)['express'])->toBeFalse();
});

it('saves allowlist city availability from admin', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    ['medellin' => $medellin, 'bogota' => $bogota, 'express' => $express] = cityShippingFixtures();

    actingAs($admin)
        ->put(route('shipping-methods.update', $express), [
            'name' => $express->name,
            'description' => $express->description,
            'sort_order' => $express->sort_order,
            'enabled' => 1,
            'restrict_cities' => 1,
            'city_enabled' => [
                $medellin->id => '1',
                $bogota->id => '0',
            ],
        ])
        ->assertRedirect(route('shipping-methods.index'));

    $express->refresh();

    expect($express->restrict_cities)->toBeTrue()
        ->and($express->isAllowedForCity($medellin->id))->toBeTrue()
        ->and($express->isAllowedForCity($bogota->id))->toBeFalse();

    actingAs($admin)
        ->get(route('shipping-methods.index'))
        ->assertOk()
        ->assertSee('Solo 1 ciudad');
});

it('turns off force delivery date for a city while the global toggle stays on', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    ['medellin' => $medellin, 'bogota' => $bogota] = cityShippingFixtures();

    Setting::updateOrCreate(
        ['key' => 'force_delivery_date_enabled'],
        ['name' => 'Forzar Fecha de Entrega', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_force_delivery_date_enabled');

    expect(Setting::isForceDeliveryDateEnabled($medellin->id))->toBeTrue()
        ->and(Setting::isForceDeliveryDateEnabled($bogota->id))->toBeTrue();

    actingAs($admin)
        ->post(route('settings.update-force-delivery-date-cities'), [
            'city_force_delivery' => [
                $medellin->id => '0',
                $bogota->id => '1',
            ],
        ])
        ->assertRedirect();

    $medellin->refresh();
    $bogota->refresh();

    expect($medellin->force_delivery_date_enabled)->toBeFalse()
        ->and($bogota->force_delivery_date_enabled)->toBeTrue()
        ->and(Setting::isForceDeliveryDateEnabled($medellin->id))->toBeFalse()
        ->and(Setting::isForceDeliveryDateEnabled($bogota->id))->toBeTrue()
        ->and(Setting::isForceDeliveryDateEnabled())->toBeTrue();

    actingAs($admin)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('No aplicar Forzar Fecha en estas ciudades');
});

it('keeps the programmed SOAP delivery date when force date is off for the city', function () {
    Setting::updateOrCreate(
        ['key' => 'force_delivery_date_enabled'],
        ['name' => 'Forzar Fecha de Entrega', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_force_delivery_date_enabled');

    ['medellin' => $medellin, 'bogota' => $bogota] = cityShippingFixtures();
    $medellin->update(['force_delivery_date_enabled' => false]);

    $tax = Tax::create(['name' => 'IVA force city', 'tax' => 0]);
    $vendor = Vendor::create(['name' => 'V force city', 'slug' => 'v-force-city', 'minimum_purchase' => 0, 'active' => 1]);
    $brand = Brand::create(['name' => 'B force city', 'slug' => 'b-force-city', 'vendor_id' => $vendor->id]);
    $product = Product::create([
        'name' => 'Force city product',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'FORCE-CITY-1',
        'slug' => 'force-city-1',
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

    $originalDate = '2030-06-15';
    $forcedDate = OrderRepository::getBusinessDay(0);

    $pilotUser = User::factory()->create(['city_id' => $medellin->id]);
    $otherUser = User::factory()->create(['city_id' => $bogota->id]);
    $zone = Zone::create([
        'user_id' => $pilotUser->id,
        'route' => 'R-FORCE',
        'zone' => '933',
        'day' => '1',
        'address' => 'Calle Force',
        'code' => 'FORCE-1',
    ]);

    $makeOrder = function (User $user) use ($zone, $originalDate, $product) {
        $order = Order::create([
            'user_id' => $user->id,
            'total' => 10000,
            'discount' => 0,
            'status_id' => Order::STATUS_PENDING,
            'zone_id' => $zone->id,
            'delivery_date' => $originalDate,
            'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        ]);
        OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 10000,
            'discount' => 0,
            'percentage' => 0,
            'discount_type' => 'percentage',
            'flat_discount_amount' => 0,
            'package_quantity' => 1,
        ]);

        return $order->fresh(['user', 'zone', 'products.product.brand.vendor']);
    };

    $pilotXml = OrderRepository::buildOrderXmlForDiagnostic($makeOrder($pilotUser), false);
    $otherXml = OrderRepository::buildOrderXmlForDiagnostic($makeOrder($otherUser), false);

    expect($pilotXml)->toContain('<dyn:deliveryDate>'.$originalDate.'</dyn:deliveryDate>')
        ->and($otherXml)->toContain('<dyn:deliveryDate>'.$forcedDate.'</dyn:deliveryDate>');
});

it('completes an express checkout only in the allowlisted city and shows dates when force is off there', function () {
    Setting::updateOrCreate(['key' => 'inventory_enabled'], ['name' => 'inv', 'value' => '0', 'show' => false]);
    Setting::updateOrCreate(['key' => 'min_amount'], ['name' => 'min', 'value' => '0', 'show' => false]);
    Setting::updateOrCreate(['key' => 'express_48h_enabled'], ['name' => '48h', 'value' => '1', 'show' => false]);
    Setting::updateOrCreate(['key' => 'force_delivery_date_enabled'], ['name' => 'force', 'value' => '1', 'show' => false]);
    Cache::forget('setting_express_48h_enabled');
    Cache::forget('setting_force_delivery_date_enabled');

    ['medellin' => $medellin, 'bogota' => $bogota, 'express' => $express] = cityShippingFixtures();
    $medellin->update(['force_delivery_date_enabled' => false]);
    $express->update(['restrict_cities' => true, 'enabled' => true]);
    $express->cities()->sync([$medellin->id => ['enabled' => true]]);

    $pilot = User::factory()->create(['city_id' => $medellin->id]);
    $other = User::factory()->create(['city_id' => $bogota->id]);

    $tax = Tax::create(['name' => 'IVA express city', 'tax' => 0]);
    $vendor = Vendor::create(['name' => 'V express city', 'slug' => 'v-express-city', 'minimum_purchase' => 0, 'active' => 1]);
    $brand = Brand::create(['name' => 'B express city', 'slug' => 'b-express-city', 'vendor_id' => $vendor->id]);
    $product = Product::create([
        'name' => 'Express city product',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'EXPRESS-CITY-1',
        'slug' => 'express-city-1',
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

    $pilotZone = Zone::create([
        'user_id' => $pilot->id,
        'route' => 'R-EXP',
        'zone' => '933',
        'day' => '1',
        'address' => 'Calle Express Pilot',
        'code' => 'EXP-1',
        'dane_code' => '05001000',
        'fulfillment_provider_48h' => 'coordinadora',
        'shipping_standard_enabled' => true,
        'shipping_express_enabled' => true,
    ]);
    $otherZone = Zone::create([
        'user_id' => $other->id,
        'route' => 'R-EXP-B',
        'zone' => '933',
        'day' => '1',
        'address' => 'Calle Express Other',
        'code' => 'EXP-2',
        'dane_code' => '11001000',
        'fulfillment_provider_48h' => 'coordinadora',
        'shipping_standard_enabled' => true,
        'shipping_express_enabled' => true,
    ]);

    config([
        'services.coordinadora.oauth_url' => 'https://coordinadora.test/oauth/token',
        'services.coordinadora.base_url' => 'https://coordinadora.test',
        'services.coordinadora.key' => 'k',
        'services.coordinadora.secret' => 's',
        'services.coordinadora.id_proceso' => '11577',
        'services.coordinadora.nit' => '811025446',
        'services.coordinadora.origin_dane' => '05001000',
    ]);
    Http::fake([
        'https://coordinadora.test/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
        'https://coordinadora.test/cotizador/nacional' => Http::response([
            'isError' => false,
            'data' => [
                'flete_total' => 12900,
                'valor_envio' => 12900,
                'dias_entrega' => 1,
            ],
        ], 200),
    ]);

    actingAs($pilot)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
            ],
        ])
        ->get(route('cart'))
        ->assertOk()
        ->assertSee('"express":true', false)
        ->assertSee('forceDeliveryEnabled = false', false);

    actingAs($other)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
            ],
        ])
        ->get(route('cart'))
        ->assertOk()
        ->assertSee('"express":false', false)
        ->assertSee('forceDeliveryEnabled = true', false);

    actingAs($pilot)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
            ],
        ])
        ->getJson('/api/shipping-quote/express?zone_id='.$pilotZone->id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'provider' => Order::SHIPPING_PROVIDER_COORDINADORA,
            'shipping_cost' => 12900.0,
        ]);

    actingAs($other)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
            ],
        ])
        ->getJson('/api/shipping-quote/express?zone_id='.$otherZone->id)
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    actingAs($pilot)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
            ],
        ])
        ->post(route('cart.process'), [
            'zone_id' => $pilotZone->id,
            'delivery_method' => Order::DELIVERY_METHOD_EXPRESS,
            'observations' => '',
        ])
        ->assertSessionMissing('error')
        ->assertRedirect();

    $order = Order::query()->first();
    expect($order)->not->toBeNull()
        ->and($order->delivery_method)->toBe(Order::DELIVERY_METHOD_EXPRESS)
        ->and($order->shipping_provider)->toBe(Order::SHIPPING_PROVIDER_COORDINADORA)
        ->and((float) $order->shipping_quote_amount)->toBe(12900.0)
        ->and(\Illuminate\Support\Carbon::parse($order->delivery_date)->format('Y-m-d'))
        ->toBe(OrderRepository::getExpressDeliveryDate());

    actingAs($other)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
            ],
        ])
        ->post(route('cart.process'), [
            'zone_id' => $otherZone->id,
            'delivery_method' => Order::DELIVERY_METHOD_EXPRESS,
            'observations' => '',
        ])
        ->assertSessionHas('error');

    expect(Order::query()->count())->toBe(1);
});
