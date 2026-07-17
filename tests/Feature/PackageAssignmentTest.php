<?php

use App\Models\PackageType;
use App\Services\Shipping\PackageAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedPackageTypes(): void
{
    PackageType::create(['code' => 'S', 'name' => 'Pequeño', 'max_weight_kg' => 3, 'max_length_cm' => 30, 'max_width_cm' => 25, 'max_height_cm' => 15, 'position' => 1, 'active' => true]);
    PackageType::create(['code' => 'M', 'name' => 'Mediano', 'max_weight_kg' => 8, 'max_length_cm' => 40, 'max_width_cm' => 30, 'max_height_cm' => 25, 'position' => 2, 'active' => true]);
    PackageType::create(['code' => 'L', 'name' => 'Grande', 'max_weight_kg' => 15, 'max_length_cm' => 60, 'max_width_cm' => 40, 'max_height_cm' => 40, 'position' => 3, 'active' => true]);
}

it('assigns the smallest package that fits the order', function () {
    seedPackageTypes();

    $packages = app(PackageAssignmentService::class)->assignForItems(collect([
        ['quantity' => 2, 'weight_kg' => 0.5, 'length_cm' => 10, 'width_cm' => 8, 'height_cm' => 5],
    ]));

    expect($packages)->toHaveCount(1);
    expect($packages[0]['code'])->toBe('S');
    expect($packages[0]['count'])->toBe(1);
});

it('upgrades the package when weight exceeds the smaller sizes', function () {
    seedPackageTypes();

    // Tiny volume but 7kg: S (3kg) is out, M (8kg) fits.
    $packages = app(PackageAssignmentService::class)->assignForItems(collect([
        ['quantity' => 7, 'weight_kg' => 1, 'length_cm' => 5, 'width_cm' => 5, 'height_cm' => 5],
    ]));

    expect($packages[0]['code'])->toBe('M');
    expect($packages[0]['count'])->toBe(1);
});

it('respects single item dimensions even when volume is small', function () {
    seedPackageTypes();

    // A 50cm-long item cannot enter S/M regardless of total volume.
    $packages = app(PackageAssignmentService::class)->assignForItems(collect([
        ['quantity' => 1, 'weight_kg' => 1, 'length_cm' => 50, 'width_cm' => 10, 'height_cm' => 10],
    ]));

    expect($packages[0]['code'])->toBe('L');
});

it('splits into multiple packages when the order exceeds the largest one', function () {
    seedPackageTypes();

    // 40kg total: L holds 15kg -> 3 packages by weight.
    $packages = app(PackageAssignmentService::class)->assignForItems(collect([
        ['quantity' => 40, 'weight_kg' => 1, 'length_cm' => 10, 'width_cm' => 10, 'height_cm' => 10],
    ]));

    expect($packages[0]['code'])->toBe('L');
    expect($packages[0]['count'])->toBe(3);
});

it('ignores inactive package types', function () {
    seedPackageTypes();
    PackageType::where('code', 'S')->update(['active' => false]);

    $packages = app(PackageAssignmentService::class)->assignForItems(collect([
        ['quantity' => 1, 'weight_kg' => 0.5, 'length_cm' => 10, 'width_cm' => 8, 'height_cm' => 5],
    ]));

    expect($packages[0]['code'])->toBe('M');
});

it('returns empty when no package types are configured', function () {
    $packages = app(PackageAssignmentService::class)->assignForItems(collect([
        ['quantity' => 1, 'weight_kg' => 0.5, 'length_cm' => 10, 'width_cm' => 8, 'height_cm' => 5],
    ]));

    expect($packages)->toBe([]);
});

it('assigns the smallest package for orders without dimension data', function () {
    seedPackageTypes();

    $packages = app(PackageAssignmentService::class)->assignForItems(collect([
        ['quantity' => 3, 'weight_kg' => 0, 'length_cm' => 0, 'width_cm' => 0, 'height_cm' => 0],
    ]));

    expect($packages[0]['code'])->toBe('S');
    expect($packages[0]['count'])->toBe(1);
});
