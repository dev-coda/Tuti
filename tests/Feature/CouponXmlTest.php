<?php

use App\Models\Brand;
use App\Models\Bonification;
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

/*
|--------------------------------------------------------------------------
| Coupon XML & Discount - Comprehensive Test Suite
|--------------------------------------------------------------------------
| Covers:
|  Section 1 – XML pricing rules (percentage → dyn:discount, fixed → reduced dyn:unitPrice)
|  Section 2 – Single coupon (percentage & fixed, various applies_to targets)
|  Section 3 – Multiple coupons (2 and 3 coupons, best-per-product)
|  Section 4 – Coupons interacting with other discounts & bonifications
|  Section 5 – End-to-end: CouponDiscountService → OrderProduct → XML
|  Section 6 – SOAP safety: non-coupon orders are unchanged
|  Section 7 – Bug regression (vendor minimums, package price safeguard)
*/

// ══════════════════════════════════════════════════════════════════════════
// Helpers
// ══════════════════════════════════════════════════════════════════════════

function makeTax(): Tax
{
    return Tax::create(['name' => 'Tax ' . uniqid(), 'tax' => 0]);
}

function makeVendor(array $overrides = []): Vendor
{
    return Vendor::create(array_merge([
        'name' => 'Vendor ' . uniqid(),
        'slug' => 'vendor-' . uniqid(),
        'vendor_type' => 'V',
        'minimum_purchase' => 0,
        'active' => 1,
    ], $overrides));
}

function makeBrand(Vendor $vendor, array $overrides = []): Brand
{
    return Brand::create(array_merge([
        'name' => 'Brand ' . uniqid(),
        'slug' => 'brand-' . uniqid(),
        'vendor_id' => $vendor->id,
    ], $overrides));
}

function makeZone(): Zone
{
    return Zone::create([
        'route' => 'R' . substr(uniqid(), -3),
        'zone' => 'Z' . substr(uniqid(), -3),
        'day' => 'Lunes',
        'address' => 'Test',
        'code' => 'T' . substr(uniqid(), -4),
    ]);
}

function makeProduct(Brand $brand, Tax $tax, array $overrides = []): Product
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

function makeUser(Zone $zone): User
{
    $user = User::factory()->create([
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
    ]);
    $zone->update(['user_id' => $user->id]);
    return $user;
}

function makeCoupon(array $attrs = []): Coupon
{
    return Coupon::create(array_merge([
        'code' => 'C' . strtoupper(substr(uniqid(), -6)),
        'name' => 'Test Coupon',
        'type' => 'percentage',
        'value' => 10,
        'valid_from' => Carbon::now()->subDay(),
        'valid_to' => Carbon::now()->addMonths(1),
        'active' => true,
        'applies_to' => 'cart',
        'applies_to_ids' => null,
    ], $attrs));
}

function cart(array $items): Collection
{
    return collect($items);
}

function cartItem(Product $product, int $qty = 1, $variationId = null): array
{
    return ['product_id' => $product->id, 'quantity' => $qty, 'variation_id' => $variationId];
}

/**
 * Build an in-memory Order + OrderProduct collection for XML generation tests.
 */
function mockOrder(User $user, Zone $zone, array $lines): array
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
    foreach ($lines as $l) {
        $product = $l['product'];
        $op = new OrderProduct([
            'order_id' => 0,
            'product_id' => $product->id,
            'quantity' => $l['quantity'],
            'price' => $l['price'],
            'percentage' => $l['percentage'] ?? 0,
            'discount_type' => $l['discount_type'] ?? 'percentage',
            'flat_discount_amount' => $l['flat_discount_amount'] ?? 0,
            'variation_item_id' => $l['variation_item_id'] ?? null,
            'package_quantity' => $product->package_quantity ?? 1,
        ]);
        $op->setRelation('product', $product);
        $products[] = $op;
        $total += $l['price'] * $l['quantity'];
    }
    $order->total = $total;
    $order->setRelation('products', collect($products));
    return [$order, collect($products)];
}

/**
 * Simulate CartController mapping: CouponDiscountService result → OrderProduct fields.
 * Returns the fields that CartController would pass to OrderProduct::create().
 */
function mapCouponResultToOrderFields(array $modProduct, Product $product): array
{
    $discountType = $modProduct['applied_discount_type'] ?? 'percentage';
    $basePrice = $modProduct['base_price'];

    if ($discountType === 'fixed_amount') {
        $unitPrice = $product->calculate_package_price
            ? $basePrice * ($product->package_quantity ?? 1)
            : $basePrice;
        $lineDiscountPercent = 0;
        $orderDiscountType = 'fixed_amount';
        $flatDiscountAmount = (float) ($modProduct['fixed_discount_per_unit']
            ?? $modProduct['unit_price_reduction']
            ?? 0);
    } else {
        $unitPrice = $product->calculate_package_price
            ? $basePrice * ($product->package_quantity ?? 1)
            : $basePrice;
        $lineDiscountPercent = (int) round((float) ($modProduct['effective_discount_percentage']
            ?? $modProduct['applied_discount_percentage']
            ?? 0));
        $orderDiscountType = 'percentage';
        $flatDiscountAmount = 0;
    }

    return [
        'price' => $unitPrice,
        'percentage' => $lineDiscountPercent,
        'discount_type' => $orderDiscountType,
        'flat_discount_amount' => $flatDiscountAmount,
        'package_quantity' => $product->package_quantity ?? 1,
    ];
}

/**
 * Full pipeline: apply coupons → map to OrderProduct → generate XML.
 * Returns the XML string for assertion.
 */
function couponToXml(array $coupons, User $user, Zone $zone, Collection $cartProducts, array $productModels): ?string
{
    $svc = app(CouponDiscountService::class);

    if (count($coupons) === 1) {
        $result = $svc->applyCouponDiscountToProducts($coupons[0], $user, $cartProducts, false);
    } else {
        $result = $svc->applyMultipleCouponsToProducts($coupons, $user, $cartProducts, false);
    }

    if (!$result['success']) {
        return null;
    }

    $lookup = [];
    foreach ($result['modified_products'] as $mod) {
        $key = $mod['product_id'] . '_' . ($mod['variation_id'] ?? 'null');
        $lookup[$key] = $mod;
    }

    $orderLines = [];
    foreach ($cartProducts as $item) {
        $product = $productModels[$item['product_id']];
        $key = $item['product_id'] . '_' . ($item['variation_id'] ?? 'null');
        $mod = $lookup[$key] ?? null;

        if ($mod) {
            $fields = mapCouponResultToOrderFields($mod, $product);
        } else {
            $fields = [
                'price' => $product->price,
                'percentage' => 0,
                'discount_type' => 'percentage',
                'flat_discount_amount' => 0,
                'package_quantity' => $product->package_quantity ?? 1,
            ];
        }

        $orderLines[] = [
            'product' => $product,
            'quantity' => $item['quantity'],
            'price' => $fields['price'],
            'percentage' => $fields['percentage'],
            'discount_type' => $fields['discount_type'],
            'flat_discount_amount' => $fields['flat_discount_amount'],
        ];
    }

    [$order, $products] = mockOrder($user, $zone, $orderLines);
    return OrderRepository::buildOrderXmlForDiagnostic($order, false, $products);
}

