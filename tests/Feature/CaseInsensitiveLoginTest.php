<?php

use App\Models\MagicLoginCode;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Setting::updateOrCreate(['key' => 'mailgun_domain'], ['name' => 'Mailgun Domain', 'value' => 'mg.example.com', 'show' => true]);
    Setting::updateOrCreate(['key' => 'mailgun_secret'], ['name' => 'Mailgun Secret', 'value' => 'key-test', 'show' => true]);
    Setting::updateOrCreate(['key' => 'mailgun_endpoint'], ['name' => 'Mailgun Endpoint', 'value' => 'api.mailgun.net', 'show' => true]);
    Setting::updateOrCreate(['key' => 'mail_from_address'], ['name' => 'From', 'value' => 'noreply@mg.example.com', 'show' => true]);
    Setting::updateOrCreate(['key' => 'mail_from_name'], ['name' => 'From Name', 'value' => 'Tuti', 'show' => true]);
});

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

    Mail::assertSent(\App\Mail\MagicLoginCodeMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });

    postJson(route('magic-link.verify'), [
        'email' => 'Tienda.Sol@example.COM',
        'code' => $code->code,
    ])->assertOk()->assertJson(['success' => true]);

    $this->assertAuthenticatedAs($user);
});

it('sends password reset links case-insensitively', function () {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'Tienda.Reset@Example.com',
        'password' => Hash::make('secret-password'),
    ]);

    post(route('password.email'), [
        'email' => 'tienda.reset@example.com',
    ])->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});
