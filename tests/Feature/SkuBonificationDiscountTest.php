<?php

use App\Models\Bonification;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function skuDiscountCatalog(): array
{
    $vendor = Vendor::create([
        'name' => 'Tronex sku',
        'slug' => 'tronex-sku-'.uniqid(),
        'minimum_purchase' => 0,
        'active' => 1,
        'discount' => 5,
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
    ]);
    $brand = Brand::create([
        'name' => 'Encendedores sku',
        'slug' => 'encendedores-sku-'.uniqid(),
        'vendor_id' => $vendor->id,
        'active' => 1,
        'discount' => 5,
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
    ]);
    $tax = Tax::create(['name' => 'IVA sku '.uniqid(), 'tax' => 19]);

    return [$brand, $tax];
}

function skuDiscountProduct(Brand $brand, Tax $tax, string $name, string $sku): Product
{
    return Product::create([
        'name' => $name,
        'description' => $name,
        'short_description' => $name,
        'sku' => $sku,
        'slug' => str($name.' '.uniqid())->slug(),
        'active' => 1,
        'price' => 6470.59,
        'delivery_days' => 1,
        'discount' => 0,
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);
}

it('keeps the brand discount on a product that shares an sku with a bonification product', function () {
    [$brand, $tax] = skuDiscountCatalog();
    $regular = skuDiscountProduct($brand, $tax, 'Encendedor Jett Solido', 'SLJETT');
    $offer = skuDiscountProduct($brand, $tax, 'Pague 6 lleve 7 encendedor Jett', 'SLJETT');

    $bonification = Bonification::create([
        'name' => 'Pague 6 lleve 7 encendedor jett',
        'buy' => 6,
        'get' => 1,
        'product_id' => $regular->id,
        'max' => 1000,
        'allow_discounts' => false,
    ]);
    $regular->bonifications()->attach($bonification->id);
    $offer->bonifications()->attach($bonification->id);

    $price = $regular->fresh()->load('brand.vendor', 'bonifications')->getFinalPriceForUser(false);

    expect($price['has_discount'])->toBeTrue()
        ->and((float) $price['discount'])->toBe(5.0);
});

it('still removes the discount when the bonification belongs to this product alone', function () {
    [$brand, $tax] = skuDiscountCatalog();
    $gift = skuDiscountProduct($brand, $tax, 'Regalo', 'GIFT-ONLY');
    $offer = skuDiscountProduct($brand, $tax, 'Pague 6 lleve 7', 'OFFER-ONLY');

    $bonification = Bonification::create([
        'name' => 'Solo esta oferta',
        'buy' => 6,
        'get' => 1,
        'product_id' => $gift->id,
        'max' => 10,
        'allow_discounts' => false,
    ]);
    $offer->bonifications()->attach($bonification->id);

    $price = $offer->fresh()->load('brand.vendor', 'bonifications')->getFinalPriceForUser(false);

    expect($price['has_discount'])->toBeFalse()
        ->and((float) $price['discount'])->toBe(0.0);
});