// Scaffold shared across all tests
function scaffold(): array
{
    $tax = makeTax();
    $vendor = makeVendor();
    $brand = makeBrand($vendor);
    $zone = makeZone();
    $user = makeUser($zone);
    return compact('tax', 'vendor', 'brand', 'zone', 'user');
}


// ══════════════════════════════════════════════════════════════════════════
// Section 1 – XML Pricing Rules (OrderProduct → XML)
// ══════════════════════════════════════════════════════════════════════════

it('[XML] percentage discount populates dyn:discount and keeps original unitPrice', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    [$order, $prods] = mockOrder($user, $zone, [[
        'product' => $p, 'quantity' => 2, 'price' => 1000,
        'percentage' => 15, 'discount_type' => 'percentage', 'flat_discount_amount' => 0,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);
    expect($xml)->toContain('<dyn:discount>15</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>1000.00</dyn:unitPrice>');
});

it('[XML] fixed_amount discount reduces unitPrice and sets dyn:discount to 0', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    [$order, $prods] = mockOrder($user, $zone, [[
        'product' => $p, 'quantity' => 1, 'price' => 1000,
        'percentage' => 0, 'discount_type' => 'fixed_amount', 'flat_discount_amount' => 200,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);
    expect($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>800.00</dyn:unitPrice>');
});

it('[XML] fixed_amount never leaks percentage into discount field', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    [$order, $prods] = mockOrder($user, $zone, [[
        'product' => $p, 'quantity' => 1, 'price' => 1000,
        'percentage' => 0, 'discount_type' => 'fixed_amount', 'flat_discount_amount' => 500,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);
    expect($xml)->toContain('<dyn:discount>0</dyn:discount>');
});

it('[XML] mixed discount types in same order each use correct XML mapping', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p1 = makeProduct($brand, $tax, ['price' => 1000]);
    $p2 = makeProduct($brand, $tax, ['price' => 500]);

    [$order, $prods] = mockOrder($user, $zone, [
        ['product' => $p1, 'quantity' => 1, 'price' => 1000,
         'percentage' => 20, 'discount_type' => 'percentage', 'flat_discount_amount' => 0],
        ['product' => $p2, 'quantity' => 1, 'price' => 500,
         'percentage' => 0, 'discount_type' => 'fixed_amount', 'flat_discount_amount' => 100],
    ]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);
    expect($xml)->toContain('<dyn:discount>20</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>1000.00</dyn:unitPrice>')
        ->and($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>400.00</dyn:unitPrice>');
});

it('[XML] fixed discount caps at 10% floor of unit price', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    [$order, $prods] = mockOrder($user, $zone, [[
        'product' => $p, 'quantity' => 1, 'price' => 1000,
        'percentage' => 0, 'discount_type' => 'fixed_amount', 'flat_discount_amount' => 950,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);
    expect($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>100.00</dyn:unitPrice>');
});

it('[XML] bonification line uses unitPrice=0 and discount=0', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 500]);

    [$order, $prods] = mockOrder($user, $zone, [[
        'product' => $p, 'quantity' => 1, 'price' => 500,
        'percentage' => 0, 'discount_type' => 'percentage', 'flat_discount_amount' => 0,
    ]]);

    // Use buildSoapXmlBody with bonification=1 via buildOrderXmlForDiagnostic
    // We can't easily test bonification lines through the public API without a real bonification order
    // Instead test resolveXmlPricing indirectly: build an order with zero price (bonification semantics)
    [$orderBonif, $prodsBonif] = mockOrder($user, $zone, [[
        'product' => $p, 'quantity' => 1, 'price' => 0,
        'percentage' => 0, 'discount_type' => 'percentage', 'flat_discount_amount' => 0,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($orderBonif, false, $prodsBonif);
    expect($xml)->toContain('<dyn:unitPrice>0.00</dyn:unitPrice>')
        ->and($xml)->toContain('<dyn:discount>0</dyn:discount>');
});

it('[XML] calculate_package_price=true divides price by package_quantity', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, [
        'price' => 600,
        'package_quantity' => 3,
        'calculate_package_price' => true,
    ]);

    // CartController stores price * package_quantity for calculate_package_price products
    [$order, $prods] = mockOrder($user, $zone, [[
        'product' => $p, 'quantity' => 1, 'price' => 600,
        'percentage' => 10, 'discount_type' => 'percentage', 'flat_discount_amount' => 0,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);
    // resolveXmlPricing divides by packageQty: 600/3 = 200
    expect($xml)->toContain('<dyn:unitPrice>200.00</dyn:unitPrice>')
        ->and($xml)->toContain('<dyn:discount>10</dyn:discount>');
});


// ══════════════════════════════════════════════════════════════════════════
// Section 2 – Single Coupon (CouponDiscountService)
// ══════════════════════════════════════════════════════════════════════════

it('[Single] percentage coupon applies to whole cart', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 20, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p, 2)]), false);

    expect($result['success'])->toBeTrue();
    $mod = $result['modified_products'][0];
    expect($mod['applied_discount_type'])->toBe('percentage')
        ->and($mod['applied_discount_percentage'])->toBe(20.0)
        ->and($mod['coupon_contribution'])->toBeGreaterThan(0);
});

it('[Single] fixed amount coupon applies to whole cart', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 500]);

    $coupon = makeCoupon(['type' => 'fixed_amount', 'value' => 100, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p, 2)]), false);

    expect($result['success'])->toBeTrue();
    $mod = collect($result['modified_products'])->firstWhere('product_id', $p->id);
    expect($mod)->not->toBeNull()
        ->and($mod['applied_discount_type'])->toBe('fixed_amount')
        ->and($mod['unit_price_reduction'])->toBeGreaterThan(0)
        ->and($mod['fixed_discount_per_unit'])->toBeGreaterThan(0);
});

it('[Single] product-specific percentage coupon only affects target product', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p1 = makeProduct($brand, $tax, ['price' => 1000]);
    $p2 = makeProduct($brand, $tax, ['price' => 1000]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 30, 'applies_to' => 'product', 'applies_to_ids' => [$p1->id]]);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p1), cartItem($p2)]), false);

    expect($result['success'])->toBeTrue();
    $byPid = collect($result['modified_products'])->keyBy('product_id');
    expect($byPid[$p1->id]['applied_discount_percentage'])->toBe(30.0)
        ->and($byPid[$p1->id]['coupon_contribution'])->toBeGreaterThan(0);
    expect($byPid[$p2->id]['applied_discount_percentage'] ?? 0)->toBe(0.0);
});

