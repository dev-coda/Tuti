<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
});

it('allows admin to create a supervisor from admin form', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    post(route('supervisors.store'), [
        'name' => 'Supervisor Test',
        'email' => 'supervisor.test@example.com',
        'zone' => 101,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect(route('supervisors.index'));

    $user = User::where('email', 'supervisor.test@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('supervisor'))->toBeTrue();
    expect((int) $user->zone)->toBe(101);
});
