<?php

use App\Models\Brand;
use App\Models\City;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Setting;
use App\Models\State;
use App\Models\SupervisorRoute;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supernumerario', 'guard_name' => 'web']);
});

function catalogCity(string $name): City
{
    $state = State::create(['name' => 'Departamento '.$name]);

    return City::create([
        'name' => $name,
        'state_id' => $state->id,
        'active' => true,
        'is_preferred' => true,
    ]);
}

it('shows Cucuta stock to a user domiciled in Cucuta even when the profile zone is Medellin', function () {
    $cucuta = catalogCity('Cúcuta');
    $user = User::factory()->create([
        'zone' => '102',
        'city_id' => $cucuta->id,
    ]);

    expect($user->catalogBodegaCode())->toBe('CU00');
});

it('uses a supervisor Cucuta route instead of a Medellin profile zone', function () {
    $supervisor = User::factory()->create(['zone' => '102']);
    $supervisor->assignRole('supervisor');

    SupervisorRoute::create([
        'user_id' => $supervisor->id,
        'zone' => '409',
        'route' => '',
    ]);

    expect($supervisor->fresh()->catalogBodegaCode())->toBe('CU00');
});

it('uses the client delivery zone before a stale users.zone', function () {
    $client = User::factory()->create(['zone' => '102']);
    Zone::create([
        'user_id' => $client->id,
        'zone' => '409',
        'route' => 'R1',
        'day' => '1',
        'address' => 'Calle 10',
        'code' => 'CU-1',
    ]);

    expect($client->fresh()->catalogBodegaCode())->toBe('CU00');
});

it('renders the product page with the domicile warehouse stock', function () {
    Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventario', 'value' => '1', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'global_minimum_inventory'],
        ['name' => 'Minimo', 'value' => '0', 'show' => false]
    );

    $cucuta = catalogCity('Cúcuta');
    $user = User::factory()->create([
        'zone' => '102',
        'city_id' => $cucuta->id,
    ]);

    $tax = Tax::create(['name' => 'IVA bodega', 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Vendor bodega',
        'slug' => 'vendor-bodega',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Marca bodega',
        'slug' => 'marca-bodega',
        'vendor_id' => $vendor->id,
        'active' => 1,
    ]);
    $product = Product::create([
        'name' => 'Pila Cucuta',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'SKU-CUCUTA',
        'slug' => 'pila-cucuta',
        'active' => 1,
        'price' => 1000,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    ProductInventory::create([
        'product_id' => $product->id,
        'bodega_code' => 'CU00',
        'available' => 80,
        'physical' => 80,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $product->id,
        'bodega_code' => 'MDTAT',
        'available' => 12,
        'physical' => 12,
        'reserved' => 0,
    ]);

    actingAs($user)
        ->get(route('product', $product->slug))
        ->assertOk()
        ->assertSee('80 unidades')
        ->assertDontSee('12 unidades');
});
