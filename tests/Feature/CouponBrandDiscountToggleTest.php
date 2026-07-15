<?php

use App\Models\Brand;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Services\CouponDiscountService;
use App\Services\CouponService;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

/*
|--------------------------------------------------------------------------
| Coupon toggle: allow / exempt from brand & vendor discounts
|--------------------------------------------------------------------------
| When apply_on_brand_vendor_discounts is FALSE the coupon must not touch
| cart lines that already carry a brand or vendor (descuento directo)
| discount; those lines keep their existing discount. Product-level
| discounts are unaffected by the toggle.
*/

function bvToggleScaffold(array $brandOverrides = [], array $vendorOverrides = []): array
{
    $tax = Tax::create(['name' => 'Tax ' . uniqid(), 'tax' => 0]);

    $vendor = Vendor::create(array_merge([
        'name' => 'Vendor ' . uniqid(),
        'slug' => 'vendor-' . uniqid(),
        'vendor_type' => 'V',
        'minimum_purchase' => 0,
        'active' => 1,
    ], $vendorOverrides));

    $brand = Brand::create(array_merge([
        'name' => 'Brand ' . uniqid(),
        'slug' => 'brand-' . uniqid(),
        'vendor_id' => $vendor->id,
    ], $brandOverrides));

    $zone = Zone::create([
        'route' => 'R' . substr(uniqid(), -3),
        'zone' => 'Z' . substr(uniqid(), -3),
        'day' => 'Lunes',
        'address' => 'Test',
        'code' => 'T' . substr(uniqid(), -4),
    ]);

    $user = User::factory()->create();
    $zone->update(['user_id' => $user->id]);

    return compact('tax', 'vendor', 'brand', 'zone', 'user');
}

function bvToggleProduct(Brand $brand, Tax $tax, array $overrides = []): Product
{
    return Product::create(array_merge([
        'name' => 'Product ' . uniqid(),
        'slug' => 'product-' . uniqid(),
        'description' => '',
        'short_description' => '',
        'sku' => 'SKU-' . strtoupper(substr(uniqid(), -8)),
        'active' => 1,
        'price' => 1000,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
        'calculate_package_price' => false,
    ], $overrides));
}

function bvToggleCoupon(array $attrs = []): Coupon
{
    return Coupon::create(array_merge([
        'code' => 'BV' . strtoupper(substr(uniqid(), -6)),
        'name' => 'Toggle Coupon',
        'type' => 'percentage',
        'value' => 20,
        'valid_from' => Carbon::now()->subDay(),
        'valid_to' => Carbon::now()->addMonth(),
        'active' => true,
        'applies_to' => 'cart',
        'applies_to_ids' => null,
    ], $attrs));
}

