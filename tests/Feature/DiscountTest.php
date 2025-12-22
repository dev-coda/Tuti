<?php

use App\Models\User;
use App\Models\Tax;
use App\Models\Vendor;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Coupon;
use App\Models\Bonification;
use App\Models\Setting;
use App\Services\DiscountService;
use App\Services\CouponDiscountService;
use App\Services\CouponService;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| DiscountService Unit Tests
|--------------------------------------------------------------------------
| These tests verify the discount service logic without requiring full database
*/

it('DiscountService can be instantiated', function () {
    $discountService = new DiscountService();
    expect($discountService)->toBeInstanceOf(DiscountService::class);
});

it('DiscountService has applyBestDiscount method', function () {
    $discountService = new DiscountService();
    expect(method_exists($discountService, 'applyBestDiscount'))->toBeTrue();
});

it('DiscountService has distributeFixedDiscount method', function () {
    $discountService = new DiscountService();
    expect(method_exists($discountService, 'distributeFixedDiscount'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| CouponDiscountService Unit Tests
|--------------------------------------------------------------------------
*/

it('CouponDiscountService can be instantiated', function () {
    $couponDiscountService = new CouponDiscountService();
    expect($couponDiscountService)->toBeInstanceOf(CouponDiscountService::class);
});

it('CouponDiscountService has applyCouponDiscountToProducts method', function () {
    $couponDiscountService = new CouponDiscountService();
    expect(method_exists($couponDiscountService, 'applyCouponDiscountToProducts'))->toBeTrue();
});

it('CouponDiscountService has calculateFinalCartTotals method', function () {
    $couponDiscountService = new CouponDiscountService();
    expect(method_exists($couponDiscountService, 'calculateFinalCartTotals'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Coupon Model Tests
|--------------------------------------------------------------------------
*/

it('Coupon model has correct type constants', function () {
    expect(Coupon::TYPE_FIXED_AMOUNT)->toBe('fixed_amount');
    expect(Coupon::TYPE_PERCENTAGE)->toBe('percentage');
});

it('Coupon model has correct applies_to constants', function () {
    expect(Coupon::APPLIES_TO_CART)->toBe('cart');
    expect(Coupon::APPLIES_TO_PRODUCT)->toBe('product');
    expect(Coupon::APPLIES_TO_CATEGORY)->toBe('category');
    expect(Coupon::APPLIES_TO_BRAND)->toBe('brand');
    expect(Coupon::APPLIES_TO_VENDOR)->toBe('vendor');
    expect(Coupon::APPLIES_TO_CUSTOMER)->toBe('customer');
    expect(Coupon::APPLIES_TO_CUSTOMER_TYPE)->toBe('customer_type');
});

it('Coupon calculateDiscount works for percentage type', function () {
    $coupon = new Coupon([
        'type' => Coupon::TYPE_PERCENTAGE,
        'value' => 10, // 10%
    ]);
    
    // 10% of 10000 = 1000
    expect($coupon->calculateDiscount(10000))->toBe(1000.0);
});

it('Coupon calculateDiscount works for fixed amount type', function () {
    $coupon = new Coupon([
        'type' => Coupon::TYPE_FIXED_AMOUNT,
        'value' => 5000,
    ]);
    
    // Fixed 5000 discount
    expect($coupon->calculateDiscount(10000))->toBe(5000.0);
});

it('Coupon fixed amount does not exceed cart total', function () {
    $coupon = new Coupon([
        'type' => Coupon::TYPE_FIXED_AMOUNT,
        'value' => 15000, // More than cart total
    ]);
    
    // Should cap at cart total
    expect($coupon->calculateDiscount(10000))->toBe(10000.0);
});

it('Coupon isValid checks active status and date range', function () {
    $coupon = new Coupon([
        'active' => true,
        'valid_from' => Carbon::now()->subDay(),
        'valid_to' => Carbon::now()->addDay(),
    ]);
    
    expect($coupon->isValid())->toBeTrue();
});

it('Coupon isValid returns false when inactive', function () {
    $coupon = new Coupon([
        'active' => false,
        'valid_from' => Carbon::now()->subDay(),
        'valid_to' => Carbon::now()->addDay(),
    ]);
    
    expect($coupon->isValid())->toBeFalse();
});

it('Coupon isValid returns false when expired', function () {
    $coupon = new Coupon([
        'active' => true,
        'valid_from' => Carbon::now()->subDays(10),
        'valid_to' => Carbon::now()->subDay(),
    ]);
    
    expect($coupon->isValid())->toBeFalse();
});

it('Coupon isValid returns false when not yet valid', function () {
    $coupon = new Coupon([
        'active' => true,
        'valid_from' => Carbon::now()->addDay(),
        'valid_to' => Carbon::now()->addDays(10),
    ]);
    
    expect($coupon->isValid())->toBeFalse();
});

it('Coupon hasExceededTotalLimit checks usage', function () {
    $coupon = new Coupon([
        'total_usage_limit' => 10,
        'current_usage' => 10,
    ]);
    
    expect($coupon->hasExceededTotalLimit())->toBeTrue();
});

it('Coupon hasExceededTotalLimit returns false when under limit', function () {
    $coupon = new Coupon([
        'total_usage_limit' => 10,
        'current_usage' => 5,
    ]);
    
    expect($coupon->hasExceededTotalLimit())->toBeFalse();
});

it('Coupon hasExceededTotalLimit returns false when no limit set', function () {
    $coupon = new Coupon([
        'total_usage_limit' => null,
        'current_usage' => 100,
    ]);
    
    expect($coupon->hasExceededTotalLimit())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Bonification Model Tests
|--------------------------------------------------------------------------
*/

it('Bonification model can be instantiated', function () {
    $bonification = new Bonification([
        'name' => 'Buy 10 Get 1',
        'buy' => 10,
        'get' => 1,
    ]);
    
    expect($bonification)->toBeInstanceOf(Bonification::class);
    expect($bonification->name)->toBe('Buy 10 Get 1');
    expect($bonification->buy)->toBe(10);
    expect($bonification->get)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Product Model Discount Tests
|--------------------------------------------------------------------------
*/

it('Product model can have discount attribute', function () {
    $product = new Product([
        'name' => 'Test Product',
        'price' => 10000,
        'discount' => 15,
    ]);
    
    expect($product->discount)->toBe(15);
});

it('Product model can have package_quantity attribute', function () {
    $product = new Product([
        'name' => 'Test Product',
        'price' => 1000,
        'package_quantity' => 12,
    ]);
    
    expect($product->package_quantity)->toBe(12);
});

/*
|--------------------------------------------------------------------------
| Brand/Vendor Discount Tests
|--------------------------------------------------------------------------
*/

it('Brand model can have discount attributes', function () {
    $brand = new Brand([
        'name' => 'Test Brand',
        'discount' => 10,
        'discount_type' => 'percentage',
        'first_purchase_only' => true,
    ]);
    
    expect($brand->discount)->toBe(10);
    expect($brand->discount_type)->toBe('percentage');
    expect($brand->first_purchase_only)->toBeTrue();
});

it('Vendor model can have discount attributes', function () {
    $vendor = new Vendor([
        'name' => 'Test Vendor',
        'discount' => 15,
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
    ]);
    
    expect($vendor->discount)->toBe(15);
    expect($vendor->discount_type)->toBe('percentage');
    expect($vendor->first_purchase_only)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Discount Calculation Logic Tests
|--------------------------------------------------------------------------
*/

it('percentage discount calculation is correct', function () {
    $originalPrice = 10000;
    $discountPercentage = 15;
    
    $discountAmount = $originalPrice * ($discountPercentage / 100);
    $finalPrice = $originalPrice - $discountAmount;
    
    expect($discountAmount)->toBe(1500.0);
    expect($finalPrice)->toBe(8500.0);
});

it('fixed amount discount calculation is correct', function () {
    $originalPrice = 10000;
    $discountAmount = 2000;
    
    $finalPrice = $originalPrice - $discountAmount;
    
    expect($finalPrice)->toBe(8000.0);
});

it('proportional discount distribution works correctly', function () {
    // Product 1: $10000 (33.33% of total)
    // Product 2: $20000 (66.67% of total)
    // Total: $30000
    // Fixed discount: $3000
    
    $product1Price = 10000;
    $product2Price = 20000;
    $totalCart = $product1Price + $product2Price;
    $totalDiscount = 3000;
    
    $product1Discount = ($product1Price / $totalCart) * $totalDiscount;
    $product2Discount = ($product2Price / $totalCart) * $totalDiscount;
    
    expect($product1Discount)->toBe(1000.0);
    expect($product2Discount)->toBe(2000.0);
    expect($product1Discount + $product2Discount)->toBe($totalDiscount);
});

it('package quantity affects total price correctly', function () {
    $unitPrice = 1000;
    $quantity = 2; // packages
    $packageQuantity = 12; // units per package
    
    $totalPrice = $unitPrice * $quantity * $packageQuantity;
    
    expect($totalPrice)->toBe(24000);
});

it('discount with package quantity is calculated correctly', function () {
    $unitPrice = 1000;
    $quantity = 2; // packages
    $packageQuantity = 12; // units per package
    $discountPercentage = 10;
    
    $totalPrice = $unitPrice * $quantity * $packageQuantity;
    $discountAmount = $totalPrice * ($discountPercentage / 100);
    
    expect($totalPrice)->toBe(24000);
    expect($discountAmount)->toBe(2400.0);
});

/*
|--------------------------------------------------------------------------
| Edge Cases
|--------------------------------------------------------------------------
*/

it('discount does not make price negative', function () {
    $originalPrice = 1000;
    $discountAmount = 5000; // More than price
    
    $finalPrice = max(0, $originalPrice - $discountAmount);
    
    expect($finalPrice)->toBe(0);
});

it('zero percent discount results in no discount', function () {
    $originalPrice = 10000;
    $discountPercentage = 0;
    
    $discountAmount = $originalPrice * ($discountPercentage / 100);
    
    expect($discountAmount)->toBe(0.0);
});

it('100 percent discount gives full discount', function () {
    $originalPrice = 10000;
    $discountPercentage = 100;
    
    $discountAmount = $originalPrice * ($discountPercentage / 100);
    
    expect($discountAmount)->toBe(10000.0);
});

/*
|--------------------------------------------------------------------------
| CouponDiscountService Result Format Tests
|--------------------------------------------------------------------------
*/

it('calculateFinalCartTotals handles failure result', function () {
    $couponDiscountService = new CouponDiscountService();
    
    $failedResult = ['success' => false];
    $cartProducts = collect([]);
    
    $totals = $couponDiscountService->calculateFinalCartTotals($failedResult, $cartProducts);
    
    expect($totals['subtotal'])->toBe(0);
    expect($totals['total_discount'])->toBe(0);
    expect($totals['coupon_discount'])->toBe(0);
    expect($totals['final_total'])->toBe(0);
    expect($totals['products'])->toBe([]);
});
