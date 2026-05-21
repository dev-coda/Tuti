<?php

uses(Tests\TestCase::class);

use App\Models\OrderProduct;
use App\Models\Product;
use App\Repositories\OrderRepository;

/**
 * Unit tests for OrderRepository::resolveXmlPricing (private).
 * Locks canonical SOAP dyn:unitPrice / dyn:discount math from stored OrderProduct + Product flags.
 * No database — in-memory models only.
 */
function invokeResolveXmlPricing(
    OrderProduct $orderProduct,
    Product $productData,
    int $bonification = 0,
    int $orderId = 0,
    bool $logToSoapChannel = false
): array {
    $m = new ReflectionMethod(OrderRepository::class, 'resolveXmlPricing');
    $m->setAccessible(true);

    return $m->invoke(null, $orderProduct, $productData, $bonification, $orderId, $logToSoapChannel);
}

it('resolveXmlPricing: bonification line forces zero unit price and zero discount', function () {
    $op = new OrderProduct([
        'price' => 9999,
        'percentage' => 50,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
        'package_quantity' => 1,
    ]);
    $p = new Product(['calculate_package_price' => false, 'package_quantity' => 1]);

    $r = invokeResolveXmlPricing($op, $p, 1, 0, false);

    // Bonification branch returns numeric 0 (not parseCurrency-formatted).
    expect($r['unit_price'])->toBe(0)
        ->and($r['discount_percentage'])->toBe(0)
        ->and($r['discount_type'])->toBe('bonification');
});

it('resolveXmlPricing: plain stored price passes through as unit price with percentage discount', function () {
    $op = new OrderProduct([
        'price' => 750,
        'percentage' => 12,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
        'package_quantity' => 1,
    ]);
    $p = new Product(['calculate_package_price' => false, 'package_quantity' => 1]);

    $r = invokeResolveXmlPricing($op, $p, 0, 0, false);

    expect($r['unit_price'])->toBe('750.00')
        ->and($r['discount_percentage'])->toBe(12)
        ->and($r['discount_type'])->toBe('percentage');
});

it('resolveXmlPricing: calculate_package_price divides stored line price by package_quantity', function () {
    $op = new OrderProduct([
        'price' => 1800,
        'percentage' => 0,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
        'package_quantity' => 3,
    ]);
    $p = new Product(['calculate_package_price' => true, 'package_quantity' => 3]);

    $r = invokeResolveXmlPricing($op, $p, 0, 0, false);

    expect($r['unit_price'])->toBe('600.00')
        ->and($r['discount_percentage'])->toBe(0);
});

it('resolveXmlPricing: percentage discount on package product keeps unit from division', function () {
    $op = new OrderProduct([
        'price' => 1800,
        'percentage' => 20,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
        'package_quantity' => 3,
    ]);
    $p = new Product(['calculate_package_price' => true, 'package_quantity' => 3]);

    $r = invokeResolveXmlPricing($op, $p, 0, 0, false);

    expect($r['unit_price'])->toBe('600.00')
        ->and($r['discount_percentage'])->toBe(20);
});

it('resolveXmlPricing: fixed_amount reduces unit price and clears dyn discount', function () {
    $op = new OrderProduct([
        'price' => 1800,
        'percentage' => 0,
        'discount_type' => 'fixed_amount',
        'flat_discount_amount' => 100,
        'package_quantity' => 3,
    ]);
    $p = new Product(['calculate_package_price' => true, 'package_quantity' => 3]);

    $r = invokeResolveXmlPricing($op, $p, 0, 0, false);

    expect($r['unit_price'])->toBe('500.00')
        ->and($r['discount_percentage'])->toBe(0)
        ->and($r['discount_type'])->toBe('fixed_amount');
});

it('resolveXmlPricing: percentage is rounded to integer for XML', function () {
    $op = new OrderProduct([
        'price' => 1000,
        'percentage' => 14.6,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
        'package_quantity' => 1,
    ]);
    $p = new Product(['calculate_package_price' => false, 'package_quantity' => 1]);

    $r = invokeResolveXmlPricing($op, $p, 0, 0, false);

    expect($r['discount_percentage'])->toBe(15);
});

it('resolveXmlPricing: fixed_amount branch ignored when flat_discount_amount is zero', function () {
    $op = new OrderProduct([
        'price' => 1000,
        'percentage' => 10,
        'discount_type' => 'fixed_amount',
        'flat_discount_amount' => 0,
        'package_quantity' => 1,
    ]);
    $p = new Product(['calculate_package_price' => false, 'package_quantity' => 1]);

    $r = invokeResolveXmlPricing($op, $p, 0, 0, false);

    expect($r['unit_price'])->toBe('1000.00')
        ->and($r['discount_percentage'])->toBe(10);
});
