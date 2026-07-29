<?php

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Database\Eloquent\Collection;

it('amount_with_tax adds the percentage to exclusive amounts', function () {
    expect(amount_with_tax(1000, 19))->toBe(1190.0);
    expect(amount_with_tax(1000, 0))->toBe(1000.0);
    expect(amount_with_tax(1000, null))->toBe(1000.0);
});

it('order totalWithTax recomputes lista lines with product IVA', function () {
    $tax = new Tax(['tax' => 19]);
    $product = new Product([
        'price' => 1000,
        'package_quantity' => 1,
        'calculate_package_price' => false,
    ]);
    $product->setRelation('tax', $tax);

    $line = new OrderProduct([
        'quantity' => 1,
        'price' => 1000,
        'percentage' => 0,
        'package_quantity' => 1,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
    ]);
    $line->setRelation('product', $product);

    $order = new Order([
        'total' => 1000, // historically stored without IVA
        'discount' => 0,
    ]);
    $order->setRelation('products', new Collection([$line]));

    expect($order->totalWithTax())->toBe(1190.0);
    expect($order->discountWithTax())->toBe(0.0);
});

it('order totalWithTax applies percentage discount before IVA', function () {
    $tax = new Tax(['tax' => 19]);
    $product = new Product([
        'price' => 1000,
        'package_quantity' => 1,
        'calculate_package_price' => false,
    ]);
    $product->setRelation('tax', $tax);

    $line = new OrderProduct([
        'quantity' => 1,
        'price' => 1000,
        'percentage' => 10,
        'package_quantity' => 1,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
    ]);
    $line->setRelation('product', $product);

    $order = new Order([
        'total' => 900,
        'discount' => 100,
    ]);
    $order->setRelation('products', new Collection([$line]));

    expect($order->totalWithTax())->toBe(1071.0); // 900 * 1.19
    expect($order->discountWithTax())->toBe(119.0); // 100 * 1.19
});
