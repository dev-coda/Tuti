<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
});

it('redirects users with must_change_password to the forced change page after login', function () {
    $user = User::factory()->create([
        'password' => Hash::make(User::defaultPassword()),
        'must_change_password' => true,
    ]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => User::defaultPassword(),
    ])->assertRedirect(route('password.forced-change'));

    $this->actingAs($user->fresh())
        ->get(route('clients.orders.index'))
        ->assertRedirect(route('password.forced-change'));
});

it('flags default password on login and forces password change', function () {
    $user = User::factory()->create([
        'password' => Hash::make(User::defaultPassword()),
        'must_change_password' => false,
    ]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => User::defaultPassword(),
    ])->assertRedirect(route('password.forced-change'));

    expect($user->fresh()->must_change_password)->toBeTrue();
});

it('allows access after changing the forced password', function () {
    $user = User::factory()->create([
        'password' => Hash::make(User::defaultPassword()),
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->post(route('password.forced-change.store'), [
            'password' => 'NewSecurePass1!',
            'password_confirmation' => 'NewSecurePass1!',
        ])
        ->assertRedirect('/');

    $user->refresh();
    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('NewSecurePass1!', $user->password))->toBeTrue();

    $this->actingAs($user)
        ->get(route('clients.orders.index'))
        ->assertOk();
});

it('rejects setting the default password as the new password', function () {
    $user = User::factory()->create([
        'password' => Hash::make(User::defaultPassword()),
        'must_change_password' => true,
    ]);

    $this->actingAs($user)
        ->from(route('password.forced-change'))
        ->post(route('password.forced-change.store'), [
            'password' => User::defaultPassword(),
            'password_confirmation' => User::defaultPassword(),
        ])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->must_change_password)->toBeTrue();
});

it('does not force password change for admin users', function () {
    $admin = User::factory()->create([
        'password' => Hash::make(User::defaultPassword()),
        'must_change_password' => true,
    ]);
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('password.forced-change'))
        ->assertRedirect('/');

    $this->actingAs($admin)
        ->get(route('clients.orders.index'))
        ->assertOk();
});

it('does not force password change when logging in with a custom password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('MyOwnPassword1!'),
        'must_change_password' => false,
    ]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'MyOwnPassword1!',
    ])->assertRedirect('/');

    expect($user->fresh()->must_change_password)->toBeFalse();
});

it('sets must_change_password when assigning the default password via command', function () {
    DB::table('users')->where('id', User::factory()->create()->id)->update(['password' => '']);

    $user = User::first();

    $this->artisan('users:set-default-password', ['--force' => true])
        ->assertSuccessful();

    expect($user->fresh()->must_change_password)->toBeTrue();
});
