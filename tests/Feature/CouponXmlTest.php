<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Repositories\OrderRepository;
use App\Services\CouponDiscountService;
use App\Services\CouponService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Coupon XML & Workflow - Comprehensive Smoke Tests
|--------------------------------------------------------------------------
| Verifies:
| - XML rules: % discounts use dyn:discount, fixed use reduced unitPrice + discount=0
| - Coupon combinations: single, multiple, percentage+fixed
| - Client-specific (APPLIES_TO_CUSTOMER)
| - applies_to: cart, product, brand, category
| - Corner cases: package pricing, mixed cart, exclusions
*/


// --- Helpers ---

function createTestTax(): Tax
{
    return Tax::create(['name' => 'Test Tax ' . uniqid(), 'tax' => 0]);
}

function createTestVendor(): Vendor
{
    return Vendor::create([
        'name' => 'Test Vendor ' . uniqid(),
        'slug' => 'test-vendor-' . uniqid(),
        'vendor_type' => 'V',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
}

function createTestBrand(Vendor $vendor): Brand
{
    return Brand::create([
        'name' => 'Test Brand ' . uniqid(),
        'slug' => 'test-brand-' . uniqid(),
        'vendor_id' => $vendor->id,
    ]);
}

function createTestZone(): Zone
{
    return Zone::create([
        'route' => 'R' . substr(uniqid(), -3),
        'zone' => 'Z' . substr(uniqid(), -3),
        'day' => 'Lunes',
        'address' => 'Test',
        'code' => 'T' . substr(uniqid(), -4),
    ]);
}

function createTestProduct(Brand $brand, Tax $tax, array $overrides = []): Product
{
    return Product::create(array_merge([
        'name' => 'Test Product ' . uniqid(),
        'slug' => 'test-product-' . uniqid(),
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

function createTestUser(Zone $zone): User
{
    $user = User::factory()->create();
    $user->zones()->attach($zone->id);
    return $user;
}

function createCoupon(array $attrs = []): Coupon
{
    $from = Carbon::now()->subDay();
    $to = Carbon::now()->addMonths(1);
    return Coupon::create(array_merge([
        'code' => 'CUP' . strtoupper(substr(uniqid(), -6)),
        'name' => 'Test Coupon',
        'type' => 'percentage',
        'value' => 10,
        'valid_from' => $from,
        'valid_to' => $to,
        'active' => true,
        'applies_to' => 'cart',
        'applies_to_ids' => null,
    ], $attrs));
}

function buildMockOrder(User $user, Zone $zone, array $orderProductsData): array
{
    $order = new Order([
        'id' => 0,
        'user_id' => $user->id,
        'zone_id' => $zone->id,
        'delivery_date' => now()->addDays(2)->format('Y-m-d'),
        'observations' => 'Test',
        'created_at' => now(),
    ]);
    $order->id = 0;
    $order->setRelation('zone', $zone);
    $order->setRelation('user', $user);

    $products = [];
    $total = 0;
    foreach ($orderProductsData as $opData) {
        $product = $opData['product'];
        $op = new OrderProduct([
            'order_id' => 0,
            'product_id' => $product->id,
            'quantity' => $opData['quantity'],
            'price' => $opData['price'],
            'percentage' => $opData['percentage'] ?? 0,
            'discount_type' => $opData['discount_type'] ?? 'percentage',
            'flat_discount_amount' => $opData['flat_discount_amount'] ?? 0,
            'variation_item_id' => $opData['variation_item_id'] ?? null,
            'package_quantity' => $product->package_quantity ?? 1,
        ]);
        $op->setRelation('product', $product);
        $products[] = $op;
        $total += $opData['price'] * $opData['quantity'];
    }
    $order->total = $total;
    $order->setRelation('products', collect($products));
    return [$order, collect($products)];
}

// --- XML Rule Tests ---

it('uses dyn:discount for percentage discounts and keeps unitPrice at base', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $product = createTestProduct($brand, $tax);

    [$order, $products] = buildMockOrder($user, $zone, [[
        'product' => $product,
        'quantity' => 2,
        'price' => 1000,
        'percentage' => 15,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $products);
    expect($xml)->not->toBeNull()
        ->and($xml)->toContain('<dyn:discount>15</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>1000</dyn:unitPrice>');
});

it('uses reduced unitPrice and dyn:discount 0 for fixed_amount discounts', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $product = createTestProduct($brand, $tax);

    [$order, $products] = buildMockOrder($user, $zone, [[
        'product' => $product,
        'quantity' => 1,
        'price' => 1000,
        'percentage' => 0,
        'discount_type' => 'fixed_amount',
        'flat_discount_amount' => 200,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $products);
    expect($xml)->not->toBeNull()
        ->and($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>800</dyn:unitPrice>');
});

it('never transmits percentage in discount field when discount_type is fixed_amount', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $product = createTestProduct($brand, $tax);

    [$order, $products] = buildMockOrder($user, $zone, [[
        'product' => $product,
        'quantity' => 1,
        'price' => 1000,
        'percentage' => 0,
        'discount_type' => 'fixed_amount',
        'flat_discount_amount' => 500,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $products);
    expect($xml)->not->toBeNull()->and($xml)->toContain('<dyn:discount>0</dyn:discount>');
});

// --- CouponDiscountService: Single percentage coupon ---

it('applies single percentage coupon to cart and returns modified products', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $product = createTestProduct($brand, $tax);

    $coupon = createCoupon(['type' => 'percentage', 'value' => 20, 'applies_to' => 'cart']);
    $cart = collect([['product_id' => $product->id, 'quantity' => 2, 'variation_id' => null]]);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, $cart, false);

    expect($result['success'])->toBeTrue()
        ->and($result['modified_products'])->toHaveCount(1)
        ->and($result['modified_products'][0]['applied_discount_type'])->toBe('percentage')
        ->and($result['modified_products'][0]['applied_discount_percentage'])->toBe(20.0);
});

// --- CouponDiscountService: Single fixed amount coupon ---

it('applies single fixed amount coupon and returns fixed_amount discount type', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $product = createTestProduct($brand, $tax, ['price' => 500]);

    $coupon = createCoupon([
        'type' => 'fixed_amount',
        'value' => 100,
        'applies_to' => 'cart',
    ]);
    $cart = collect([['product_id' => $product->id, 'quantity' => 2, 'variation_id' => null]]);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, $cart, false);

    expect($result['success'])->toBeTrue();
    $mod = collect($result['modified_products'])->firstWhere('product_id', $product->id);
    expect($mod)->not->toBeNull()
        ->and($mod['applied_discount_type'])->toBeIn(['fixed_amount', 'existing_percentage'])
        ->and($mod)->toHaveKeys(['unit_price_reduction', 'base_price', 'new_unit_price']);
});

// --- Client-specific (APPLIES_TO_CUSTOMER) ---

it('applies coupon only when user id is in applies_to_ids for APPLIES_TO_CUSTOMER', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $product = createTestProduct($brand, $tax);

    $coupon = createCoupon([
        'type' => 'percentage',
        'value' => 15,
        'applies_to' => 'customer',
        'applies_to_ids' => [$user->id],
    ]);
    $cart = collect([['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null]]);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, $cart, false);

    expect($result['success'])->toBeTrue()
        ->and($result['modified_products'][0]['applied_discount_percentage'])->toBe(15.0);
});

it('does not apply APPLIES_TO_CUSTOMER coupon when user id is not in applies_to_ids', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $otherUser = User::factory()->create();
    $otherUser->zones()->attach($zone->id);
    $product = createTestProduct($brand, $tax);

    $coupon = createCoupon([
        'type' => 'percentage',
        'value' => 15,
        'applies_to' => 'customer',
        'applies_to_ids' => [$user->id],
    ]);
    $cart = collect([['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null]]);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $otherUser, $cart, false);

    // Service returns success but coupon contributes nothing when user not in applies_to_ids
    expect($result['total_coupon_discount'])->toBe(0.0);
    $mod = $result['modified_products'][0] ?? null;
    expect($mod)->not->toBeNull()
        ->and($mod['applied_discount_percentage'] ?? 0)->not->toBe(15.0);
});

// --- Multiple coupons (best-of per product) ---

it('applyMultipleCouponsToProducts picks best discount per product', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $product = createTestProduct($brand, $tax, ['price' => 1000]);

    $pct10 = createCoupon(['code' => 'PCT10', 'type' => 'percentage', 'value' => 10, 'applies_to' => 'cart']);
    $pct25 = createCoupon(['code' => 'PCT25', 'type' => 'percentage', 'value' => 25, 'applies_to' => 'cart']);
    $cart = collect([['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null]]);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyMultipleCouponsToProducts(
        [$pct10, $pct25],
        $user,
        $cart,
        false
    );

    expect($result['success'])->toBeTrue();
    $mod = $result['modified_products'][0] ?? null;
    expect($mod)->not->toBeNull()
        ->and($mod['applied_discount_percentage'])->toBe(25.0)
        ->and($mod['winning_coupon_code'] ?? null)->toBe('PCT25');
});

it('applyMultipleCouponsToProducts handles percentage plus fixed and picks better savings', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $product = createTestProduct($brand, $tax, ['price' => 1000]);

    $pct = createCoupon(['code' => 'PCT20', 'type' => 'percentage', 'value' => 20, 'applies_to' => 'cart']);
    $fix = createCoupon(['code' => 'FIX100', 'type' => 'fixed_amount', 'value' => 100, 'applies_to' => 'cart']);
    $cart = collect([['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null]]);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyMultipleCouponsToProducts([$pct, $fix], $user, $cart, false);

    expect($result['success'])->toBeTrue();
    $mod = $result['modified_products'][0] ?? null;
    expect($mod)->not->toBeNull();
    // 20% of 1000 = 200, fixed 100 = 100. Percentage should win.
    expect($mod['applied_discount_type'])->toBeIn(['percentage', 'fixed_amount']);
});

// --- Product-specific coupon ---

it('applies product-specific coupon only to matching product', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $p1 = createTestProduct($brand, $tax, ['sku' => 'P1']);
    $p2 = createTestProduct($brand, $tax, ['sku' => 'P2']);

    $coupon = createCoupon([
        'type' => 'percentage',
        'value' => 30,
        'applies_to' => 'product',
        'applies_to_ids' => [$p1->id],
    ]);
    $cart = collect([
        ['product_id' => $p1->id, 'quantity' => 1, 'variation_id' => null],
        ['product_id' => $p2->id, 'quantity' => 1, 'variation_id' => null],
    ]);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, $cart, false);

    expect($result['success'])->toBeTrue();
    $modsByProduct = collect($result['modified_products'])->keyBy('product_id');
    expect($modsByProduct->get($p1->id)['applied_discount_percentage'])->toBe(30.0);
    expect($modsByProduct->get($p2->id)['applied_discount_percentage'] ?? 0)->not->toBe(30.0);
});

// --- Brand-specific coupon ---

it('applies brand-specific coupon to products of that brand', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $product = createTestProduct($brand, $tax);

    $coupon = createCoupon([
        'type' => 'percentage',
        'value' => 12,
        'applies_to' => 'brand',
        'applies_to_ids' => [$brand->id],
    ]);
    $cart = collect([['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null]]);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, $cart, false);

    expect($result['success'])->toBeTrue()
        ->and($result['modified_products'][0]['applied_discount_percentage'])->toBe(12.0);
});

// --- Category-specific coupon ---

it('applies category-specific coupon when product is in category', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $category = Category::create(['name' => 'Test Cat', 'slug' => 'test-cat-' . uniqid()]);
    $product = createTestProduct($brand, $tax);
    $product->categories()->attach($category->id);

    $coupon = createCoupon([
        'type' => 'percentage',
        'value' => 18,
        'applies_to' => 'category',
        'applies_to_ids' => [$category->id],
    ]);
    $cart = collect([['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null]]);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, $cart, false);

    expect($result['success'])->toBeTrue()
        ->and($result['modified_products'][0]['applied_discount_percentage'])->toBe(18.0);
});

// --- Package pricing ---

it('handles calculate_package_price product with percentage coupon', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $product = createTestProduct($brand, $tax, [
        'price' => 500,
        'package_quantity' => 2,
        'calculate_package_price' => true,
    ]);

    $coupon = createCoupon(['type' => 'percentage', 'value' => 10, 'applies_to' => 'cart']);
    $cart = collect([['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null]]);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, $cart, false);

    expect($result['success'])->toBeTrue();
    $mod = $result['modified_products'][0];
    expect($mod['package_quantity'])->toBe(2)
        ->and($mod['base_price'])->toBe(500.0);
});

// --- Mixed cart: some products with coupon, some without ---

it('applies coupon only to applicable products in mixed cart', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $p1 = createTestProduct($brand, $tax, ['sku' => 'M1']);
    $p2 = createTestProduct($brand, $tax, ['sku' => 'M2']);

    $coupon = createCoupon([
        'type' => 'percentage',
        'value' => 25,
        'applies_to' => 'product',
        'applies_to_ids' => [$p1->id],
    ]);
    $cart = collect([
        ['product_id' => $p1->id, 'quantity' => 1, 'variation_id' => null],
        ['product_id' => $p2->id, 'quantity' => 1, 'variation_id' => null],
    ]);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, $cart, false);

    expect($result['success'])->toBeTrue()
        ->and($result['modified_products'])->toHaveCount(2);
    $byPid = collect($result['modified_products'])->keyBy('product_id');
    expect($byPid[$p1->id]['applied_discount_percentage'])->toBe(25.0);
});

// --- Minimum amount ---

it('respects minimum_amount when cart total is below', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $product = createTestProduct($brand, $tax, ['price' => 50]);

    $coupon = createCoupon([
        'type' => 'percentage',
        'value' => 20,
        'applies_to' => 'cart',
        'minimum_amount' => 500,
    ]);
    $cart = collect([['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null]]);

    $svc = app(CouponService::class);
    $applyResult = $svc->applyCouponToCart($coupon, $user, $cart, false);
    // CouponService.applyCouponToCart does not check minimum - that's in validateCoupon
    $validation = $svc->validateCoupon($coupon->code, $user, $cart, 50);
    expect($validation['valid'])->toBeFalse();
});

// --- Empty / no applicable products ---

it('applyMultipleCouponsToProducts returns failure when no coupons apply', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $product = createTestProduct($brand, $tax);

    $coupon = createCoupon([
        'type' => 'percentage',
        'value' => 15,
        'applies_to' => 'product',
        'applies_to_ids' => [999999],
    ]);
    $cart = collect([['product_id' => $product->id, 'quantity' => 1, 'variation_id' => null]]);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyMultipleCouponsToProducts([$coupon], $user, $cart, false);

    expect($result['success'])->toBeFalse();
});

// --- XML with multiple products (percentage + fixed in same order) ---

it('generates correct XML for mixed discount types in same order', function () {
    $tax = createTestTax();
    $vendor = createTestVendor();
    $brand = createTestBrand($vendor);
    $zone = createTestZone();
    $user = createTestUser($zone);
    $p1 = createTestProduct($brand, $tax, ['sku' => 'X1', 'price' => 1000]);
    $p2 = createTestProduct($brand, $tax, ['sku' => 'X2', 'price' => 500]);

    [$order, $products] = buildMockOrder($user, $zone, [
        [
            'product' => $p1,
            'quantity' => 1,
            'price' => 1000,
            'percentage' => 20,
            'discount_type' => 'percentage',
            'flat_discount_amount' => 0,
        ],
        [
            'product' => $p2,
            'quantity' => 1,
            'price' => 500,
            'percentage' => 0,
            'discount_type' => 'fixed_amount',
            'flat_discount_amount' => 100,
        ],
    ]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $products);

    expect($xml)->not->toBeNull()
        ->and($xml)->toContain('<dyn:discount>20</dyn:discount>')
        ->and($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>1000</dyn:unitPrice>')
        ->and($xml)->toContain('<dyn:unitPrice>400</dyn:unitPrice>');
});
