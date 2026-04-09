<?php

use App\Models\Brand;
use App\Models\Product;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Services\DiscountService;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\actingAs;

/*
|--------------------------------------------------------------------------
| Discount Exclusion Feature Tests
|--------------------------------------------------------------------------
| These tests verify that the exclude_from_brand_discount and
| exclude_from_vendor_discount flags on products are properly enforced
| across all discount calculation paths.
*/

function createProductWithDiscountHierarchy(array $overrides = []): Product
{
    $defaults = [
        'vendor_discount' => 15,
        'brand_discount' => 10,
        'product_discount' => 0,
        'product_price' => 10000,
        'tax_rate' => 0,
        'exclude_from_brand_discount' => false,
        'exclude_from_vendor_discount' => false,
    ];

    $config = array_merge($defaults, $overrides);

    $tax = Tax::create(['name' => 'Tax ' . $config['tax_rate'] . '%', 'tax' => $config['tax_rate']]);

    $vendor = Vendor::create([
        'name' => 'Test Vendor',
        'slug' => 'test-vendor-' . uniqid(),
        'minimum_purchase' => 0,
        'minimum_discount_amount' => 0,
        'active' => true,
        'discount' => $config['vendor_discount'],
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
    ]);

    $brand = Brand::create([
        'name' => 'Test Brand',
        'slug' => 'test-brand-' . uniqid(),
        'active' => true,
        'discount' => $config['brand_discount'],
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
        'vendor_id' => $vendor->id,
    ]);

    return Product::create([
        'name' => 'Test Product ' . uniqid(),
        'slug' => 'test-product-' . uniqid(),
        'sku' => 'SKU-' . uniqid(),
        'price' => $config['product_price'],
        'discount' => $config['product_discount'],
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
        'delivery_days' => 5,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'package_quantity' => 1,
        'active' => true,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'exclude_from_brand_discount' => $config['exclude_from_brand_discount'],
        'exclude_from_vendor_discount' => $config['exclude_from_vendor_discount'],
        'safety_stock' => 0,
    ]);
}

// ──────────────────────────────────────────────────────────────────────────
// getFinalPriceForUser
// ──────────────────────────────────────────────────────────────────────────

it('applies vendor discount when not excluded', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 15,
        'brand_discount' => 10,
    ]);

    $result = $product->getFinalPriceForUser(false);

    expect($result['discount'])->toBe(15);
    expect($result['discount_on'])->toBe('Vendor');
    expect($result['has_discount'])->toBeTrue();
});

it('skips vendor discount when product is excluded from vendor discounts', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 15,
        'brand_discount' => 10,
        'exclude_from_vendor_discount' => true,
    ]);

    $result = $product->getFinalPriceForUser(false);

    // Should fall back to brand discount since vendor is excluded
    expect($result['discount'])->toBe(10);
    expect($result['discount_on'])->toBe('Marca');
});

it('skips brand discount when product is excluded from brand discounts', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 15,
        'brand_discount' => 10,
        'exclude_from_brand_discount' => true,
    ]);

    $result = $product->getFinalPriceForUser(false);

    // Vendor should still apply (higher priority), brand skipped
    expect($result['discount'])->toBe(15);
    expect($result['discount_on'])->toBe('Vendor');
});

it('skips both brand and vendor discounts when both exclusions are set', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 15,
        'brand_discount' => 10,
        'exclude_from_brand_discount' => true,
        'exclude_from_vendor_discount' => true,
    ]);

    $result = $product->getFinalPriceForUser(false);

    expect($result['discount'])->toBe(0);
    expect($result['discount_on'])->toBeFalse();
    expect($result['has_discount'])->toBeFalse();
});

it('keeps product-level discount even when brand and vendor are excluded', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 15,
        'brand_discount' => 10,
        'product_discount' => 5,
        'exclude_from_brand_discount' => true,
        'exclude_from_vendor_discount' => true,
    ]);

    $result = $product->getFinalPriceForUser(false);

    expect($result['discount'])->toBe(5);
    expect($result['discount_on'])->toBe('Producto');
    expect($result['has_discount'])->toBeTrue();
});

it('falls back to brand when vendor excluded and brand > product', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 20,
        'brand_discount' => 12,
        'product_discount' => 5,
        'exclude_from_vendor_discount' => true,
    ]);

    $result = $product->getFinalPriceForUser(false);

    expect($result['discount'])->toBe(12);
    expect($result['discount_on'])->toBe('Marca');
});

