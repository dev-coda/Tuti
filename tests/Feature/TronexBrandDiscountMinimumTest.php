<?php

use App\Models\Brand;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Vendor;
use App\Services\DiscountService;

/*
| Tronex brands carry a 5% discount that must apply only when that vendor's
| purchase reaches minimum_discount_amount (20.000 COP in production).
| A 2.000 COP product must not receive it.
*/

function tronexMinimumProduct(float $price, float $minimum, float $productDiscount = 0, int $packageQuantity = 1): Product
{
    $tax = Tax::create(['name' => 'IVA 0 '.uniqid(), 'tax' => 0]);

    $vendor = Vendor::create([
        'name' => 'Tronex',
        'slug' => 'tronex-'.uniqid(),
        'minimum_purchase' => 0,
        'minimum_discount_amount' => $minimum,
        'active' => true,
        'discount' => 5,
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
    ]);

    $brand = Brand::create([
        'name' => 'Tronex Brand',
        'slug' => 'tronex-brand-'.uniqid(),
        'active' => true,
        'discount' => 5,
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
        'vendor_id' => $vendor->id,
    ]);

    return Product::create([
        'name' => 'Pila '.uniqid(),
        'slug' => 'pila-'.uniqid(),
        'sku' => 'TRX-'.uniqid(),
        'price' => $price,
        'discount' => $productDiscount,
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
        'delivery_days' => 1,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'package_quantity' => $packageQuantity,
        'active' => true,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'exclude_from_brand_discount' => false,
        'exclude_from_vendor_discount' => false,
        'safety_stock' => 0,
    ])->load('brand.vendor', 'bonifications', 'tax');
}

it('does not apply the Tronex 5% on a 2000 COP product', function () {
    $product = tronexMinimumProduct(2000, 20000);

    $catalog = $product->getFinalPriceForUser(false);
    $smallCart = $product->getFinalPriceForUser(false, 2000);
    $justUnder = $product->getFinalPriceForUser(false, 19999);

    expect($catalog['discount'])->toBe(0)
        ->and($catalog['has_discount'])->toBeFalse()
        ->and((float) $catalog['price'])->toBe(2000.0)
        ->and($smallCart['discount'])->toBe(0)
        ->and($justUnder['discount'])->toBe(0)
        ->and($product->hasAnyDiscountForAutoTag())->toBeFalse()
        ->and($product->getStaticDiscountInfo())->toBeNull();
});

it('applies the Tronex 5% once the purchase reaches 20000 COP', function () {
    $product = tronexMinimumProduct(2000, 20000);

    $qualified = $product->getFinalPriceForUser(false, 20000);

    expect($qualified['discount'])->toBe(5)
        ->and($qualified['has_discount'])->toBeTrue()
        ->and((float) $qualified['price'])->toBe(1900.0);
});

it('shows the 5% on a catalog product that already costs 20000 COP', function () {
    $product = tronexMinimumProduct(20000, 20000);

    $catalog = $product->getFinalPriceForUser(false);

    expect($catalog['discount'])->toBe(5)
        ->and((float) $catalog['price'])->toBe(19000.0);
});

it('keeps a product discount when the 20000 COP minimum is not met', function () {
    $product = tronexMinimumProduct(2000, 20000, productDiscount: 15);

    $result = $product->getFinalPriceForUser(false, 2000);

    expect((float) $result['discount'])->toBe(15.0)
        ->and($result['discount_on'])->toBe('Producto')
        ->and((float) $result['price'])->toBe(1700.0);
});

it('still applies a brand discount immediately when the vendor has no purchase minimum', function () {
    $product = tronexMinimumProduct(2000, 0);

    $result = $product->getFinalPriceForUser(false);

    expect($result['discount'])->toBe(5)
        ->and((float) $result['price'])->toBe(1900.0);
});

it('does not let a 2000 COP line qualify through package quantity below 20000', function () {
    $product = tronexMinimumProduct(2000, 20000, packageQuantity: 9);

    $catalog = $product->getFinalPriceForUser(false);

    expect($catalog['discount'])->toBe(0)
        ->and((float) $catalog['price'])->toBe(18000.0);
});

it('DiscountService withholds the brand 5% until the vendor subtotal reaches 20000', function () {
    $cheap = tronexMinimumProduct(2000, 20000);
    $service = new DiscountService();

    $below = $service->applyBestDiscount(collect([
        ['product_id' => $cheap->id, 'quantity' => 1, 'variation_id' => null],
    ]), \App\Models\User::factory()->create(), false);

    expect($below['amount'])->toBe(0);

    $enough = tronexMinimumProduct(10000, 20000);
    $enough->brand->vendor->update(['discount' => 0]);

    $met = $service->applyBestDiscount(collect([
        ['product_id' => $enough->id, 'quantity' => 2, 'variation_id' => null],
    ]), \App\Models\User::factory()->create(), false);

    expect($met['type'])->toBe('direct')
        ->and($met['amount'])->toBe(1000.0);
});
