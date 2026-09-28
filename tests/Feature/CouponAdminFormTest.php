<?php

use App\Models\Brand;
use App\Models\Coupon;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function couponAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function couponFormPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'ADMIN' . strtoupper(substr(uniqid(), -5)),
        'name' => 'Admin form coupon',
        'type' => 'percentage',
        'value' => 10,
        'valid_from' => now()->format('Y-m-d H:i:s'),
        'valid_to' => now()->addMonth()->format('Y-m-d H:i:s'),
        'applies_to' => 'cart',
        'active' => 1,
        'apply_on_brand_vendor_discounts' => '0',
    ], $overrides);
}

it('loads the edit form with AJAX zone search instead of embedding every zone row', function () {
    $admin = couponAdmin();

    // Enough rows that a full multi-select regress would be obvious in HTML size/content.
    foreach (range(1, 25) as $i) {
        Zone::create([
            'route' => 'R' . $i,
            'zone' => (string) (100 + $i),
            'day' => 'Lunes',
            'address' => 'Addr ' . $i,
            'code' => 'ZCODE' . $i,
        ]);
    }

    $selected = Zone::query()->latest('id')->first();
    $coupon = Coupon::create([
        'code' => 'ZONEEDIT1',
        'name' => 'Zone edit coupon',
        'type' => 'percentage',
        'value' => 5,
        'valid_from' => Carbon::now()->subDay(),
        'valid_to' => Carbon::now()->addMonth(),
        'active' => true,
        'applies_to' => 'cart',
        'allowed_zone_ids' => [$selected->id],
        'allowed_zones' => [(string) $selected->zone],
    ]);

    $response = actingAs($admin)->get(route('coupons.edit', $coupon));

    $response->assertOk()
        ->assertSee('id="zone-ids-filter"', false)
        ->assertSee(route('coupons.search-zones', absolute: false), false)
        ->assertSee('name="allowed_zone_ids[]"', false)
        ->assertSee('ID: ' . $selected->id, false);

    // Must not dump one checkbox/option per zone row (only the preselected zone).
    expect(substr_count($response->getContent(), 'name="allowed_zone_ids[]"'))->toBe(1);
});

it('updates a coupon and can clear zone restrictions and deactivate it', function () {
    $admin = couponAdmin();
    $zone = Zone::create([
        'route' => 'R9',
        'zone' => '909',
        'day' => 'Lunes',
        'address' => 'Clear me',
        'code' => 'CLEAR1',
    ]);

    $coupon = Coupon::create([
        'code' => 'CLEARZ1',
        'name' => 'Clear zones coupon',
        'type' => 'percentage',
        'value' => 15,
        'valid_from' => Carbon::now()->subDay(),
        'valid_to' => Carbon::now()->addMonth(),
        'active' => true,
        'applies_to' => 'cart',
        'allowed_zone_ids' => [$zone->id],
        'allowed_zones' => ['909'],
        'allowed_routes' => ['R9'],
    ]);

    $payload = couponFormPayload([
        'code' => 'CLEARZ1',
        'name' => 'Cleared zones coupon',
        'value' => 12,
    ]);
    unset($payload['active']); // unchecked checkbox is omitted from the form post

    actingAs($admin)->put(route('coupons.update', $coupon), $payload)
        ->assertRedirect(route('coupons.index'));

    $coupon->refresh();

    expect($coupon->name)->toBe('Cleared zones coupon')
        ->and((float) $coupon->value)->toBe(12.0)
        ->and($coupon->active)->toBeFalse()
        ->and($coupon->allowed_zone_ids)->toBeNull()
        ->and($coupon->allowed_zones)->toBeNull()
        ->and($coupon->allowed_routes)->toBeNull();
});

it('updates a brand coupon and clears applies_to_ids when switched to cart', function () {
    $admin = couponAdmin();
    $vendor = Vendor::create([
        'name' => 'Vendor ' . uniqid(),
        'slug' => 'vendor-' . uniqid(),
        'vendor_type' => 'V',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Brand ' . uniqid(),
        'slug' => 'brand-' . uniqid(),
        'vendor_id' => $vendor->id,
    ]);

    $coupon = Coupon::create([
        'code' => 'BRAND2CART',
        'name' => 'Brand coupon',
        'type' => 'percentage',
        'value' => 8,
        'valid_from' => Carbon::now()->subDay(),
        'valid_to' => Carbon::now()->addMonth(),
        'active' => true,
        'applies_to' => 'brand',
        'applies_to_ids' => [$brand->id],
    ]);

    actingAs($admin)->put(route('coupons.update', $coupon), couponFormPayload([
        'code' => 'BRAND2CART',
        'name' => 'Now cart coupon',
        'applies_to' => 'cart',
        'active' => 1,
    ]))->assertRedirect(route('coupons.index'));

    $coupon->refresh();

    expect($coupon->applies_to)->toBe('cart')
        ->and($coupon->applies_to_ids)->toBeNull();
});

it('searches zones for the coupon form without loading the full table', function () {
    $admin = couponAdmin();
    $zone = Zone::create([
        'route' => 'SEARCHR',
        'zone' => '777',
        'day' => 'Martes',
        'address' => 'Unique Search Address XYZ',
        'code' => 'SRCH1',
    ]);

    actingAs($admin)
        ->get(route('coupons.search-zones', ['q' => 'Unique Search Address XYZ']))
        ->assertOk()
        ->assertJsonFragment([
            'id' => $zone->id,
        ]);
});