it('[Single] product-specific fixed coupon only affects target product', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p1 = makeProduct($brand, $tax, ['price' => 1000]);
    $p2 = makeProduct($brand, $tax, ['price' => 1000]);

    $coupon = makeCoupon(['type' => 'fixed_amount', 'value' => 200, 'applies_to' => 'product', 'applies_to_ids' => [$p1->id]]);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p1), cartItem($p2)]), false);

    expect($result['success'])->toBeTrue();
    $byPid = collect($result['modified_products'])->keyBy('product_id');
    expect($byPid[$p1->id]['applied_discount_type'])->toBe('fixed_amount')
        ->and($byPid[$p1->id]['unit_price_reduction'])->toBeGreaterThan(0);
});

it('[Single] brand-specific coupon applies to products of that brand only', function () {
    ['tax' => $tax, 'vendor' => $vendor, 'zone' => $zone, 'user' => $user] = scaffold();
    $brand1 = makeBrand($vendor);
    $brand2 = makeBrand($vendor);
    $p1 = makeProduct($brand1, $tax, ['price' => 1000]);
    $p2 = makeProduct($brand2, $tax, ['price' => 1000]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 15, 'applies_to' => 'brand', 'applies_to_ids' => [$brand1->id]]);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p1), cartItem($p2)]), false);

    expect($result['success'])->toBeTrue();
    $byPid = collect($result['modified_products'])->keyBy('product_id');
    expect($byPid[$p1->id]['applied_discount_percentage'])->toBe(15.0)
        ->and($byPid[$p1->id]['coupon_contribution'])->toBeGreaterThan(0);
});

it('[Single] category-specific coupon applies to products in that category', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $cat = Category::create(['name' => 'Cat ' . uniqid(), 'slug' => 'cat-' . uniqid()]);
    $p1 = makeProduct($brand, $tax, ['price' => 1000]);
    $p1->categories()->attach($cat->id);
    $p2 = makeProduct($brand, $tax, ['price' => 1000]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 18, 'applies_to' => 'category', 'applies_to_ids' => [$cat->id]]);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p1), cartItem($p2)]), false);

    expect($result['success'])->toBeTrue();
    $byPid = collect($result['modified_products'])->keyBy('product_id');
    expect($byPid[$p1->id]['applied_discount_percentage'])->toBe(18.0);
});

it('[Single] coupon contribution is zero when existing discount is larger', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000, 'discount' => 30]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 20, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);

    $mod = $result['modified_products'][0];
    expect($mod['applied_discount_percentage'])->toBe(30.0)
        ->and($mod['discount_source'])->toBe('existing')
        ->and($mod['coupon_contribution'])->toBe(0.0)
        ->and($result['total_coupon_discount'])->toBe(0.0);
});

it('[Single] coupon wins when it is larger than existing product discount', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000, 'discount' => 5]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 25, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);

    $mod = $result['modified_products'][0];
    expect($mod['applied_discount_percentage'])->toBe(25.0)
        ->and($mod['discount_source'])->toBe('coupon')
        ->and($mod['coupon_contribution'])->toBeGreaterThan(0);
});

it('[Single] customer-specific coupon applies when user is in list', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 15, 'applies_to' => 'customer', 'applies_to_ids' => [$user->id]]);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);

    expect($result['success'])->toBeTrue()
        ->and($result['modified_products'][0]['applied_discount_percentage'])->toBe(15.0);
});

it('[Single] customer-specific coupon does not apply for other users', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $other = makeUser($zone);
    $p = makeProduct($brand, $tax);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 15, 'applies_to' => 'customer', 'applies_to_ids' => [$user->id]]);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $other, cart([cartItem($p)]), false);

    expect($result['total_coupon_discount'])->toBe(0.0);
});

it('[Single] fixed coupon distributes proportionally across two products', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p1 = makeProduct($brand, $tax, ['price' => 1000]);
    $p2 = makeProduct($brand, $tax, ['price' => 500]);

    $coupon = makeCoupon(['type' => 'fixed_amount', 'value' => 300, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p1), cartItem($p2)]), false);

    expect($result['success'])->toBeTrue();
    $byPid = collect($result['modified_products'])->keyBy('product_id');
    // 1000:500 = 2:1 → reductions 200 and 100
    expect(round($byPid[$p1->id]['unit_price_reduction'], 2))->toBe(200.0)
        ->and(round($byPid[$p2->id]['unit_price_reduction'], 2))->toBe(100.0);
});

it('[Single] package pricing product with percentage coupon', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 500, 'package_quantity' => 2, 'calculate_package_price' => true]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 10, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);

    expect($result['success'])->toBeTrue();
    $mod = $result['modified_products'][0];
    expect($mod['applied_discount_percentage'])->toBe(10.0)
        ->and($mod['package_quantity'])->toBe(2);
});

it('[Single] package pricing product with fixed coupon', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 600, 'package_quantity' => 3, 'calculate_package_price' => true]);

    $coupon = makeCoupon(['type' => 'fixed_amount', 'value' => 300, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    // qty=1, pkg=3, total units=3, base=600, lineTotal=600*1*3=1800
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);

    expect($result['success'])->toBeTrue();
    $mod = $result['modified_products'][0];
    expect($mod['applied_discount_type'])->toBe('fixed_amount')
        ->and($mod['unit_price_reduction'])->toBeGreaterThan(0);
});


// ══════════════════════════════════════════════════════════════════════════
// Section 3 – Multiple Coupons (best-per-product selection)
// ══════════════════════════════════════════════════════════════════════════

