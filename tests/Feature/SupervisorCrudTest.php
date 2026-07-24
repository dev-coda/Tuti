<?php

use App\Models\SupervisorRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
});

it('allows admin to create a supervisor with multiple route assignments', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    post(route('supervisors.store'), [
        'name' => 'Supervisor Test',
        'email' => 'supervisor.test@example.com',
        'zone' => 101,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'assignments' => [
            ['zone' => '101', 'route' => '0001'],
            ['zone' => '102', 'route' => '0002'],
            ['zone' => '', 'route' => ''], // ignored empty row
        ],
    ])->assertRedirect(route('supervisors.index'));

    $user = User::where('email', 'supervisor.test@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('supervisor'))->toBeTrue();
    expect((int) $user->zone)->toBe(101);
    expect($user->supervisorRoutes)->toHaveCount(2);
    expect($user->supervisorRoutes->pluck('route')->sort()->values()->all())->toBe(['0001', '0002']);
    expect($user->supervisedZones())->toEqualCanonicalizing(['101', '102']);
});

it('allows admin to sync supervisor route assignments on update', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $supervisor = User::factory()->create([
        'name' => 'Supervisor Edit',
        'email' => 'supervisor.edit@example.com',
        'zone' => 50,
    ]);
    $supervisor->assignRole('supervisor');
    SupervisorRoute::create([
        'user_id' => $supervisor->id,
        'zone' => '50',
        'route' => '0001',
    ]);

    actingAs($admin);

    put(route('supervisors.update', $supervisor), [
        'name' => 'Supervisor Edit',
        'email' => 'supervisor.edit@example.com',
        'zone' => 50,
        'assignments' => [
            ['zone' => '60', 'route' => '0011'],
            ['zone' => '70', 'route' => '0022'],
        ],
    ])->assertRedirect(route('supervisors.index'));

    $supervisor->refresh();

    expect($supervisor->supervisorRoutes)->toHaveCount(2);
    expect(
        $supervisor->supervisorRoutes
            ->map(fn ($row) => $row->zone . '|' . $row->route)
            ->sort()
            ->values()
            ->all()
    )->toBe(['60|0011', '70|0022']);
});

it('rejects half-filled supervisor route assignments', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    post(route('supervisors.store'), [
        'name' => 'Supervisor Invalid',
        'email' => 'supervisor.invalid@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'assignments' => [
            ['zone' => '101', 'route' => ''],
        ],
    ])->assertSessionHasErrors(['assignments']);

    expect(User::where('email', 'supervisor.invalid@example.com')->exists())->toBeFalse();
});
