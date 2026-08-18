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

it('allows admin to create a supervisor with multiple zone assignments', function () {
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
            ['zone' => '101'],
            ['zone' => '102'],
            ['zone' => ''], // ignored empty row
        ],
    ])->assertRedirect(route('supervisors.index'));

    $user = User::where('email', 'supervisor.test@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('supervisor'))->toBeTrue();
    expect((int) $user->zone)->toBe(101);
    expect($user->supervisorRoutes)->toHaveCount(2);
    expect($user->supervisorRoutes->pluck('zone')->sort()->values()->all())->toBe(['101', '102']);
    expect($user->supervisedZones())->toEqualCanonicalizing(['101', '102']);
});

it('allows admin to sync supervisor zone assignments on update', function () {
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
        'route' => null,
    ]);

    actingAs($admin);

    put(route('supervisors.update', $supervisor), [
        'name' => 'Supervisor Edit',
        'email' => 'supervisor.edit@example.com',
        'zone' => 50,
        'assignments' => [
            ['zone' => '60'],
            ['zone' => '70'],
        ],
    ])->assertRedirect(route('supervisors.index'));

    $supervisor->refresh();

    expect($supervisor->supervisorRoutes)->toHaveCount(2);
    expect(
        $supervisor->supervisorRoutes
            ->pluck('zone')
            ->sort()
            ->values()
            ->all()
    )->toBe(['60', '70']);
});

it('allows admin to assign specific routes and whole zones to a supervisor', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    post(route('supervisors.store'), [
        'name' => 'Supervisor Rutas',
        'email' => 'supervisor.rutas@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'assignments' => [
            ['zone' => '101', 'route' => '0001'],
            ['zone' => '101', 'route' => '0002'],
            ['zone' => '202', 'route' => ''],
            ['zone' => ''], // ignored empty row
        ],
    ])->assertRedirect(route('supervisors.index'));

    $user = User::where('email', 'supervisor.rutas@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('supervisor'))->toBeTrue()
        ->and($user->supervisorRoutes)->toHaveCount(3)
        ->and($user->supervisedZones())->toEqualCanonicalizing(['101', '202'])
        ->and($user->supervisedCoverages())->toEqualCanonicalizing([
            ['zone' => '101', 'route' => '0001'],
            ['zone' => '101', 'route' => '0002'],
            ['zone' => '202', 'route' => null],
        ]);
});

it('collapses specific routes when the same zone is assigned as a whole', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    post(route('supervisors.store'), [
        'name' => 'Supervisor Zona Completa',
        'email' => 'supervisor.zona@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'assignments' => [
            ['zone' => '101', 'route' => '0001'],
            ['zone' => '101', 'route' => ''],
        ],
    ])->assertRedirect(route('supervisors.index'));

    $user = User::where('email', 'supervisor.zona@example.com')->first();

    expect($user->supervisorRoutes)->toHaveCount(1)
        ->and($user->supervisorRoutes->first()->zone)->toBe('101')
        ->and($user->supervisorRoutes->first()->resolvedRoute())->toBeNull()
        ->and($user->supervisedCoverages())->toEqualCanonicalizing([
            ['zone' => '101', 'route' => null],
        ]);
});

it('rejects non-numeric supervisor zone assignments', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin);

    post(route('supervisors.store'), [
        'name' => 'Supervisor Invalid',
        'email' => 'supervisor.invalid@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'assignments' => [
            ['zone' => 'ABC'],
        ],
    ])->assertSessionHasErrors(['assignments']);

    expect(User::where('email', 'supervisor.invalid@example.com')->exists())->toBeFalse();
});