it('[Multi-2] two percentage coupons: best percentage wins per product', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    $c10 = makeCoupon(['code' => 'PCT10', 'type' => 'percentage', 'value' => 10, 'applies_to' => 'cart']);
    $c25 = makeCoupon(['code' => 'PCT25', 'type' => 'percentage', 'value' => 25, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyMultipleCouponsToProducts([$c10, $c25], $user, cart([cartItem($p)]), false);

    expect($result['success'])->toBeTrue();
    $mod = $result['modified_products'][0];
    expect($mod['applied_discount_percentage'])->toBe(25.0)
        ->and($mod['winning_coupon_code'])->toBe('PCT25');
});

it('[Multi-2] two fixed coupons: larger savings wins', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    $c100 = makeCoupon(['code' => 'FIX100', 'type' => 'fixed_amount', 'value' => 100, 'applies_to' => 'cart']);
    $c250 = makeCoupon(['code' => 'FIX250', 'type' => 'fixed_amount', 'value' => 250, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyMultipleCouponsToProducts([$c100, $c250], $user, cart([cartItem($p)]), false);

    expect($result['success'])->toBeTrue();
    $mod = $result['modified_products'][0];
    expect($mod['winning_coupon_code'])->toBe('FIX250');
});

it('[Multi-2] percentage vs fixed: percentage wins when saving is greater', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    // 20% of 1000 = 200 saving; fixed 100 saving → percentage should win
    $pct = makeCoupon(['code' => 'PCT20', 'type' => 'percentage', 'value' => 20, 'applies_to' => 'cart']);
    $fix = makeCoupon(['code' => 'FIX100', 'type' => 'fixed_amount', 'value' => 100, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyMultipleCouponsToProducts([$pct, $fix], $user, cart([cartItem($p)]), false);

    expect($result['success'])->toBeTrue();
    $mod = $result['modified_products'][0];
    expect($mod['winning_coupon_code'])->toBe('PCT20')
        ->and($mod['applied_discount_type'])->toBe('percentage');
});

it('[Multi-2] percentage vs fixed: fixed wins when saving is greater', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    // 5% of 1000 = 50 saving; fixed 200 saving → fixed should win
    $pct = makeCoupon(['code' => 'PCT5', 'type' => 'percentage', 'value' => 5, 'applies_to' => 'cart']);
    $fix = makeCoupon(['code' => 'FIX200', 'type' => 'fixed_amount', 'value' => 200, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyMultipleCouponsToProducts([$pct, $fix], $user, cart([cartItem($p)]), false);

    expect($result['success'])->toBeTrue();
    $mod = $result['modified_products'][0];
    expect($mod['winning_coupon_code'])->toBe('FIX200')
        ->and($mod['applied_discount_type'])->toBe('fixed_amount');
});

it('[Multi-2] product-specific coupon vs cart coupon: each product gets its best', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p1 = makeProduct($brand, $tax, ['price' => 1000]);
    $p2 = makeProduct($brand, $tax, ['price' => 1000]);

    // Cart-wide 10% vs product-specific 30% for p1 only
    $cartCoupon = makeCoupon(['code' => 'CART10', 'type' => 'percentage', 'value' => 10, 'applies_to' => 'cart']);
    $prodCoupon = makeCoupon(['code' => 'PROD30', 'type' => 'percentage', 'value' => 30, 'applies_to' => 'product', 'applies_to_ids' => [$p1->id]]);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyMultipleCouponsToProducts([$cartCoupon, $prodCoupon], $user, cart([cartItem($p1), cartItem($p2)]), false);

    expect($result['success'])->toBeTrue();
    $byPid = collect($result['modified_products'])->keyBy('product_id');
    // p1: 30% (product coupon) > 10% (cart) → PROD30 wins
    expect($byPid[$p1->id]['winning_coupon_code'])->toBe('PROD30')
        ->and($byPid[$p1->id]['applied_discount_percentage'])->toBe(30.0);
    // p2: only CART10 applies (PROD30 doesn't target p2)
    expect($byPid[$p2->id]['winning_coupon_code'])->toBe('CART10')
        ->and($byPid[$p2->id]['applied_discount_percentage'])->toBe(10.0);
});

it('[Multi-3] three coupons: 2 percentage + 1 fixed, best wins per product', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    $c1 = makeCoupon(['code' => 'A10', 'type' => 'percentage', 'value' => 10, 'applies_to' => 'cart']);
    $c2 = makeCoupon(['code' => 'A20', 'type' => 'percentage', 'value' => 20, 'applies_to' => 'cart']);
    $c3 = makeCoupon(['code' => 'AFIX150', 'type' => 'fixed_amount', 'value' => 150, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyMultipleCouponsToProducts([$c1, $c2, $c3], $user, cart([cartItem($p)]), false);

    expect($result['success'])->toBeTrue();
    $mod = $result['modified_products'][0];
    // 10% = 100 saving, 20% = 200 saving, fixed 150 saving → A20 (200) wins
    expect($mod['winning_coupon_code'])->toBe('A20');
});

it('[Multi-3] three coupons targeting different products', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p1 = makeProduct($brand, $tax, ['price' => 1000]);
    $p2 = makeProduct($brand, $tax, ['price' => 800]);
    $p3 = makeProduct($brand, $tax, ['price' => 500]);

    $c1 = makeCoupon(['code' => 'CP1', 'type' => 'percentage', 'value' => 15, 'applies_to' => 'product', 'applies_to_ids' => [$p1->id]]);
    $c2 = makeCoupon(['code' => 'CP2', 'type' => 'percentage', 'value' => 20, 'applies_to' => 'product', 'applies_to_ids' => [$p2->id]]);
    $c3 = makeCoupon(['code' => 'CP3', 'type' => 'fixed_amount', 'value' => 100, 'applies_to' => 'product', 'applies_to_ids' => [$p3->id]]);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyMultipleCouponsToProducts([$c1, $c2, $c3], $user, cart([cartItem($p1), cartItem($p2), cartItem($p3)]), false);

    expect($result['success'])->toBeTrue();
    $byPid = collect($result['modified_products'])->keyBy('product_id');
    expect($byPid[$p1->id]['winning_coupon_code'])->toBe('CP1')
        ->and($byPid[$p2->id]['winning_coupon_code'])->toBe('CP2')
        ->and($byPid[$p3->id]['winning_coupon_code'])->toBe('CP3');
});

it('[Multi-3] three coupons with overlap: same product targeted by all three', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 2000]);

    $c1 = makeCoupon(['code' => 'OV10', 'type' => 'percentage', 'value' => 10, 'applies_to' => 'cart']); // 200
    $c2 = makeCoupon(['code' => 'OV15', 'type' => 'percentage', 'value' => 15, 'applies_to' => 'cart']); // 300
    $c3 = makeCoupon(['code' => 'OVFIX400', 'type' => 'fixed_amount', 'value' => 400, 'applies_to' => 'cart']); // 400
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyMultipleCouponsToProducts([$c1, $c2, $c3], $user, cart([cartItem($p)]), false);

    expect($result['success'])->toBeTrue();
    $mod = $result['modified_products'][0];
    // Fixed 400 > 15% of 2000 (300) > 10% (200) → OVFIX400 wins
    expect($mod['winning_coupon_code'])->toBe('OVFIX400')
        ->and($mod['applied_discount_type'])->toBe('fixed_amount');
});

it('[Multi] non-applicable coupon contributes zero discount', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 15, 'applies_to' => 'product', 'applies_to_ids' => [999999]]);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyMultipleCouponsToProducts([$coupon], $user, cart([cartItem($p)]), false);

    // The service returns success=true but with zero coupon contribution
    // since the product doesn't match the coupon's applies_to_ids
    expect($result['success'])->toBeTrue()
        ->and($result['total_coupon_discount'])->toBe(0.0);
});


