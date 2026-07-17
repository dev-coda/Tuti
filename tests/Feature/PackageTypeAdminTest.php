<?php

use App\Jobs\SyncProductDimensions;
use App\Models\PackageType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makePackageAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

it('lists and creates package types from admin', function () {
    $admin = makePackageAdmin();

    PackageType::create(['code' => 'S', 'name' => 'Pequeño', 'max_weight_kg' => 3, 'max_length_cm' => 30, 'max_width_cm' => 25, 'max_height_cm' => 15, 'active' => true]);

    $this->actingAs($admin)
        ->get(route('package-types.index'))
        ->assertOk()
        ->assertSee('Tamaños de Empaque')
        ->assertSee('Pequeño');

    $this->actingAs($admin)
        ->post(route('package-types.store'), [
            'code' => 'M',
            'name' => 'Mediano',
            'max_weight_kg' => 8,
            'max_length_cm' => 40,
            'max_width_cm' => 30,
            'max_height_cm' => 25,
            'position' => 2,
            'active' => '1',
        ])
        ->assertRedirect(route('package-types.index'));

    expect(PackageType::where('code', 'M')->exists())->toBeTrue();
});

it('dispatches the dimension sync job from admin', function () {
    $admin = makePackageAdmin();
    Bus::fake();

    $this->actingAs($admin)
        ->post(route('package-types.sync-dimensions'))
        ->assertRedirect(route('package-types.index'))
        ->assertSessionHas('success');

    Bus::assertDispatched(SyncProductDimensions::class);
});
