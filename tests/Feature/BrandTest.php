<?php

use App\Models\Brand;
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


it('user not logged cannot access to brand page', function () {
    $response = $this->get('/admin/brands');

    $response->assertStatus(302)
        ->assertRedirect('/formulario');
});




it('user logged can access to brand page', function () {

    actingAs(User::first())
        ->get('/brands')
        ->assertStatus(200);
});


it('user logged can access to create brand page', function () {

    actingAs(User::first())
        ->get('/brands/create')
        ->assertStatus(200);
});


it('user logged can create brand', function () {

    actingAs(User::first())
        ->post('/brands', [
            'name' => 'Brand Item',
            'description' => 'Brand Item',
            'slug' => 'slug-brand',
            'discount' => 20,
            'active' => 1,
            'delivery_days' => 123


        ])
        ->assertRedirect('/brands')
        ->assertSessionHas('success', 'Marca creada');
});


it('user logged can access to edit brand page', function () {

    $user = User::first();

    $brand = Brand::create([
        'name' => 'Brand Item',
        'description' => 'Brand Item',
        'slug' => 'slug-brand',
        'active' => 1,
        'discount' => 20,
        'delivery_days' => 123
    ]);

    actingAs($user)
        ->get("/brands/{$brand->id}/edit")
        ->assertStatus(200);
});


it('user logged can access to edit brand', function () {

    $user = User::first();

    $brand = Brand::create([
        'name' => 'Brand Item',
        'description' => 'Brand Item',
        'slug' => 'slug-brand',
        'active' => 1,
        'discount' => 10,
        'delivery_days' => 123
    ]);


    actingAs($user)
        ->put("/brands/{$brand->id}", [
            'name' => 'Brand Item1',
            'description' => 'Brand Item1',
            'slug' => 'slug-brand1',
            'active' => 1,
            'discount' => 4,
            'delivery_days' => 123
        ])
        ->assertRedirect('/brands')
        ->assertSessionHas('success', 'Marca actualizada');
});




it('user logged can delete brand', function () {

    $user = User::first();

    $brand = Brand::create([
        'name' => 'Brand Item1',
        'description' => 'Brand Item1',
        'slug' => 'slug-brand1',
        'active' => 1,
        'delivery_days' => 123
    ]);

    actingAs($user)
        ->delete("/brands/{$brand->id}")
        ->assertRedirect('/brands')
        ->assertSessionHas('success', 'Vendor eliminado');
});



it('user logged can not delete brand if has asociate vendor', function () {

    $user = User::first();

    $vendor = Vendor::create([
        'name' => 'Article Item',
        'slug' => 'article-item',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);

    $brand = $vendor->brands()->create([
        'name' => 'Article Item',
        'description' => 'Article Item',
        'slug' => 'article-item',
        'delivery_days' => 0,
        'active' => 1,
    ]);

    actingAs($user)
        ->delete("/brands/{$brand->id}")
        ->assertRedirect("/brands/{$vendor->id}/edit")
        ->assertSessionHas('error', 'No es posible eliminar la marca por que tiene vendors asociados');
});