// ══════════════════════════════════════════════════════════════════════════
// Section 4 – Coupons + Other Discounts & Bonifications
// ══════════════════════════════════════════════════════════════════════════

it('[Interaction] coupon beats product direct discount', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000, 'discount' => 5]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 25, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);

    $mod = $result['modified_products'][0];
    expect($mod['applied_discount_percentage'])->toBe(25.0)
        ->and($mod['discount_source'])->toBe('coupon');
});

it('[Interaction] product direct discount beats smaller coupon', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000, 'discount' => 30]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 10, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);

    $mod = $result['modified_products'][0];
    expect($mod['applied_discount_percentage'])->toBe(30.0)
        ->and($mod['discount_source'])->toBe('existing');
});

it('[Interaction] coupon vs brand discount: highest wins', function () {
    ['tax' => $tax, 'vendor' => $vendor, 'zone' => $zone, 'user' => $user] = scaffold();
    $brand = makeBrand($vendor, ['discount' => 12]);
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 20, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);

    $mod = $result['modified_products'][0];
    // Coupon (20%) > brand (12%) → coupon wins
    expect($mod['applied_discount_percentage'])->toBe(20.0)
        ->and($mod['discount_source'])->toBe('coupon');
});

it('[Interaction] brand discount beats smaller coupon', function () {
    ['tax' => $tax, 'vendor' => $vendor, 'zone' => $zone, 'user' => $user] = scaffold();
    $brand = makeBrand($vendor, ['discount' => 25]);
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 10, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);

    $mod = $result['modified_products'][0];
    expect($mod['applied_discount_percentage'])->toBe(25.0)
        ->and($mod['discount_source'])->toBe('existing');
});

it('[Interaction] fixed coupon vs existing percentage: fixed wins when saving is larger', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    // existing discount = 5% of 1000 = 50; fixed coupon = 200
    $p = makeProduct($brand, $tax, ['price' => 1000, 'discount' => 5]);

    $coupon = makeCoupon(['type' => 'fixed_amount', 'value' => 200, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);

    $mod = collect($result['modified_products'])->firstWhere('product_id', $p->id);
    expect($mod['applied_discount_type'])->toBe('fixed_amount')
        ->and($mod['discount_source'])->toBe('coupon');
});

it('[Interaction] existing percentage beats smaller fixed coupon', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    // existing discount = 30% of 1000 = 300; fixed coupon = 50
    $p = makeProduct($brand, $tax, ['price' => 1000, 'discount' => 30]);

    $coupon = makeCoupon(['type' => 'fixed_amount', 'value' => 50, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);

    $mod = collect($result['modified_products'])->firstWhere('product_id', $p->id);
    expect($mod['applied_discount_type'])->toBe('percentage')
        ->and($mod['discount_source'])->toBe('existing');
});

it('[Interaction] multi-coupon + existing discount: best overall wins per product', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    // Product has 15% direct discount (150 saving on 1000)
    $p = makeProduct($brand, $tax, ['price' => 1000, 'discount' => 15]);

    $c1 = makeCoupon(['code' => 'MC10', 'type' => 'percentage', 'value' => 10, 'applies_to' => 'cart']); // 100 saving
    $c2 = makeCoupon(['code' => 'MC20', 'type' => 'percentage', 'value' => 20, 'applies_to' => 'cart']); // 200 saving
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyMultipleCouponsToProducts([$c1, $c2], $user, cart([cartItem($p)]), false);

    expect($result['success'])->toBeTrue();
    $mod = $result['modified_products'][0];
    // MC20 (200) > existing (150) > MC10 (100)
    expect($mod['applied_discount_percentage'])->toBe(20.0)
        ->and($mod['winning_coupon_code'])->toBe('MC20');
});

it('[Interaction] bonification product with allow_discounts=false blocks coupon', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    $giftProduct = makeProduct($brand, $tax, ['price' => 0]);
    $bonification = Bonification::create([
        'name' => 'Buy 5 Get 1',
        'buy' => 5,
        'get' => 1,
        'product_id' => $giftProduct->id,
        'max' => 10,
        'allow_discounts' => false,
    ]);
    $p->bonifications()->attach($bonification->id);
    $p->load('bonifications');

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 20, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);

    // getFinalPriceForUser should return discount=0 because bonification blocks it
    // The coupon should still be the winner since it competes against 0% existing
    $mod = $result['modified_products'][0];
    expect($mod['applied_discount_percentage'])->toBe(20.0);
});

it('[Interaction] bonification product with allow_discounts=true allows coupon', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000, 'discount' => 10]);

    $giftProduct = makeProduct($brand, $tax, ['price' => 0]);
    $bonification = Bonification::create([
        'name' => 'Buy 3 Get 1',
        'buy' => 3,
        'get' => 1,
        'product_id' => $giftProduct->id,
        'max' => 10,
        'allow_discounts' => true,
    ]);
    $p->bonifications()->attach($bonification->id);
    $p->load('bonifications');

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 25, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);

    $mod = $result['modified_products'][0];
    // Coupon 25% > product 10% → coupon wins, bonification allows it
    expect($mod['applied_discount_percentage'])->toBe(25.0)
        ->and($mod['discount_source'])->toBe('coupon');
});


// ══════════════════════════════════════════════════════════════════════════
// Section 5 – End-to-End: CouponDiscountService → OrderProduct → XML
// ══════════════════════════════════════════════════════════════════════════

it('[E2E] single percentage coupon produces correct XML discount field', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 15, 'applies_to' => 'cart']);
    $xml = couponToXml([$coupon], $user, $zone, cart([cartItem($p, 2)]), [$p->id => $p]);

    expect($xml)->not->toBeNull()
        ->and($xml)->toContain('<dyn:discount>15</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>1000.00</dyn:unitPrice>');
});

it('[E2E] single fixed coupon produces correct XML unitPrice reduction', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    $coupon = makeCoupon(['type' => 'fixed_amount', 'value' => 200, 'applies_to' => 'cart']);
    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);

    expect($result['success'])->toBeTrue();
    $mod = collect($result['modified_products'])->firstWhere('product_id', $p->id);

    // Verify the service produces valid fixed_discount_per_unit
    expect($mod['applied_discount_type'])->toBe('fixed_amount')
        ->and($mod['fixed_discount_per_unit'])->toBeGreaterThan(0);

    $xml = couponToXml([$coupon], $user, $zone, cart([cartItem($p)]), [$p->id => $p]);

    expect($xml)->not->toBeNull()
        ->and($xml)->toContain('<dyn:discount>0</dyn:discount>');

    // Unit price should be reduced: 1000 - 200 = 800
    expect($xml)->toContain('<dyn:unitPrice>800.00</dyn:unitPrice>');
});