it('falls back to product discount when vendor excluded and brand < product', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 20,
        'brand_discount' => 3,
        'product_discount' => 8,
        'exclude_from_vendor_discount' => true,
    ]);

    $result = $product->getFinalPriceForUser(false);

    expect($result['discount'])->toBe(8);
    expect($result['discount_on'])->toBe('Producto');
});

it('computes correct final price with vendor discount excluded', function () {
    $product = createProductWithDiscountHierarchy([
        'product_price' => 10000,
        'vendor_discount' => 20,
        'brand_discount' => 0,
        'product_discount' => 0,
        'exclude_from_vendor_discount' => true,
        'tax_rate' => 0,
    ]);

    $result = $product->getFinalPriceForUser(false);

    // No discount should apply — full price
    expect((float) $result['price'])->toBe(10000.0);
    expect((float) $result['old'])->toBe(10000.0);
    expect($result['has_discount'])->toBeFalse();
});

it('computes correct final price with brand discount excluded', function () {
    $product = createProductWithDiscountHierarchy([
        'product_price' => 10000,
        'vendor_discount' => 0,
        'brand_discount' => 20,
        'product_discount' => 0,
        'exclude_from_brand_discount' => true,
        'tax_rate' => 0,
    ]);

    $result = $product->getFinalPriceForUser(false);

    expect((float) $result['price'])->toBe(10000.0);
    expect($result['has_discount'])->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────
// hasAnyDiscount
// ──────────────────────────────────────────────────────────────────────────

it('hasAnyDiscount returns true when brand has discount and product is not excluded', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 0,
        'brand_discount' => 10,
        'product_discount' => 0,
        'exclude_from_brand_discount' => false,
    ]);

    expect($product->hasAnyDiscount())->toBeTrue();
});

it('hasAnyDiscount returns false when brand has discount but product is excluded', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 0,
        'brand_discount' => 10,
        'product_discount' => 0,
        'exclude_from_brand_discount' => true,
    ]);

    expect($product->hasAnyDiscount())->toBeFalse();
});

it('hasAnyDiscount returns true when vendor has discount and product is not excluded', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 15,
        'brand_discount' => 0,
        'product_discount' => 0,
        'exclude_from_vendor_discount' => false,
    ]);

    expect($product->hasAnyDiscount())->toBeTrue();
});

it('hasAnyDiscount returns false when vendor has discount but product is excluded', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 15,
        'brand_discount' => 0,
        'product_discount' => 0,
        'exclude_from_vendor_discount' => true,
    ]);

    expect($product->hasAnyDiscount())->toBeFalse();
});

it('hasAnyDiscount returns true with product discount regardless of exclusion flags', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 15,
        'brand_discount' => 10,
        'product_discount' => 5,
        'exclude_from_brand_discount' => true,
        'exclude_from_vendor_discount' => true,
    ]);

    expect($product->hasAnyDiscount())->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────
// getStaticDiscountInfo
// ──────────────────────────────────────────────────────────────────────────

it('getStaticDiscountInfo picks vendor discount when not excluded', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 15,
        'brand_discount' => 10,
        'product_discount' => 5,
    ]);

    $info = $product->getStaticDiscountInfo();

    expect($info)->not->toBeNull();
    expect($info['has_discount'])->toBeTrue();
    expect($info['discount'])->toBe(15);
    expect($info['discount_source'])->toBe('vendor');
});

it('getStaticDiscountInfo skips vendor when excluded, picks brand', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 15,
        'brand_discount' => 10,
        'product_discount' => 5,
        'exclude_from_vendor_discount' => true,
    ]);

    $info = $product->getStaticDiscountInfo();

    expect($info)->not->toBeNull();
    expect($info['discount'])->toBe(10);
    expect($info['discount_source'])->toBe('brand');
});

it('getStaticDiscountInfo skips brand when excluded, picks vendor', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 15,
        'brand_discount' => 10,
        'product_discount' => 5,
        'exclude_from_brand_discount' => true,
    ]);

    $info = $product->getStaticDiscountInfo();

    expect($info)->not->toBeNull();
    expect($info['discount'])->toBe(15);
    expect($info['discount_source'])->toBe('vendor');
});

