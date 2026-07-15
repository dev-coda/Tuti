<?php

use App\Models\MagicLoginCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('logs in regardless of the email casing typed by the user', function () {
    $user = User::factory()->create([
        'email' => 'cliente@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    post(route('login'), [
        'email' => 'CLIENTE@Example.COM',
        'password' => 'secret-password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

it('logs in when the stored email has uppercase characters', function () {
    $user = User::factory()->create([
        'email' => 'Cliente.Mayusculas@Example.com',
        'password' => Hash::make('secret-password'),
    ]);

    post(route('login'), [
        'email' => 'cliente.mayusculas@example.com',
        'password' => 'secret-password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

it('still rejects a wrong password', function () {
    User::factory()->create([
        'email' => 'cliente@example.com',
        'password' => Hash::make('secret-password'),
    ]);

    post(route('login'), [
        'email' => 'CLIENTE@EXAMPLE.COM',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('sends and verifies magic login codes case-insensitively', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'Tienda.Sol@Example.com',
        'password' => Hash::make('secret-password'),
    ]);

    postJson(route('magic-link.send'), ['email' => 'TIENDA.SOL@EXAMPLE.COM'])
        ->assertOk()
        ->assertJson(['success' => true]);

    $code = MagicLoginCode::where('email', 'tienda.sol@example.com')->latest()->first();
    expect($code)->not->toBeNull();

    postJson(route('magic-link.verify'), [
        'email' => 'Tienda.Sol@example.COM',
        'code' => $code->code,
    ])->assertOk()->assertJson(['success' => true]);

    $this->assertAuthenticatedAs($user);
});