it('keeps applying coupons over brand discounts by default (toggle on)', function () {
    ['tax' => $tax, 'brand' => $brand, 'user' => $user] = bvToggleScaffold(['discount' => 12]);
    $p = bvToggleProduct($brand, $tax);

    $coupon = bvToggleCoupon(['value' => 20]);
    expect($coupon->appliesOverBrandVendorDiscounts())->toBeTrue();

    $result = app(CouponDiscountService::class)->applyCouponDiscountToProducts(
        $coupon, $user, collect([['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null]]), false
    );

    $mod = $result['modified_products'][0];
    // Historical behavior: coupon (20%) beats brand (12%)
    expect($mod['applied_discount_percentage'])->toBe(20.0)
        ->and($mod['discount_source'])->toBe('coupon');
});

it('exempt coupon does not apply on a brand-discounted product, which keeps its brand discount', function () {
    ['tax' => $tax, 'brand' => $brand, 'user' => $user] = bvToggleScaffold(['discount' => 12]);
    $p = bvToggleProduct($brand, $tax);

    $coupon = bvToggleCoupon(['value' => 20, 'apply_on_brand_vendor_discounts' => false]);

    $result = app(CouponDiscountService::class)->applyCouponDiscountToProducts(
        $coupon, $user, collect([['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null]]), false
    );

    // The whole cart is brand-discounted, so the coupon cannot be applied at all.
    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toContain('descuento de marca o proveedor');
});

it('exempt coupon applies only to lines without brand/vendor discounts in a mixed cart', function () {
    ['tax' => $tax, 'vendor' => $vendor, 'brand' => $discountedBrand, 'user' => $user] = bvToggleScaffold(['discount' => 12]);
    $plainBrand = Brand::create([
        'name' => 'Plain ' . uniqid(),
        'slug' => 'plain-' . uniqid(),
        'vendor_id' => $vendor->id,
    ]);

    $brandDiscounted = bvToggleProduct($discountedBrand, $tax, ['price' => 1000]);
    $plain = bvToggleProduct($plainBrand, $tax, ['price' => 1000]);

    $coupon = bvToggleCoupon(['value' => 20, 'apply_on_brand_vendor_discounts' => false]);
    $cart = collect([
        ['product_id' => $brandDiscounted->id, 'quantity' => 1, 'variation_id' => null],
        ['product_id' => $plain->id, 'quantity' => 1, 'variation_id' => null],
    ]);

    $result = app(CouponDiscountService::class)->applyCouponDiscountToProducts($coupon, $user, $cart, false);

    expect($result['success'])->toBeTrue();

    $mods = collect($result['modified_products']);
    $discountedLine = $mods->firstWhere('product_id', $brandDiscounted->id);
    $plainLine = $mods->firstWhere('product_id', $plain->id);

    // Brand-discounted line keeps its 12% brand discount, no coupon contribution.
    expect($discountedLine['applied_discount_percentage'])->toBe(12.0)
        ->and($discountedLine['discount_source'])->toBe('existing')
        ->and((float) $discountedLine['coupon_contribution'])->toBe(0.0);

    // Plain line receives the coupon.
    expect($plainLine['applied_discount_percentage'])->toBe(20.0)
        ->and($plainLine['discount_source'])->toBe('coupon');

    // Total coupon discount comes only from the plain line: 20% of 1000.
    expect((float) $result['total_coupon_discount'])->toBe(200.0);
});

it('exempt coupon still competes with product-level discounts (toggle only covers brand/vendor)', function () {
    ['tax' => $tax, 'brand' => $brand, 'user' => $user] = bvToggleScaffold();
    // Discount lives on the product itself, not the brand or vendor.
    $p = bvToggleProduct($brand, $tax, ['price' => 1000, 'discount' => 12]);

    $coupon = bvToggleCoupon(['value' => 20, 'apply_on_brand_vendor_discounts' => false]);

    $result = app(CouponDiscountService::class)->applyCouponDiscountToProducts(
        $coupon, $user, collect([['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null]]), false
    );

    $mod = $result['modified_products'][0];
    expect($result['success'])->toBeTrue()
        ->and($mod['applied_discount_percentage'])->toBe(20.0)
        ->and($mod['discount_source'])->toBe('coupon');
});

it('exempt coupon skips vendor-discounted products too', function () {
    ['tax' => $tax, 'brand' => $brand, 'user' => $user] = bvToggleScaffold([], ['discount' => 15]);
    $p = bvToggleProduct($brand, $tax, ['price' => 1000]);

    $coupon = bvToggleCoupon(['value' => 20, 'apply_on_brand_vendor_discounts' => false]);

    $result = app(CouponService::class)->applyCouponToCart(
        $coupon, $user, collect([['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null]]), false
    );

    expect($result['success'])->toBeFalse()
        ->and($result['discount_amount'])->toBe(0);
});

it('cart-wide fixed coupon base excludes brand-discounted lines when exempt', function () {
    ['tax' => $tax, 'vendor' => $vendor, 'brand' => $discountedBrand, 'user' => $user] = bvToggleScaffold(['discount' => 10]);
    $plainBrand = Brand::create([
        'name' => 'Plain ' . uniqid(),
        'slug' => 'plain-' . uniqid(),
        'vendor_id' => $vendor->id,
    ]);

    $brandDiscounted = bvToggleProduct($discountedBrand, $tax, ['price' => 1000]);
    $plain = bvToggleProduct($plainBrand, $tax, ['price' => 1000]);

    // Fixed 1500 gets capped to the eligible base (1000, only the plain line).
    $coupon = bvToggleCoupon(['type' => 'fixed_amount', 'value' => 1500, 'apply_on_brand_vendor_discounts' => false]);
    $cart = collect([
        ['product_id' => $brandDiscounted->id, 'quantity' => 1, 'variation_id' => null],
        ['product_id' => $plain->id, 'quantity' => 1, 'variation_id' => null],
    ]);

    $result = app(CouponService::class)->applyCouponToCart($coupon, $user, $cart, false);

    expect($result['success'])->toBeTrue()
        ->and((float) $result['discount_amount'])->toBe(1000.0);

    // With the toggle on, the full cart (2000) is eligible so the whole 1500 applies.
    $stackable = bvToggleCoupon(['type' => 'fixed_amount', 'value' => 1500]);
    $result = app(CouponService::class)->applyCouponToCart($stackable, $user, $cart, false);

    expect($result['success'])->toBeTrue()
        ->and((float) $result['discount_amount'])->toBe(1500.0);
});

it('admin can create and update the brand/vendor discount toggle from the coupon form', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $payload = [
        'code' => 'TOGGLE1',
        'name' => 'Toggle test',
        'type' => 'percentage',
        'value' => 10,
        'valid_from' => now()->format('Y-m-d H:i:s'),
        'valid_to' => now()->addMonth()->format('Y-m-d H:i:s'),
        'applies_to' => 'cart',
        'active' => 1,
        'apply_on_brand_vendor_discounts' => '0',
    ];

    actingAs($admin)->post(route('coupons.store'), $payload)->assertRedirect(route('coupons.index'));

    $coupon = Coupon::where('code', 'TOGGLE1')->firstOrFail();
    expect($coupon->apply_on_brand_vendor_discounts)->toBeFalse();

    // Re-enable via update.
    actingAs($admin)->put(route('coupons.update', $coupon), array_merge($payload, [
        'apply_on_brand_vendor_discounts' => '1',
    ]))->assertRedirect(route('coupons.index'));

    expect($coupon->fresh()->apply_on_brand_vendor_discounts)->toBeTrue();
});

it('defaults the toggle to allowed when the field is not sent', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin)->post(route('coupons.store'), [
        'code' => 'TOGGLE2',
        'name' => 'Legacy payload',
        'type' => 'percentage',
        'value' => 10,
        'valid_from' => now()->format('Y-m-d H:i:s'),
        'valid_to' => now()->addMonth()->format('Y-m-d H:i:s'),
        'applies_to' => 'cart',
        'active' => 1,
    ])->assertRedirect(route('coupons.index'));

    expect(Coupon::where('code', 'TOGGLE2')->firstOrFail()->apply_on_brand_vendor_discounts)->toBeTrue();
});