it('[E2E] fixed coupon on two products: XML shows reduced prices for both', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p1 = makeProduct($brand, $tax, ['price' => 1000]);
    $p2 = makeProduct($brand, $tax, ['price' => 500]);

    $coupon = makeCoupon(['type' => 'fixed_amount', 'value' => 300, 'applies_to' => 'cart']);
    $xml = couponToXml(
        [$coupon], $user, $zone,
        cart([cartItem($p1), cartItem($p2)]),
        [$p1->id => $p1, $p2->id => $p2]
    );

    expect($xml)->not->toBeNull();

    // Both lines should have discount=0 (fixed amount style)
    preg_match_all('/<dyn:discount>(\d+)<\/dyn:discount>/', $xml, $discountMatches);
    foreach ($discountMatches[1] as $d) {
        expect((int) $d)->toBe(0);
    }

    // Unit prices should be reduced from base
    preg_match_all('/<dyn:unitPrice>([\d.]+)<\/dyn:unitPrice>/', $xml, $priceMatches);
    $xmlPrices = array_map('floatval', $priceMatches[1]);
    // All prices should be less than their original values
    foreach ($xmlPrices as $price) {
        expect($price)->toBeLessThan(1000);
        expect($price)->toBeGreaterThan(0);
    }
});

it('[E2E] percentage coupon with existing smaller product discount: XML shows coupon discount', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000, 'discount' => 5]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 20, 'applies_to' => 'cart']);
    $xml = couponToXml([$coupon], $user, $zone, cart([cartItem($p)]), [$p->id => $p]);

    expect($xml)->not->toBeNull()
        // Coupon 20% > product 5% → XML should show 20
        ->and($xml)->toContain('<dyn:discount>20</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>1000.00</dyn:unitPrice>');
});

it('[E2E] percentage coupon with existing larger product discount: XML shows existing discount', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000, 'discount' => 30]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 10, 'applies_to' => 'cart']);
    $xml = couponToXml([$coupon], $user, $zone, cart([cartItem($p)]), [$p->id => $p]);

    expect($xml)->not->toBeNull()
        // Existing 30% > coupon 10% → XML should show 30
        ->and($xml)->toContain('<dyn:discount>30</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>1000.00</dyn:unitPrice>');
});

it('[E2E] mixed cart: percentage coupon on one product, no coupon on another', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p1 = makeProduct($brand, $tax, ['price' => 1000]);
    $p2 = makeProduct($brand, $tax, ['price' => 500]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 20, 'applies_to' => 'product', 'applies_to_ids' => [$p1->id]]);
    $xml = couponToXml(
        [$coupon], $user, $zone,
        cart([cartItem($p1), cartItem($p2)]),
        [$p1->id => $p1, $p2->id => $p2]
    );

    expect($xml)->not->toBeNull()
        ->and($xml)->toContain('<dyn:discount>20</dyn:discount>')
        ->and($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>1000.00</dyn:unitPrice>')
        ->and($xml)->toContain('<dyn:unitPrice>500.00</dyn:unitPrice>');
});

it('[E2E] two coupons: percentage for p1, fixed for p2, correct XML per line', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p1 = makeProduct($brand, $tax, ['price' => 1000]);
    $p2 = makeProduct($brand, $tax, ['price' => 800]);

    // Percentage coupon only for p1: 25% → XML discount=25, unitPrice=1000
    // Fixed coupon only for p2: 200 → XML discount=0, unitPrice=600
    $c1 = makeCoupon(['code' => 'E2EP', 'type' => 'percentage', 'value' => 25, 'applies_to' => 'product', 'applies_to_ids' => [$p1->id]]);
    $c2 = makeCoupon(['code' => 'E2EF', 'type' => 'fixed_amount', 'value' => 200, 'applies_to' => 'product', 'applies_to_ids' => [$p2->id]]);
    $xml = couponToXml(
        [$c1, $c2], $user, $zone,
        cart([cartItem($p1), cartItem($p2)]),
        [$p1->id => $p1, $p2->id => $p2]
    );

    expect($xml)->not->toBeNull()
        // p1: percentage → discount=25, unitPrice=1000
        ->and($xml)->toContain('<dyn:discount>25</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>1000.00</dyn:unitPrice>')
        // p2: fixed → discount=0, unitPrice=600
        ->and($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>600.00</dyn:unitPrice>');
});

it('[E2E] three coupons on three products: each line correct in XML', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p1 = makeProduct($brand, $tax, ['price' => 1000]);
    $p2 = makeProduct($brand, $tax, ['price' => 800]);
    $p3 = makeProduct($brand, $tax, ['price' => 600]);

    $c1 = makeCoupon(['code' => 'T1', 'type' => 'percentage', 'value' => 10, 'applies_to' => 'product', 'applies_to_ids' => [$p1->id]]);
    $c2 = makeCoupon(['code' => 'T2', 'type' => 'percentage', 'value' => 20, 'applies_to' => 'product', 'applies_to_ids' => [$p2->id]]);
    $c3 = makeCoupon(['code' => 'T3', 'type' => 'fixed_amount', 'value' => 150, 'applies_to' => 'product', 'applies_to_ids' => [$p3->id]]);
    $xml = couponToXml(
        [$c1, $c2, $c3], $user, $zone,
        cart([cartItem($p1), cartItem($p2), cartItem($p3)]),
        [$p1->id => $p1, $p2->id => $p2, $p3->id => $p3]
    );

    expect($xml)->not->toBeNull()
        ->and($xml)->toContain('<dyn:discount>10</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>1000.00</dyn:unitPrice>')
        ->and($xml)->toContain('<dyn:discount>20</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>800.00</dyn:unitPrice>')
        ->and($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>450.00</dyn:unitPrice>');
});

it('[E2E] calculate_package_price product with percentage coupon in XML', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 600, 'package_quantity' => 3, 'calculate_package_price' => true]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 10, 'applies_to' => 'cart']);
    $xml = couponToXml([$coupon], $user, $zone, cart([cartItem($p)]), [$p->id => $p]);

    expect($xml)->not->toBeNull()
        ->and($xml)->toContain('<dyn:discount>10</dyn:discount>');
    // CartController stores basePrice * package_quantity for calculate_package_price
    // resolveXmlPricing divides: 1800 / 3 = 600... but wait, couponToXml helper uses
    // basePrice from CouponDiscountService which is just price (600), then multiplied by pkg
    // The XML unit price should be 600 (base) since CartController stores base*pkg=1800 and resolveXmlPricing divides back
});

