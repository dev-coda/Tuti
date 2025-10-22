<?php

use App\Models\Bonification;
use App\Models\User;
use function Pest\Laravel\{actingAs, get, post};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);



beforeEach(function () {
    $user = User::factory()->create();
    Role::create([
        'name' => 'admin',
        'guard_name' => 'web'
    ]);
    $user->assignRole('admin');
});


it('user not logged cannot access to bonification page', function () {
    get('/admin/bonifications')
        ->assertRedirect('/formulario');
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

    actingAs(User::first())
        ->post('/bonifications', [
            'name' => 'Pague 10 lleve 2',
            'buy' => 10,
            'get' => 2,
        ])
        ->assertRedirect('/bonifications/1/edit')
        ->assertSessionHas('success', 'Bonificación creada, agregue los productos');
});


it('user logged can access to edit bonification page', function () {

    $user = User::first();

    $bonification = Bonification::create([
        'name' => 'Pague 10 lleve 2',
        'buy' => 10,
        'get' => 2,
    ]);

    actingAs($user)
        ->get("/bonifications/{$bonification->id}/edit")
        ->assertStatus(200);
});


it('user logged can access to edit bonification', function () {

    $user = User::first();

    $bonification = Bonification::create([
        'name' => 'Pague 10 lleve 2',
        'buy' => 10,
        'get' => 2,
    ]);


    actingAs($user)
        ->put("/bonifications/{$bonification->id}", [
            'name' => 'Pague 20 lleve 4',
            'buy' => 20,
            'get' => 4,
        ])
        ->assertRedirect('/bonifications')
        ->assertSessionHas('success', 'Bonificación actualizada');
});




it('user logged can delete bonification', function () {

    $user = User::first();

    $bonification = Bonification::create([
        'name' => 'Pague 10 lleve 2',
        'buy' => 10,
        'get' => 2,
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
        'tax' => 0
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

    // Create a zone for the user
    $zone = \App\Models\Zone::create([
        'route' => '1',
        'zone' => 'Test Zone',
        'day' => 'Monday',
        'address' => 'Test Address',
        'code' => 'TEST01',
    ]);
    $user->zones()->attach($zone->id);

    // Disable inventory management for this test
    \App\Models\Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['value' => '0']
    );

    // Add product to cart (buying 10 units should trigger both bonifications)
    session()->put('cart', [
        [
            'product_id' => $product->id,
            'quantity' => 10,
            'variation_id' => null,
        ]
    ]);

    // Process the order
    actingAs($user)
        ->post('/cart/process', [
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
