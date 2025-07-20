<?php


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



it('has author')->assertDatabaseHas('users', [
    'id' => 1
]);


it('user not logged cannot access to vendor page', function () {
    $response = $this->get('/admin/vendors');

    $response->assertStatus(302)
        ->assertRedirect('/formulario');
});



it('user logged can  access to vendors page', function () {

    actingAs(User::first())
        ->get('/vendors')
        ->assertStatus(200);
});


it('user logged can access to create vendor page', function () {

    actingAs(User::first())
        ->get('/vendors/create')
        ->assertStatus(200);
});


it('user logged can create vendor', function () {

    actingAs(User::first())
        ->post('/vendors', [
            'name' => 'Article Item',
            'slug' => 'article-item',
            'minimum_purchase' => 0,
            'active' => 1,


        ])
        ->assertRedirect('/vendors')
        ->assertSessionHas('success', 'Vendor creado correctamente');
});


it('user logged can access to edit vendor page', function () {

    $user = User::first();

    $vendor = Vendor::create([
        'name' => 'Article Item',
        'slug' => 'article-item',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);

    actingAs($user)
        ->get("/vendors/{$vendor->id}/edit")
        ->assertStatus(200);
});


it('user logged can access to edit vendor', function () {

    $user = User::first();

    $vendor = Vendor::create([
        'name' => 'Article Item',
        'slug' => 'article-item',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);

    actingAs($user)
        ->put("/vendors/{$vendor->id}", [
            'name' => 'Article Item2',
            'slug' => 'article-item2',
            'minimum_purchase' => 10,
            'active' => 0,
        ])
        ->assertRedirect('/vendors')
        ->assertSessionHas('success', 'Vendor actualizado');
});



it('user logged can delete vendor', function () {

    $user = User::first();

    $vendor = Vendor::create([
        'name' => 'Article Item',
        'slug' => 'article-item',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);

    actingAs($user)
        ->delete("/vendors/{$vendor->id}")
        ->assertRedirect('/vendors')
        ->assertSessionHas('success', 'Vendor eliminado');
});



it('user logged can not delete vendor if has asociate brands', function () {

    $user = User::first();

    $vendor = Vendor::create([
        'name' => 'Article Item',
        'slug' => 'article-item',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);

    $vendor->brands()->create([
        'name' => 'Article Item',
        'description' => 'Article Item',
        'slug' => 'article-item',
        'delivery_days' => 0,
        'active' => 1,
    ]);

    actingAs($user)
        ->delete("/vendors/{$vendor->id}")
        ->assertRedirect("/vendors/{$vendor->id}/edit")
        ->assertSessionHas('error', 'No es posible eliminar el vendor por que tiene marcas asociadas');
});


// it('has article page', function () {
//     $response = $this->get('/articles');
//     dd(  $response);
//     $response->assertStatus(200);
// });
