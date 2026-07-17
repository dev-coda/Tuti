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
| Coupon toggle: stack on top of brand & vendor discounts
|--------------------------------------------------------------------------
| By default (apply_on_brand_vendor_discounts = FALSE) coupons do NOT stack
| with brand/vendor (descuento directo) discounts: the best discount wins
| per cart line. When the toggle is explicitly enabled the coupon is applied
| ON TOP of the existing brand/vendor discount. Product-level discounts
| always use best-of regardless of the toggle.
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

it('does not stack by default: the better coupon replaces the brand discount', function () {
    ['tax' => $tax, 'brand' => $brand, 'user' => $user] = bvToggleScaffold(['discount' => 12]);
    $p = bvToggleProduct($brand, $tax);

    $coupon = bvToggleCoupon(['value' => 20]);
    expect($coupon->appliesOverBrandVendorDiscounts())->toBeFalse();

    $result = app(CouponDiscountService::class)->applyCouponDiscountToProducts(
        $coupon, $user, collect([['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null]]), false
    );

    $mod = $result['modified_products'][0];
    // Best discount wins: coupon (20%) beats brand (12%) — no 32% stacking.
    expect($mod['applied_discount_percentage'])->toBe(20.0)
        ->and($mod['discount_source'])->toBe('coupon')
        ->and((float) $result['total_coupon_discount'])->toBe(80.0); // incremental part over the brand 12%
});

it('does not stack by default: a better brand discount is kept over the coupon', function () {
    ['tax' => $tax, 'brand' => $brand, 'user' => $user] = bvToggleScaffold(['discount' => 25]);
    $p = bvToggleProduct($brand, $tax);

    $coupon = bvToggleCoupon(['value' => 10]);

    $result = app(CouponDiscountService::class)->applyCouponDiscountToProducts(
        $coupon, $user, collect([['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null]]), false
    );

    $mod = $result['modified_products'][0];
    expect($result['success'])->toBeTrue()
        ->and($mod['applied_discount_percentage'])->toBe(25.0)
        ->and($mod['discount_source'])->toBe('existing')
        ->and((float) $mod['coupon_contribution'])->toBe(0.0);
});

it('stacks a percentage coupon on top of a brand discount when the toggle is enabled', function () {
    ['tax' => $tax, 'brand' => $brand, 'user' => $user] = bvToggleScaffold(['discount' => 12]);
    $p = bvToggleProduct($brand, $tax, ['price' => 1000]);

    $coupon = bvToggleCoupon(['value' => 20, 'apply_on_brand_vendor_discounts' => true]);

    $result = app(CouponDiscountService::class)->applyCouponDiscountToProducts(
        $coupon, $user, collect([['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null]]), false
    );

    $mod = $result['modified_products'][0];
    // 12% brand + 20% coupon = 32% total; the coupon contributed 200 of it.
    expect($mod['applied_discount_percentage'])->toBe(32.0)
        ->and($mod['discount_source'])->toBe('coupon')
        ->and((float) $mod['coupon_contribution'])->toBe(200.0)
        ->and((float) $mod['line_savings'])->toBe(320.0);
});

it('stacks on top of vendor discounts too and clamps the combined percentage at 100', function () {
    ['tax' => $tax, 'brand' => $brand, 'user' => $user] = bvToggleScaffold([], ['discount' => 90]);
    $p = bvToggleProduct($brand, $tax, ['price' => 1000]);

    $coupon = bvToggleCoupon(['value' => 20, 'apply_on_brand_vendor_discounts' => true]);

    $result = app(CouponDiscountService::class)->applyCouponDiscountToProducts(
        $coupon, $user, collect([['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null]]), false
    );

    $mod = $result['modified_products'][0];
    expect($mod['applied_discount_percentage'])->toBe(100.0)
        ->and((float) $mod['coupon_contribution'])->toBe(100.0); // only the 10% left of room
});

it('never stacks over product-level discounts even with the toggle enabled', function () {
    ['tax' => $tax, 'brand' => $brand, 'user' => $user] = bvToggleScaffold();
    // Discount lives on the product itself, not the brand or vendor.
    $p = bvToggleProduct($brand, $tax, ['price' => 1000, 'discount' => 12]);

    $coupon = bvToggleCoupon(['value' => 20, 'apply_on_brand_vendor_discounts' => true]);

    $result = app(CouponDiscountService::class)->applyCouponDiscountToProducts(
        $coupon, $user, collect([['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null]]), false
    );

    $mod = $result['modified_products'][0];
    expect($result['success'])->toBeTrue()
        ->and($mod['applied_discount_percentage'])->toBe(20.0)
        ->and($mod['discount_source'])->toBe('coupon');
});

it('stacks a fixed-amount coupon on top of a brand discount when the toggle is enabled', function () {
    ['tax' => $tax, 'brand' => $brand, 'user' => $user] = bvToggleScaffold(['discount' => 10]);
    $p = bvToggleProduct($brand, $tax, ['price' => 1000]);

    $coupon = bvToggleCoupon(['type' => 'fixed_amount', 'value' => 200, 'apply_on_brand_vendor_discounts' => true]);

    $result = app(CouponDiscountService::class)->applyCouponDiscountToProducts(
        $coupon, $user, collect([['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null]]), false
    );

    $mod = $result['modified_products'][0];
    // Brand saves 100/unit, coupon reduces another 200 → final price 700.
    expect($mod['applied_discount_type'])->toBe('fixed_amount')
        ->and((float) $mod['new_unit_price'])->toBe(700.0)
        ->and((float) $mod['coupon_contribution'])->toBe(200.0)
        ->and((float) $mod['line_savings'])->toBe(300.0)
        ->and((float) $mod['fixed_discount_per_unit'])->toBe(300.0);
});

it('fixed-amount coupon uses best-of against brand discounts by default', function () {
    ['tax' => $tax, 'brand' => $brand, 'user' => $user] = bvToggleScaffold(['discount' => 10]);
    $p = bvToggleProduct($brand, $tax, ['price' => 1000]);

    // Coupon reduction (200) beats brand savings (100) → coupon wins, not 300 combined.
    $coupon = bvToggleCoupon(['type' => 'fixed_amount', 'value' => 200]);
    $result = app(CouponDiscountService::class)->applyCouponDiscountToProducts(
        $coupon, $user, collect([['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null]]), false
    );

    $mod = $result['modified_products'][0];
    expect($mod['applied_discount_type'])->toBe('fixed_amount')
        ->and((float) $mod['new_unit_price'])->toBe(800.0)
        ->and((float) $mod['coupon_contribution'])->toBe(200.0);

    // Coupon reduction (50) is worse than brand savings (100) → brand discount kept.
    $small = bvToggleCoupon(['type' => 'fixed_amount', 'value' => 50]);
    $result = app(CouponDiscountService::class)->applyCouponDiscountToProducts(
        $small, $user, collect([['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null]]), false
    );

    $mod = $result['modified_products'][0];
    expect($mod['applied_discount_type'])->toBe('percentage')
        ->and($mod['applied_discount_percentage'])->toBe(10.0)
        ->and((float) $mod['coupon_contribution'])->toBe(0.0);
});

it('applies a default coupon on a fully brand-discounted cart without errors', function () {
    ['tax' => $tax, 'brand' => $brand, 'user' => $user] = bvToggleScaffold(['discount' => 12]);
    $p = bvToggleProduct($brand, $tax);

    $coupon = bvToggleCoupon(['value' => 20]);

    $result = app(CouponService::class)->applyCouponToCart(
        $coupon, $user, collect([['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null]]), false
    );

    expect($result['success'])->toBeTrue()
        ->and((float) $result['discount_amount'])->toBe(200.0);
});

it('applies a coupon to a brand-discounted cart through the web endpoint without the generic error', function () {
    ['tax' => $tax, 'brand' => $brand, 'user' => $user] = bvToggleScaffold(['discount' => 12]);
    $p = bvToggleProduct($brand, $tax, ['price' => 1000]);

    $coupon = bvToggleCoupon(['value' => 20]);

    $response = actingAs($user)
        ->withSession(['cart' => [['product_id' => $p->id, 'quantity' => 1, 'variation_id' => null]]])
        ->post(route('cart.coupon.apply'), ['coupon_code' => $coupon->code]);

    $response->assertRedirect(route('cart'))
        ->assertSessionMissing('error')
        ->assertSessionHas('success');
});

it('applies a coupon even when the cart line has no variation_id key', function () {
    ['tax' => $tax, 'brand' => $brand, 'user' => $user] = bvToggleScaffold(['discount' => 12]);
    $p = bvToggleProduct($brand, $tax, ['price' => 1000]);

    $coupon = bvToggleCoupon(['value' => 20]);

    // Legacy/malformed cart line without the variation_id key must not blow up.
    $response = actingAs($user)
        ->withSession(['cart' => [['product_id' => $p->id, 'quantity' => 1]]])
        ->post(route('cart.coupon.apply'), ['coupon_code' => $coupon->code]);

    $response->assertRedirect(route('cart'))
        ->assertSessionMissing('error')
        ->assertSessionHas('success');
});

it('admin can enable and disable stacking from the coupon form', function () {
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
        'apply_on_brand_vendor_discounts' => '1',
    ];

    actingAs($admin)->post(route('coupons.store'), $payload)->assertRedirect(route('coupons.index'));

    $coupon = Coupon::where('code', 'TOGGLE1')->firstOrFail();
    expect($coupon->apply_on_brand_vendor_discounts)->toBeTrue();

    // Disable via update.
    actingAs($admin)->put(route('coupons.update', $coupon), array_merge($payload, [
        'apply_on_brand_vendor_discounts' => '0',
    ]))->assertRedirect(route('coupons.index'));

    expect($coupon->fresh()->apply_on_brand_vendor_discounts)->toBeFalse();
});

it('defaults to no stacking when the field is not sent', function () {
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

    expect(Coupon::where('code', 'TOGGLE2')->firstOrFail()->apply_on_brand_vendor_discounts)->toBeFalse();
});
