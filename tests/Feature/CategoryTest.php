<?php

use App\Models\Category;
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


it('user not logged cannot access to category page', function () {
    get('/admin/categories')
        ->assertRedirect('/formulario');
});




it('user logged can access to category page', function () {

    actingAs(User::first())
        ->get('/categories')
        ->assertStatus(200);
});


it('user logged can access to create category page', function () {

    actingAs(User::first())
        ->get('/categories/create')
        ->assertStatus(200);
});


it('user logged can create category', function () {

    actingAs(User::first())
        ->post('/categories', [
            'name' => 'Categoría 1',
        ])
        ->assertRedirect('/categories')
        ->assertSessionHas('success', 'La categoría se ha creado correctamente');
});


it('user logged can access to edit category page', function () {

    $user = User::first();

    $category = Category::create([
        'name' => 'Categoría 1',
        'slug' => 'Categoria-1',
    ]);

    actingAs($user)
        ->get("/categories/{$category->id}/edit")
        ->assertStatus(200);
});


it('user logged can access to edit category', function () {

    $user = User::first();

    $category = Category::create([
        'name' => 'Categoría 1',
        'slug' => 'Categoria-1',
    ]);


    actingAs($user)
        ->put("/categories/{$category->id}", [
            'name' => 'Categoría 2',
            'slug' => 'categoria-2'
        ])
        ->assertRedirect('/categories')
        ->assertSessionHas('success', 'La categoría se ha actualizado correctamente');
});




it('user logged can delete category', function () {

    $user = User::first();

    $category = Category::create([
        'name' => 'Categoria 1',
        'slug' => 'Categoria-1',
    ]);

    actingAs($user)
        ->delete("/categories/{$category->id}")
        ->assertRedirect('/categories')
        ->assertSessionHas('success', 'La categoría se ha eliminado correctamente');
});
