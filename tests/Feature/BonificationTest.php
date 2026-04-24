<?php

use App\Models\Bonification;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\ZoneWarehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/**
 * Gift product required for bonification validation (product_id, max, etc.).
 */
function giftProductForBonificationCrud(): Product
{
    $tax = Tax::create(['name' => 'IVA-BONIF-'.uniqid(), 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'V-Bonif-'.uniqid(),
        'slug' => 'v-bonif-'.uniqid(),
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'B-Bonif-'.uniqid(),
        'slug' => 'b-bonif-'.uniqid(),
        'vendor_id' => $vendor->id,
    ]);

    return Product::create([
        'name' => 'Gift bonif CRUD',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'SKU-BONIF-'.uniqid(),
        'slug' => 'gift-bonif-'.uniqid(),
        'active' => 1,
        'price' => 100,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);
}

beforeEach(function () {
    $user = User::factory()->create();
    Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
    ]);
    $user->assignRole('admin');
});

it('user not logged cannot access to bonification page', function () {
    get('/bonifications')
        ->assertRedirect('/login');
});

it('user logged can access to bonification page', function () {

    actingAs(User::first())
        ->get('/bonifications')
        ->assertStatus(200);
});

it('user logged can access to create bonification page', function () {

    actingAs(User::first())
        ->get('/bonifications/create')
        ->assertStatus(200);
});

it('user logged can create bonification', function () {
    $gift = giftProductForBonificationCrud();

    $response = actingAs(User::first())
        ->post('/bonifications', [
            'name' => 'Pague 10 lleve 2',
            'buy' => 10,
            'get' => 2,
            'max' => 10,
            'product_id' => $gift->id,
        ]);

    $bonification = Bonification::latest('id')->first();
    $response->assertRedirect(route('bonifications.edit', $bonification))
        ->assertSessionHas('success', 'Bonificación creada, agregue los productos');
});

it('user logged can access to edit bonification page', function () {

    $user = User::first();

    $gift = giftProductForBonificationCrud();
    $bonification = Bonification::create([
        'name' => 'Pague 10 lleve 2',
        'buy' => 10,
        'get' => 2,
        'max' => 10,
        'product_id' => $gift->id,
    ]);

    actingAs($user)
        ->get("/bonifications/{$bonification->id}/edit")
        ->assertStatus(200);
});

it('user logged can access to edit bonification', function () {

    $user = User::first();

    $gift = giftProductForBonificationCrud();
    $bonification = Bonification::create([
        'name' => 'Pague 10 lleve 2',
        'buy' => 10,
        'get' => 2,
        'max' => 10,
        'product_id' => $gift->id,
    ]);

    actingAs($user)
        ->put("/bonifications/{$bonification->id}", [
            'name' => 'Pague 20 lleve 4',
            'buy' => 20,
            'get' => 4,
            'max' => 20,
            'product_id' => $gift->id,
        ])
        ->assertRedirect('/bonifications')
        ->assertSessionHas('success', 'Bonificación actualizada');
});

it('user logged can delete bonification', function () {

    $user = User::first();

    $gift = giftProductForBonificationCrud();
    $bonification = Bonification::create([
        'name' => 'Pague 10 lleve 2',
        'buy' => 10,
        'get' => 2,
        'max' => 10,
        'product_id' => $gift->id,
    ]);

    actingAs($user)
        ->delete("/bonifications/{$bonification->id}")
        ->assertRedirect('/bonifications')
        ->assertSessionHas('success', 'La bonificacion se ha eliminado correctamente');
});

