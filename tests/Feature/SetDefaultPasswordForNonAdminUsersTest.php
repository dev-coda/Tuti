<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function createUserWithoutPassword(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    DB::table('users')->where('id', $user->id)->update(['password' => '']);

    return $user->fresh();
}

it('sets the default password only for non-admin users without a password', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    $admin = User::factory()->create(['password' => Hash::make('admin-secret')]);
    $admin->assignRole('admin');

    $clientWithPassword = User::factory()->create(['password' => Hash::make('old-client')]);
    $sellerWithPassword = User::factory()->create(['password' => Hash::make('old-seller')]);
    $sellerWithPassword->assignRole('seller');

    $clientWithoutPassword = createUserWithoutPassword();
    $sellerWithoutPassword = createUserWithoutPassword();
    $sellerWithoutPassword->assignRole('seller');

    $this->artisan('users:set-default-password', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Set default password for 2 non-admin user(s) without an existing password.');

    expect(Hash::check('Tendero2026', $clientWithoutPassword->fresh()->password))->toBeTrue()
        ->and($clientWithoutPassword->fresh()->must_change_password)->toBeTrue()
        ->and(Hash::check('Tendero2026', $sellerWithoutPassword->fresh()->password))->toBeTrue()
        ->and($sellerWithoutPassword->fresh()->must_change_password)->toBeTrue()
        ->and(Hash::check('old-client', $clientWithPassword->fresh()->password))->toBeTrue()
        ->and(Hash::check('old-seller', $sellerWithPassword->fresh()->password))->toBeTrue()
        ->and(Hash::check('admin-secret', $admin->fresh()->password))->toBeTrue();
});

it('does not update admin users even when they have no password', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = createUserWithoutPassword();
    $admin->assignRole('admin');

    $this->artisan('users:set-default-password', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No non-admin users without a password were found.');

    expect($admin->fresh()->getRawOriginal('password'))->toBe('');
});

it('supports dry run without updating passwords', function () {
    $user = createUserWithoutPassword();

    $this->artisan('users:set-default-password', ['--dry-run' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Dry run: 1 non-admin user(s) without a password would receive the default password.');

    expect($user->fresh()->getRawOriginal('password'))->toBe('');
});

it('allows a custom password override', function () {
    $user = createUserWithoutPassword();

    $this->artisan('users:set-default-password', [
        '--password' => 'CustomPass123',
        '--force' => true,
    ])->assertSuccessful();

    expect(Hash::check('CustomPass123', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->must_change_password)->toBeTrue();
});