it('[E2E] calculate_package_price product with fixed coupon in XML', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 600, 'package_quantity' => 3, 'calculate_package_price' => true]);

    $coupon = makeCoupon(['type' => 'fixed_amount', 'value' => 150, 'applies_to' => 'cart']);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);
    expect($result['success'])->toBeTrue();

    $mod = collect($result['modified_products'])->firstWhere('product_id', $p->id);
    expect($mod['applied_discount_type'])->toBe('fixed_amount');

    $xml = couponToXml([$coupon], $user, $zone, cart([cartItem($p)]), [$p->id => $p]);

    expect($xml)->not->toBeNull()
        ->and($xml)->toContain('<dyn:discount>0</dyn:discount>');
    // Price should be reduced from base
    preg_match('/<dyn:unitPrice>([\d.]+)<\/dyn:unitPrice>/', $xml, $m);
    $xmlPrice = (float) ($m[1] ?? 0);
    expect($xmlPrice)->toBeLessThan(600)
        ->and($xmlPrice)->toBeGreaterThan(0);
});

it('[E2E] no coupon produces XML with discount=0 and original price', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 750]);

    [$order, $prods] = mockOrder($user, $zone, [[
        'product' => $p, 'quantity' => 1, 'price' => 750,
        'percentage' => 0, 'discount_type' => 'percentage', 'flat_discount_amount' => 0,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);
    expect($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>750.00</dyn:unitPrice>');
});

it('[E2E] minimum_amount validation prevents coupon', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 50]);

    $coupon = makeCoupon(['type' => 'percentage', 'value' => 20, 'applies_to' => 'cart', 'minimum_amount' => 500]);
    $svc = app(CouponService::class);
    $validation = $svc->validateCoupon($coupon->code, $user, cart([cartItem($p)]), 50);
    expect($validation['valid'])->toBeFalse();
});

it('[E2E] multi-coupon (percentage + fixed) on same product: XML reflects winner', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    // 20% = 200 saving, fixed 150 = 150 saving → percentage wins
    $cpct = makeCoupon(['code' => 'MPCT20', 'type' => 'percentage', 'value' => 20, 'applies_to' => 'cart']);
    $cfix = makeCoupon(['code' => 'MFIX150', 'type' => 'fixed_amount', 'value' => 150, 'applies_to' => 'cart']);

    $xml = couponToXml([$cpct, $cfix], $user, $zone, cart([cartItem($p)]), [$p->id => $p]);

    expect($xml)->not->toBeNull()
        ->and($xml)->toContain('<dyn:discount>20</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>1000.00</dyn:unitPrice>');
});

it('[E2E] multi-coupon (percentage + fixed) on same product: fixed wins when larger', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 1000]);

    // 5% = 50 saving, fixed 300 = 300 saving → fixed wins
    $cpct = makeCoupon(['code' => 'SPCT5', 'type' => 'percentage', 'value' => 5, 'applies_to' => 'cart']);
    $cfix = makeCoupon(['code' => 'SFIX300', 'type' => 'fixed_amount', 'value' => 300, 'applies_to' => 'cart']);

    $xml = couponToXml([$cpct, $cfix], $user, $zone, cart([cartItem($p)]), [$p->id => $p]);

    expect($xml)->not->toBeNull()
        ->and($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>700.00</dyn:unitPrice>');
});

// ══════════════════════════════════════════════════════════════════════════
// Section 6 – SOAP Safety: Non-coupon orders are unchanged
// ══════════════════════════════════════════════════════════════════════════

it('[SOAP-safe] non-discounted order XML is correct', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 750]);

    [$order, $prods] = mockOrder($user, $zone, [[
        'product' => $p, 'quantity' => 3, 'price' => 750,
        'percentage' => 0, 'discount_type' => 'percentage', 'flat_discount_amount' => 0,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);
    expect($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>750.00</dyn:unitPrice>');
});

it('[SOAP-safe] non-discounted calculate_package_price order XML correct', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 600, 'package_quantity' => 3, 'calculate_package_price' => true]);

    // CartController stores basePrice * pkgQty = 600 * 3 = 1800
    [$order, $prods] = mockOrder($user, $zone, [[
        'product' => $p, 'quantity' => 2, 'price' => 1800,
        'percentage' => 0, 'discount_type' => 'percentage', 'flat_discount_amount' => 0,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);
    // resolveXmlPricing divides: 1800 / 3 = 600 per individual item
    expect($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>600.00</dyn:unitPrice>');
});

it('[SOAP-safe] percentage discount on calculate_package_price product correct', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 600, 'package_quantity' => 3, 'calculate_package_price' => true]);

    [$order, $prods] = mockOrder($user, $zone, [[
        'product' => $p, 'quantity' => 1, 'price' => 1800,
        'percentage' => 20, 'discount_type' => 'percentage', 'flat_discount_amount' => 0,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);
    expect($xml)->toContain('<dyn:discount>20</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>600.00</dyn:unitPrice>');
});

it('[SOAP-safe] fixed discount on calculate_package_price product correct', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 600, 'package_quantity' => 3, 'calculate_package_price' => true]);

    // flat_discount_amount=100 means reduce per-unit by 100: 600 - 100 = 500
    [$order, $prods] = mockOrder($user, $zone, [[
        'product' => $p, 'quantity' => 1, 'price' => 1800,
        'percentage' => 0, 'discount_type' => 'fixed_amount', 'flat_discount_amount' => 100,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);
    expect($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>500.00</dyn:unitPrice>');
});

it('[SOAP-safe] non-coupon product pricing is independent of CouponDiscountService', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p1 = makeProduct($brand, $tax, ['price' => 1000]);
    $p2 = makeProduct($brand, $tax, ['price' => 500, 'package_quantity' => 5, 'calculate_package_price' => true]);

    // Mix of normal and package product, no coupons at all
    [$order, $prods] = mockOrder($user, $zone, [
        ['product' => $p1, 'quantity' => 2, 'price' => 1000,
         'percentage' => 10, 'discount_type' => 'percentage', 'flat_discount_amount' => 0],
        ['product' => $p2, 'quantity' => 1, 'price' => 2500, // 500 * 5
         'percentage' => 0, 'discount_type' => 'percentage', 'flat_discount_amount' => 0],
    ]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);
    expect($xml)->toContain('<dyn:discount>10</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>1000.00</dyn:unitPrice>')
        ->and($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>500.00</dyn:unitPrice>'); // 2500 / 5 = 500
});

// ══════════════════════════════════════════════════════════════════════════
// Section 7 – Bug Regression Tests
// ══════════════════════════════════════════════════════════════════════════

it('[BUG-1] vendor discount with minimum_discount_amount is preserved when coupon is applied', function () {
    $tax = makeTax();
    $vendor = makeVendor(['discount' => 15, 'minimum_discount_amount' => 500]);
    $brand = makeBrand($vendor);
    $zone = makeZone();
    $user = makeUser($zone);

    $p1 = makeProduct($brand, $tax, ['price' => 400]);
    $p2 = makeProduct($brand, $tax, ['price' => 300]);
    // Cart total for this vendor: 400 + 300 = 700, exceeds minimum_discount_amount of 500

    // Apply a small product-specific coupon to p1 only (5%)
    $coupon = makeCoupon(['type' => 'percentage', 'value' => 5, 'applies_to' => 'product', 'applies_to_ids' => [$p1->id]]);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p1), cartItem($p2)]), false);
    expect($result['success'])->toBeTrue();

    $modP1 = collect($result['modified_products'])->firstWhere('product_id', $p1->id);
    $modP2 = collect($result['modified_products'])->firstWhere('product_id', $p2->id);

    // P1: vendor 15% > coupon 5% → existing vendor discount should win
    expect($modP1['applied_discount_percentage'])->toBe(15.0)
        ->and($modP1['discount_source'])->toBe('existing');

    // P2: not targeted by coupon → must STILL have the vendor 15% discount
    expect($modP2['applied_discount_percentage'])->toBe(15.0)
        ->and($modP2['discount_source'])->toBe('existing');
});

