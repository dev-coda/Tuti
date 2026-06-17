<?php

use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

afterEach(function () {
    \Mockery::close();
});

function mockRuteroForInvalidEmailTests(): void
{
    \Mockery::mock('alias:App\Repositories\UserRepository')
        ->shouldReceive('getCustomRuteroId')
        ->andReturn([
            'name' => 'Cliente Tronex',
            'routes' => [[
                'zone' => '301',
                'route' => 'R100',
                'day' => '1',
                'address' => 'Calle 10',
                'code' => 'RUT-1',
            ]],
        ]);
}

it('detects internal tuti emails as invalid client emails', function () {
    expect(User::isInvalidClientEmail('cliente@tuti.com'))->toBeTrue()
        ->and(User::isInvalidClientEmail('staff@tuti.com.co'))->toBeTrue()
        ->and(User::isInvalidClientEmail('admin@tuti'))->toBeTrue()
        ->and(User::isInvalidClientEmail('cliente@gmail.com'))->toBeFalse()
        ->and(User::isInvalidClientEmail(''))->toBeTrue()
        ->and(User::isInvalidClientEmail('not-an-email'))->toBeTrue();
});

it('redirects authenticated clients with invalid email to the data update form', function () {
    mockRuteroForInvalidEmailTests();

    $user = User::factory()->create([
        'email' => 'cliente.temp@tuti.com',
    ]);

    Zone::create([
        'user_id' => $user->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => '1',
        'address' => 'Calle 1',
        'code' => 'RUT-1',
    ]);

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('client-data-updates.client.edit'));

    $this->actingAs($user)
        ->get(route('client-data-updates.client.edit'))
        ->assertOk()
        ->assertSee('Es necesario actualizar tus datos de contacto')
        ->assertSee('Actualiza tus datos');
});

it('allows a client to submit a self-service data update request', function () {
    mockRuteroForInvalidEmailTests();

    $user = User::factory()->create([
        'name' => 'Cliente Tuti',
        'document' => '123456789',
        'email' => 'cliente.temp@tuti.com',
    ]);

    $zone = Zone::create([
        'user_id' => $user->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => '1',
        'address' => 'Calle 10',
        'code' => 'RUT-1',
    ]);

    $this->actingAs($user)
        ->post(route('client-data-updates.client.store'), [
            'document' => '123456789',
            'name' => 'Cliente Tuti',
            'business_name' => 'Mi Tienda',
            'email' => 'nuevo.correo@example.com',
            'phone' => '6011111111',
            'mobile_phone' => '3001111111',
            'whatsapp' => '3001111111',
            'address' => 'Calle 20',
            'city_name' => 'Bogotá',
        ])
        ->assertRedirect(route('client-data-updates.client.edit'))
        ->assertSessionHas('success');

    $request = \App\Models\ClientDataUpdateRequest::first();

    expect(\App\Models\ClientDataUpdateRequest::count())->toBe(1)
        ->and($request->zone_code)->toBe('301')
        ->and($request->route)->toBe('R100');
});

it('allows a client without a zone to submit a data update using getRutero', function () {
    mockRuteroForInvalidEmailTests();

    $user = User::factory()->create([
        'name' => 'Cliente Sin Zona',
        'document' => '555444333',
        'email' => 'cliente.temp@tuti.com',
    ]);

    $this->actingAs($user)
        ->get(route('client-data-updates.client.edit'))
        ->assertOk()
        ->assertSee('Actualiza tus datos')
        ->assertSee('R100');

    $this->actingAs($user)
        ->post(route('client-data-updates.client.store'), [
            'document' => '555444333',
            'name' => 'Cliente Sin Zona',
            'email' => 'correo.real@example.com',
            'address' => 'Calle 55',
        ])
        ->assertRedirect(route('client-data-updates.client.edit'))
        ->assertSessionHas('success');

    expect(\App\Models\ClientDataUpdateRequest::first())
        ->zone_code->toBe('301')
        ->route->toBe('R100')
        ->zone_id->toBeNull();
});

it('blocks magic link requests for users with invalid email', function () {
    User::factory()->create([
        'email' => 'cliente.temp@tuti.com',
    ]);

    $this->postJson(route('magic-link.send'), [
        'email' => 'cliente.temp@tuti.com',
    ])->assertStatus(422)
        ->assertJson([
            'success' => false,
            'requires_data_update' => true,
        ]);
});

it('redirects login to data update when the account has an invalid email', function () {
    mockRuteroForInvalidEmailTests();

    $user = User::factory()->create([
        'email' => 'cliente.temp@tuti.com',
        'password' => Hash::make('SecretPass1!'),
    ]);

    Zone::create([
        'user_id' => $user->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => '1',
        'address' => 'Calle 1',
        'code' => 'RUT-1',
    ]);

    $this->post(route('login'), [
        'email' => 'cliente.temp@tuti.com',
        'password' => 'SecretPass1!',
    ])->assertRedirect(route('client-data-updates.client.edit'));
});

it('rejects registration with internal tuti email', function () {
    $state = \App\Models\State::create(['name' => 'Cundinamarca']);
    $city = \App\Models\City::create([
        'name' => 'Bogotá',
        'state_id' => $state->id,
        'active' => true,
        'is_preferred' => true,
    ]);

    $this->post(route('register'), [
        'name' => 'Nuevo Cliente',
        'email' => 'nuevo@tuti.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'phone' => '3001234567',
        'city_id' => $city->id,
        'document' => '987654321',
        'terms_accepted' => '1',
    ])->assertSessionHasErrors('email');
});
