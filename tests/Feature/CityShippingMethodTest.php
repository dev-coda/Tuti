<?php

use App\Models\City;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Models\State;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Models\Brand;
use App\Models\Tax;
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
