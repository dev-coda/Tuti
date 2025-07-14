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