it('getStaticDiscountInfo falls back to product when both excluded', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 15,
        'brand_discount' => 10,
        'product_discount' => 5,
        'exclude_from_brand_discount' => true,
        'exclude_from_vendor_discount' => true,
    ]);

    $info = $product->getStaticDiscountInfo();

    expect($info)->not->toBeNull();
    expect($info['discount'])->toBe(5);
    expect($info['discount_source'])->toBe('product');
});

it('getStaticDiscountInfo returns null when all excluded and no product discount', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 15,
        'brand_discount' => 10,
        'product_discount' => 0,
        'exclude_from_brand_discount' => true,
        'exclude_from_vendor_discount' => true,
    ]);

    $info = $product->getStaticDiscountInfo();

    expect($info)->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────
// DiscountService::calculateDirectDiscount
// ──────────────────────────────────────────────────────────────────────────

it('DiscountService excludes brand discount for excluded products', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 0,
        'brand_discount' => 10,
        'exclude_from_brand_discount' => true,
    ]);
    $user = User::factory()->create();

    $service = new DiscountService();
    $cartProducts = collect([
        ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
    ]);

    $result = $service->applyBestDiscount($cartProducts, $user, false);

    expect($result['type'])->toBe('none');
    expect($result['amount'])->toBe(0);
});

it('DiscountService excludes vendor discount for excluded products', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 15,
        'brand_discount' => 0,
        'exclude_from_vendor_discount' => true,
    ]);
    $user = User::factory()->create();

    $service = new DiscountService();
    $cartProducts = collect([
        ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
    ]);

    $result = $service->applyBestDiscount($cartProducts, $user, false);

    expect($result['type'])->toBe('none');
    expect($result['amount'])->toBe(0);
});

it('DiscountService applies brand discount for non-excluded products', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 0,
        'brand_discount' => 10,
        'product_price' => 10000,
        'exclude_from_brand_discount' => false,
    ]);
    $user = User::factory()->create();

    $service = new DiscountService();
    $cartProducts = collect([
        ['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null],
    ]);

    $result = $service->applyBestDiscount($cartProducts, $user, false);

    expect($result['type'])->toBe('direct');
    expect($result['amount'])->toBeGreaterThan(0);
});

// ──────────────────────────────────────────────────────────────────────────
// Database persistence
// ──────────────────────────────────────────────────────────────────────────

it('persists exclude_from_brand_discount flag correctly', function () {
    $product = createProductWithDiscountHierarchy([
        'exclude_from_brand_discount' => true,
    ]);

    $reloaded = Product::find($product->id);

    expect($reloaded->exclude_from_brand_discount)->toBeTrue();
});

it('persists exclude_from_vendor_discount flag correctly', function () {
    $product = createProductWithDiscountHierarchy([
        'exclude_from_vendor_discount' => true,
    ]);

    $reloaded = Product::find($product->id);

    expect($reloaded->exclude_from_vendor_discount)->toBeTrue();
});

it('can update exclusion flags from false to true', function () {
    $product = createProductWithDiscountHierarchy([
        'exclude_from_brand_discount' => false,
        'exclude_from_vendor_discount' => false,
    ]);

    $product->update([
        'exclude_from_brand_discount' => true,
        'exclude_from_vendor_discount' => true,
    ]);

    $reloaded = Product::find($product->id);
    expect($reloaded->exclude_from_brand_discount)->toBeTrue();
    expect($reloaded->exclude_from_vendor_discount)->toBeTrue();
});

it('can update exclusion flags from true to false', function () {
    $product = createProductWithDiscountHierarchy([
        'exclude_from_brand_discount' => true,
        'exclude_from_vendor_discount' => true,
    ]);

    $product->update([
        'exclude_from_brand_discount' => false,
        'exclude_from_vendor_discount' => false,
    ]);

    $reloaded = Product::find($product->id);
    expect($reloaded->exclude_from_brand_discount)->toBeFalse();
    expect($reloaded->exclude_from_vendor_discount)->toBeFalse();
});

it('handles string "0" as false for exclusion flags (form checkbox pattern)', function () {
    $product = createProductWithDiscountHierarchy([
        'exclude_from_brand_discount' => true,
    ]);

    $product->update([
        'exclude_from_brand_discount' => '0',
        'exclude_from_vendor_discount' => '0',
    ]);

    $reloaded = Product::find($product->id);
    expect($reloaded->exclude_from_brand_discount)->toBeFalse();
    expect($reloaded->exclude_from_vendor_discount)->toBeFalse();
});

