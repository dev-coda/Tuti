<?php

use App\Models\Product;
use App\Models\Tax;
use App\Models\Vendor;
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


it('user not logged cannot access to product page', function () {
    $response = $this->get('/admin/products');

    $response->assertStatus(302)
        ->assertRedirect('/formulario');
});



it('user logged can  access to products page', function () {

    actingAs(User::first())
        ->get('/products')
        ->assertStatus(200);
});


it('user logged can access to create product page', function () {

    actingAs(User::first())
        ->get('/products/create')
        ->assertStatus(200);
});


it('user logged can create product', function () {


    $tax = Tax::create([
        'name' => 'IVA',
        'tax' => 20
    ]);

    actingAs(User::first())
        ->post('/products', [
            'name' => 'name',
            'description' => 'name',
            'short_description' => 'name',
            'sku' => 'name',
            'slug' => 'name',
            'active' => 1,
            'price' => 30000,
            'delivery_days' => 1,
            'discount' => 0,
            'quantity_min' => 1,
            'quantity_max' => 1,
            'step' => 1,
            'tax_id' => $tax->id,
            'brand_id' => 1,
            'variation_id' => null,
            'is_combined' => 0,



        ])
        ->assertRedirect('/products/1/edit')
        ->assertSessionHas('success', 'Producto creado');
});


it('user logged can access to edit product page', function () {

    $user = User::first();

    $tax = Tax::create([
        'name' => 'IVA',
        'tax' => 20
    ]);

    $product = Product::create([
        'name' => 'name',
        'description' => 'name',
        'short_description' => 'name',
        'sku' => 'name',
        'slug' => 'name',
        'active' => 1,
        'price' => 30000,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 1,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => 1,
        'variation_id' => null,
        'is_combined' => 0
    ]);

    actingAs($user)
        ->get("/products/{$product->id}/edit")
        ->assertStatus(200);
});


it('user logged can access to edit product', function () {

    $user = User::first();

    $tax = Tax::create([
        'name' => 'IVA',
        'tax' => 20
    ]);

    $product = Product::create([
        'name' => 'name',
        'description' => 'name',
        'short_description' => 'name',
        'sku' => 'name',
        'slug' => 'name',
        'active' => 1,
        'price' => 30000,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 1,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => 1,
        'variation_id' => null,
        'is_combined' => 0
    ]);

    actingAs($user)
        ->put("/products/{$product->id}", [
            'name' => 'name1',
            'description' => 'name1',
            'short_description' => 'name1',
            'sku' => 'name',
            'slug' => 'name',
            'active' => 1,
            'price' => 30000,
            'delivery_days' => 1,
            'discount' => 0,
            'quantity_min' => 1,
            'quantity_max' => 1,
            'step' => 1,
            'tax_id' => $tax->id,
            'brand_id' => 1,
            'variation_id' => null,
            'is_combined' => 0
        ])
        ->assertRedirect('/products')
        ->assertSessionHas('success', 'Producto actualizado');
});



// it('user logged can product vendor', function(){

//     $user = User::first();

//     $tax = Tax::create([
//         'name'=>'IVA',
//         'tax'=>20
//     ]);

//     $product = Product::create([
//         'name' => 'name1',
//         'description' => 'name1',
//         'short_description' => 'name1',
//         'sku' => 'name',
//         'slug' => 'name',
//         'active' => 1,
//         'price' => 30000,
//         'delivery_days' => 1,
//         'discount' => 0,
//         'quantity_min' => 1,
//         'quantity_max' =>1,
//         'step' => 1,
//         'tax_id' => $tax->id,
//         'brand_id' => 1,
//         'variation_id' => null,
//         'is_combined' => 0  
//         ]);

//     actingAs($user)
//         ->delete("/products/{$product->id}")
//         ->assertRedirect('/products')
//         ->assertSessionHas('success', 'Vendor eliminado');

// });



// it('user logged can not delete vendor if has asociate brands', function(){

//     $user = User::first();

//     $vendor = Vendor::create([
//         'name'=> 'Article Item',
//         'slug'=> 'article-item',
//         'minimum_purchase'=> 0,
//         'active'=> 1,
//     ]);

//     $vendor->brands()->create([
//         'name'=> 'Article Item',
//         'description'=> 'Article Item',
//         'slug'=> 'article-item',
//         'delivery_days'=> 0,
//         'active'=> 1,
//     ]);

//     actingAs($user)
//         ->delete("/vendors/{$vendor->id}")
//         ->assertRedirect("/vendors/{$vendor->id}/edit")
//         ->assertSessionHas('error', 'No es posible eliminar el vendor por que tiene marcas asociadas');

// });
