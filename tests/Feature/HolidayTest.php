<?php

use App\Models\Holiday;
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


it('user not logged cannot access to holiday page', function () {
    $response = $this->get('/admin/holidays');

    $response->assertStatus(302)
        ->assertRedirect('/formulario');
});




it('user logged can access to holiday page', function () {

    actingAs(User::first())
        ->get('/holidays')
        ->assertStatus(200);
});


it('user logged can access to create holiday page', function () {

    actingAs(User::first())
        ->get('/holidays/create')
        ->assertStatus(200);
});


it('user logged can create holiday', function () {

    actingAs(User::first())
        ->post('/holidays', [
            'name' => 'Festivo 1',
            'date' => '2023-01-01'
        ])
        ->assertRedirect('/holidays');
});


it('user logged can access to edit holiday page', function () {

    $user = User::first();

    $holiday = Holiday::create([
        'name' => 'Categoría 1',
        'date' => '2023-01-01'
    ]);

    actingAs($user)
        ->get("/holidays/{$holiday->id}/edit")
        ->assertStatus(200);
});


it('user logged can access to edit holiday', function () {

    $user = User::first();

    $holiday = holiday::create([
        'name' => 'Categoría 1',
        'date' => '2023-01-01'
    ]);


    actingAs($user)
        ->put("/holidays/{$holiday->id}", [
            'name' => 'Categoría 2',
            'date' => '2023-01-10'
        ])
        ->assertRedirect('/holidays');
});




it('user logged can delete holiday', function () {

    $user = User::first();

    $holiday = Holiday::create([
        'name' => 'Categoria 1',
        'date' => '2023-01-01'
    ]);

    actingAs($user)
        ->delete("/holidays/{$holiday->id}")
        ->assertRedirect('/holidays');
});