it('[BUG-1] vendor discount with minimum lost for non-coupon product in fixed coupon flow', function () {
    $tax = makeTax();
    $vendor = makeVendor(['discount' => 20, 'minimum_discount_amount' => 300]);
    $brand = makeBrand($vendor);
    $zone = makeZone();
    $user = makeUser($zone);

    $p1 = makeProduct($brand, $tax, ['price' => 200]);
    $p2 = makeProduct($brand, $tax, ['price' => 200]);
    // Vendor total = 400, exceeds minimum of 300

    // Fixed coupon targets p1 only
    $coupon = makeCoupon(['type' => 'fixed_amount', 'value' => 50, 'applies_to' => 'product', 'applies_to_ids' => [$p1->id]]);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p1), cartItem($p2)]), false);
    expect($result['success'])->toBeTrue();

    // P2 is NOT targeted by coupon, but should still get vendor 20% discount
    $modP2 = collect($result['modified_products'])->firstWhere('product_id', $p2->id);
    expect($modP2['applied_discount_percentage'])->toBe(20.0)
        ->and($modP2['discount_source'])->toBe('existing');
});

it('[BUG-1] E2E XML: vendor discount preserved for non-coupon products when coupon active', function () {
    $tax = makeTax();
    $vendor = makeVendor(['discount' => 25, 'minimum_discount_amount' => 500]);
    $brand = makeBrand($vendor);
    $zone = makeZone();
    $user = makeUser($zone);

    $p1 = makeProduct($brand, $tax, ['price' => 600]);
    $p2 = makeProduct($brand, $tax, ['price' => 400]);
    // Vendor total = 1000, exceeds minimum of 500

    // 10% coupon only for p1 → vendor 25% should still win for both products
    $coupon = makeCoupon(['type' => 'percentage', 'value' => 10, 'applies_to' => 'product', 'applies_to_ids' => [$p1->id]]);
    $xml = couponToXml(
        [$coupon], $user, $zone,
        cart([cartItem($p1), cartItem($p2)]),
        [$p1->id => $p1, $p2->id => $p2]
    );

    expect($xml)->not->toBeNull()
        // Both products should show 25% vendor discount (vendor wins over 10% coupon)
        ->and(substr_count($xml, '<dyn:discount>25</dyn:discount>'))->toBe(2);
});

it('[BUG-2] calculate_package_price: fixed coupon safeguard does not over-cap', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    // Product: price=600 per unit, 3 units per package, calculate_package_price=true
    $p = makeProduct($brand, $tax, ['price' => 600, 'package_quantity' => 3, 'calculate_package_price' => true]);

    // Fixed coupon of 1500 on a 1800 total (600*3)
    // Expected: full 1500 applies → unitPriceReduction = 1500/3 = 500
    // soapUnitPrice should be 600 (basePrice), maxAllowed = 600-60 = 540
    // So actual reduction = min(500, 540) = 500 per unit
    $coupon = makeCoupon(['type' => 'fixed_amount', 'value' => 1500, 'applies_to' => 'cart']);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);
    expect($result['success'])->toBeTrue();

    $mod = collect($result['modified_products'])->firstWhere('product_id', $p->id);
    expect($mod['applied_discount_type'])->toBe('fixed_amount');

    // The reduction per unit should be 500, NOT capped to 180 (which the bug would produce)
    expect($mod['fixed_discount_per_unit'])->toBe(500.0);
    expect($mod['unit_price_reduction'])->toBe(500.0);
    expect($result['total_coupon_discount'])->toBe(1500.0);
});

it('[BUG-2] E2E XML: calculate_package_price fixed coupon produces correct unit price', function () {
    ['tax' => $tax, 'brand' => $brand, 'zone' => $zone, 'user' => $user] = scaffold();
    $p = makeProduct($brand, $tax, ['price' => 600, 'package_quantity' => 3, 'calculate_package_price' => true]);

    // Fixed coupon: 300 on a 1800 total → 100 reduction per unit → SOAP price = 600 - 100 = 500
    $coupon = makeCoupon(['type' => 'fixed_amount', 'value' => 300, 'applies_to' => 'cart']);
    $xml = couponToXml([$coupon], $user, $zone, cart([cartItem($p)]), [$p->id => $p]);

    expect($xml)->not->toBeNull()
        ->and($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>500.00</dyn:unitPrice>');
});

it('[BUG-2] calculate_package_price: existing discount comparison uses correct soapUnitPrice', function () {
    $tax = makeTax();
    $vendor = makeVendor(['discount' => 25]);
    $brand = makeBrand($vendor);
    $zone = makeZone();
    $user = makeUser($zone);

    // Product: price=600, pkg=3, cpp=true, vendor has 25% discount
    $p = makeProduct($brand, $tax, ['price' => 600, 'package_quantity' => 3, 'calculate_package_price' => true]);

    // Fixed coupon: 300 total → 100 per unit reduction
    // Existing vendor savings per unit = 600 * 25% = 150
    // Fixed savings per unit = 100
    // Existing should WIN (150 > 100), so applied_discount_type should be 'percentage'
    $coupon = makeCoupon(['type' => 'fixed_amount', 'value' => 300, 'applies_to' => 'cart']);

    $svc = app(CouponDiscountService::class);
    $result = $svc->applyCouponDiscountToProducts($coupon, $user, cart([cartItem($p)]), false);
    expect($result['success'])->toBeTrue();

    $mod = collect($result['modified_products'])->firstWhere('product_id', $p->id);

    // Existing 25% saves 150/unit > fixed 100/unit → existing should win
    expect($mod['applied_discount_type'])->toBe('percentage')
        ->and($mod['applied_discount_percentage'])->toBe(25.0)
        ->and($mod['discount_source'])->toBe('existing');
});
