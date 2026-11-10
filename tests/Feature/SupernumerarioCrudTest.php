<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supernumerario', 'guard_name' => 'web']);
});

it('allows admin to create a supernumerario without a zone', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    post(route('supernumerarios.store'), [
        'name' => 'Supernumerario Test',
        'email' => 'super.num@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect(route('supernumerarios.index'));

    $user = User::where('email', 'super.num@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('supernumerario'))->toBeTrue()
        ->and($user->isStaffSeller())->toBeTrue()
        ->and($user->coversAllZones())->toBeTrue();
});

it('lets an admin limit a supernumerario to one zone', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    post(route('supernumerarios.store'), [
        'name' => 'Supernumerario Zona',
        'email' => 'super.zone@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'covers_all_zones' => '0',
    ])->assertSessionHasErrors('zone');

    post(route('supernumerarios.store'), [
        'name' => 'Supernumerario Zona',
        'email' => 'super.zone@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'covers_all_zones' => '0',
        'zone' => 409,
    ])->assertRedirect(route('supernumerarios.index'));

    $user = User::where('email', 'super.zone@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->coversAllZones())->toBeFalse()
        ->and((string) $user->zone)->toBe('409');
});

it('allows admin to update a supernumerario', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $staff = User::factory()->create([
        'name' => 'Supernumerario Edit',
        'email' => 'super.edit@example.com',
    ]);
    $staff->assignRole('supernumerario');

    actingAs($admin);

    put(route('supernumerarios.update', $staff), [
        'name' => 'Supernumerario Editado',
        'email' => 'super.edit@example.com',
    ])->assertRedirect(route('supernumerarios.index'));

    expect($staff->fresh()->name)->toBe('Supernumerario Editado')
        ->and($staff->fresh()->hasRole('supernumerario'))->toBeTrue();
});