it('handles string "1" as true for exclusion flags (form checkbox pattern)', function () {
    $product = createProductWithDiscountHierarchy();

    $product->update([
        'exclude_from_brand_discount' => '1',
        'exclude_from_vendor_discount' => '1',
    ]);

    $reloaded = Product::find($product->id);
    expect($reloaded->exclude_from_brand_discount)->toBeTrue();
    expect($reloaded->exclude_from_vendor_discount)->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────
// Edge: exclusion changes pricing in real time (no caching)
// ──────────────────────────────────────────────────────────────────────────

it('toggling exclusion flag immediately changes getFinalPriceForUser result', function () {
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 20,
        'brand_discount' => 0,
        'product_discount' => 0,
        'product_price' => 10000,
        'tax_rate' => 0,
    ]);

    $before = $product->getFinalPriceForUser(false);
    expect($before['discount'])->toBe(20);
    expect((float) $before['price'])->toBe(8000.0);

    $product->update(['exclude_from_vendor_discount' => true]);
    $product->refresh();

    $after = $product->getFinalPriceForUser(false);
    expect($after['discount'])->toBe(0);
    expect((float) $after['price'])->toBe(10000.0);
});

// ──────────────────────────────────────────────────────────────────────────
// HTTP controller save path
// ──────────────────────────────────────────────────────────────────────────

function createAdminUser(): User
{
    $user = User::factory()->create();
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $user->assignRole('admin');
    return $user;
}

function baseProductPayload(Product $product): array
{
    return [
        'name' => $product->name,
        'slug' => $product->slug,
        'sku' => $product->sku,
        'price' => $product->price,
        'delivery_days' => $product->delivery_days,
        'discount' => $product->discount,
        'discount_type' => $product->discount_type,
        'quantity_min' => $product->quantity_min,
        'quantity_max' => $product->quantity_max,
        'step' => $product->step,
        'tax_id' => $product->tax_id,
        'brand_id' => $product->brand_id,
        'package_quantity' => $product->package_quantity,
    ];
}

it('saves exclusion flags via HTTP PUT (checkbox checked)', function () {
    $admin = createAdminUser();
    $product = createProductWithDiscountHierarchy([
        'exclude_from_brand_discount' => false,
        'exclude_from_vendor_discount' => false,
    ]);

    $payload = baseProductPayload($product) + [
        'exclude_from_brand_discount' => 1,
        'exclude_from_vendor_discount' => 1,
    ];

    actingAs($admin)
        ->put("/products/{$product->id}", $payload)
        ->assertRedirect('/products')
        ->assertSessionHas('success');

    $reloaded = Product::find($product->id);
    expect($reloaded->exclude_from_brand_discount)->toBeTrue();
    expect($reloaded->exclude_from_vendor_discount)->toBeTrue();
});

it('clears exclusion flags via HTTP PUT (checkbox unchecked, hidden sends 0)', function () {
    $admin = createAdminUser();
    $product = createProductWithDiscountHierarchy([
        'exclude_from_brand_discount' => true,
        'exclude_from_vendor_discount' => true,
    ]);

    $payload = baseProductPayload($product) + [
        'exclude_from_brand_discount' => 0,
        'exclude_from_vendor_discount' => 0,
    ];

    actingAs($admin)
        ->put("/products/{$product->id}", $payload)
        ->assertRedirect('/products')
        ->assertSessionHas('success');

    $reloaded = Product::find($product->id);
    expect($reloaded->exclude_from_brand_discount)->toBeFalse();
    expect($reloaded->exclude_from_vendor_discount)->toBeFalse();
});

it('exclusion flags affect finalPrice after HTTP save', function () {
    $admin = createAdminUser();
    $product = createProductWithDiscountHierarchy([
        'vendor_discount' => 20,
        'brand_discount' => 0,
        'product_discount' => 0,
        'product_price' => 10000,
        'tax_rate' => 0,
        'exclude_from_vendor_discount' => false,
    ]);

    $before = $product->getFinalPriceForUser(false);
    expect($before['discount'])->toBe(20);

    $payload = baseProductPayload($product) + [
        'exclude_from_vendor_discount' => 1,
        'exclude_from_brand_discount' => 0,
    ];

    actingAs($admin)
        ->put("/products/{$product->id}", $payload)
        ->assertRedirect('/products');

    $reloaded = Product::with('brand.vendor')->find($product->id);
    $after = $reloaded->getFinalPriceForUser(false);
    expect($after['discount'])->toBe(0);
    expect((float) $after['price'])->toBe(10000.0);
});