it('multiple bonifications can be applied to a single order', function () {
    // This test ensures that when a product qualifies for multiple bonifications,
    // all applicable bonifications are applied, not just the first one

    $user = User::first();

    // Create a tax for products
    $tax = \App\Models\Tax::create([
        'name' => 'IVA',
        'tax' => 0,
    ]);

    // Create a brand for the products
    $vendor = \App\Models\Vendor::create([
        'name' => 'Test Vendor',
        'slug' => 'test-vendor',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);

    $brand = \App\Models\Brand::create([
        'name' => 'Test Brand',
        'slug' => 'test-brand',
        'vendor_id' => $vendor->id,
    ]);

    // Create two gift products that will be given as bonifications
    $giftProduct1 = \App\Models\Product::create([
        'name' => 'Gift Product 1',
        'description' => 'Gift 1',
        'short_description' => 'Gift 1',
        'sku' => 'GIFT001',
        'slug' => 'gift-product-1',
        'active' => 1,
        'price' => 0,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    $giftProduct2 = \App\Models\Product::create([
        'name' => 'Gift Product 2',
        'description' => 'Gift 2',
        'short_description' => 'Gift 2',
        'sku' => 'GIFT002',
        'slug' => 'gift-product-2',
        'active' => 1,
        'price' => 0,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    // Create a regular product that will trigger bonifications
    $product = \App\Models\Product::create([
        'name' => 'Test Product',
        'description' => 'Test Product',
        'short_description' => 'Test Product',
        'sku' => 'TEST001',
        'slug' => 'test-product',
        'active' => 1,
        'price' => 100,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    // Create two different bonifications
    $bonification1 = Bonification::create([
        'name' => 'Buy 10 Get 1 Free',
        'buy' => 10,
        'get' => 1,
        'product_id' => $giftProduct1->id,
        'max' => 10,
    ]);

    $bonification2 = Bonification::create([
        'name' => 'Buy 5 Get 1 Free',
        'buy' => 5,
        'get' => 1,
        'product_id' => $giftProduct2->id,
        'max' => 5,
    ]);

    // Associate the product with BOTH bonifications
    $product->bonifications()->attach([$bonification1->id, $bonification2->id]);

    // Create a zone owned by the user (User::zones() is hasMany)
    $zone = \App\Models\Zone::create([
        'route' => '1',
        'zone' => 'Test Zone',
        'day' => 'Monday',
        'address' => 'Test Address',
        'code' => 'TEST01',
        'user_id' => $user->id,
    ]);

    // Disable inventory management for this test
    \App\Models\Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '0', 'show' => false]
    );

    // Add product to cart (buying 10 units should trigger both bonifications)
    session()->put('cart', [
        [
            'product_id' => $product->id,
            'quantity' => 10,
            'variation_id' => null,
        ],
    ]);

    // Process the order (POST /carrito — see routes/web.php)
    actingAs($user)
        ->post(route('cart.process'), [
            'zone_id' => $zone->id,
            'observations' => 'Test order with multiple bonifications',
        ]);

    // Verify that the order was created
    $order = \App\Models\Order::latest()->first();
    expect($order)->not->toBeNull();

    // Verify that BOTH bonifications were applied
    $bonifications = $order->bonifications;
    expect($bonifications->count())->toBe(2);

    // Verify bonification 1 (Buy 10 Get 1): 10 items / 10 * 1 = 1 free item
    $bonif1 = $bonifications->where('bonification_id', $bonification1->id)->first();
    expect($bonif1)->not->toBeNull();
    expect($bonif1->quantity)->toBe(1);
    expect($bonif1->product_id)->toBe($giftProduct1->id);

    // Verify bonification 2 (Buy 5 Get 1): 10 items / 5 * 1 = 2 free items
    $bonif2 = $bonifications->where('bonification_id', $bonification2->id)->first();
    expect($bonif2)->not->toBeNull();
    expect($bonif2->quantity)->toBe(2);
    expect($bonif2->product_id)->toBe($giftProduct2->id);
});

it('creates separate bonification rows when different trigger products share the same gift product', function () {
    $user = User::first();

    $tax = \App\Models\Tax::create([
        'name' => 'IVA',
        'tax' => 0,
    ]);

    $vendor = \App\Models\Vendor::create([
        'name' => 'Vendor Shared Gift',
        'slug' => 'vendor-shared-gift',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);

    $brand = \App\Models\Brand::create([
        'name' => 'Brand Shared Gift',
        'slug' => 'brand-shared-gift',
        'vendor_id' => $vendor->id,
    ]);

    $sharedGift = \App\Models\Product::create([
        'name' => 'Shared Gift',
        'description' => 'Gift',
        'short_description' => 'Gift',
        'sku' => 'GIFT-SHARED',
        'slug' => 'shared-gift',
        'active' => 1,
        'price' => 0,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    $triggerA = \App\Models\Product::create([
        'name' => 'Trigger A',
        'description' => 'A',
        'short_description' => 'A',
        'sku' => 'TRIG-A',
        'slug' => 'trigger-a',
        'active' => 1,
        'price' => 100,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    $triggerB = \App\Models\Product::create([
        'name' => 'Trigger B',
        'description' => 'B',
        'short_description' => 'B',
        'sku' => 'TRIG-B',
        'slug' => 'trigger-b',
        'active' => 1,
        'price' => 100,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    $bonificationA = Bonification::create([
        'name' => 'Rule A',
        'buy' => 10,
        'get' => 1,
        'product_id' => $sharedGift->id,
        'max' => 10,
    ]);

    $bonificationB = Bonification::create([
        'name' => 'Rule B',
        'buy' => 10,
        'get' => 1,
        'product_id' => $sharedGift->id,
        'max' => 10,
    ]);

    $triggerA->bonifications()->attach($bonificationA->id);
    $triggerB->bonifications()->attach($bonificationB->id);

    $zone = \App\Models\Zone::create([
        'route' => '1',
        'zone' => 'Z',
        'day' => 'Monday',
        'address' => 'Addr',
        'code' => 'Z01',
        'user_id' => $user->id,
    ]);

    \App\Models\Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '0', 'show' => false]
    );

    session()->put('cart', [
        ['product_id' => $triggerA->id, 'quantity' => 10, 'variation_id' => null],
        ['product_id' => $triggerB->id, 'quantity' => 10, 'variation_id' => null],
    ]);

    actingAs($user)
        ->post(route('cart.process'), [
            'zone_id' => $zone->id,
            'observations' => 'Shared gift bonifications',
        ]);

    $order = \App\Models\Order::latest()->first();
    expect($order)->not->toBeNull();

    $rows = $order->bonifications;
    expect($rows->count())->toBe(2);
    expect($rows->where('bonification_id', $bonificationA->id)->first()->quantity)->toBe(1);
    expect($rows->where('bonification_id', $bonificationB->id)->first()->quantity)->toBe(1);
    expect($rows->pluck('product_id')->unique()->count())->toBe(1);
    expect($rows->first()->product_id)->toBe($sharedGift->id);
});

it('skips bonifications when stock cannot cover all gifted items involved', function () {
    $user = User::first();

    $tax = Tax::create([
        'name' => 'IVA stock',
        'tax' => 0,
    ]);

    $vendor = Vendor::create([
        'name' => 'Vendor stock',
        'slug' => 'vendor-stock',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);

    $brand = Brand::create([
        'name' => 'Brand stock',
        'slug' => 'brand-stock',
        'vendor_id' => $vendor->id,
    ]);

    $gift = Product::create([
        'name' => 'Gift constrained',
        'description' => 'Gift constrained',
        'short_description' => 'Gift constrained',
        'sku' => 'GIFT-CONSTRAINED',
        'slug' => 'gift-constrained',
        'active' => 1,
        'price' => 0,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
        'safety_stock' => 0,
        'inventory_opt_out' => 0,
    ]);

    $trigger = Product::create([
        'name' => 'Trigger constrained',
        'description' => 'Trigger constrained',
        'short_description' => 'Trigger constrained',
        'sku' => 'TRIGGER-CONSTRAINED',
        'slug' => 'trigger-constrained',
        'active' => 1,
        'price' => 100,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
        'safety_stock' => 0,
        'inventory_opt_out' => 0,
    ]);

    $bonificationA = Bonification::create([
        'name' => 'Rule constrained A',
        'buy' => 1,
        'get' => 3,
        'product_id' => $gift->id,
        'max' => 100,
    ]);
    $bonificationB = Bonification::create([
        'name' => 'Rule constrained B',
        'buy' => 1,
        'get' => 4,
        'product_id' => $gift->id,
        'max' => 100,
    ]);
    $trigger->bonifications()->attach([$bonificationA->id, $bonificationB->id]);

    $zone = \App\Models\Zone::create([
        'route' => '1',
        'zone' => '933',
        'day' => 'Monday',
        'address' => 'Addr',
        'code' => 'Z-STOCK',
        'user_id' => $user->id,
    ]);

    Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '1', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'global_minimum_inventory'],
        ['name' => 'Global minimum inventory', 'value' => '5', 'show' => false]
    );
    ZoneWarehouse::updateOrCreate(
        ['zone_code' => '933'],
        ['bodega_code' => 'BOD-1']
    );

    // Trigger product has enough stock to purchase 1 unit.
    ProductInventory::create([
        'product_id' => $trigger->id,
        'bodega_code' => 'BOD-1',
        'available' => 20,
        'physical' => 20,
        'reserved' => 0,
    ]);

    // Gift product available=11 with global minimum=5 => max givable=6.
    // Combined demand from both bonifications is 7, so BOTH should be skipped.
    ProductInventory::create([
        'product_id' => $gift->id,
        'bodega_code' => 'BOD-1',
        'available' => 11,
        'physical' => 11,
        'reserved' => 0,
    ]);

    session()->put('cart', [
        ['product_id' => $trigger->id, 'quantity' => 1, 'variation_id' => null],
    ]);

    actingAs($user)->post(route('cart.process'), [
        'zone_id' => $zone->id,
        'observations' => 'Constrained gift stock',
    ]);

    $order = \App\Models\Order::latest()->first();
    expect($order)->not->toBeNull();
    expect($order->products()->count())->toBe(1);
    expect($order->bonifications()->count())->toBe(0);

    $giftInventoryAfter = ProductInventory::where('product_id', $gift->id)
        ->where('bodega_code', 'BOD-1')
        ->first();
    expect((int) $giftInventoryAfter->available)->toBe(11);
});
