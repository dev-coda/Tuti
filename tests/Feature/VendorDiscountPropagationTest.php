<?php

use App\Models\Brand;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('copies a proveedor percentage discount onto linked brands without lowering a higher brand discount', function () {
    $vendor = Vendor::create([
        'name' => 'Eterna',
        'slug' => 'eterna-prop',
        'minimum_purchase' => 0,
        'active' => 1,
        'discount' => 10,
        'discount_type' => 'percentage',
        'first_purchase_only' => true,
    ]);
    $other = Vendor::create([
        'name' => 'Otro',
        'slug' => 'otro-prop',
        'minimum_purchase' => 0,
        'active' => 1,
        'discount' => 0,
        'discount_type' => 'percentage',
    ]);

    $plain = Brand::create([
        'name' => 'Sin descuento',
        'slug' => 'sin-descuento-prop',
        'vendor_id' => $vendor->id,
        'active' => 1,
        'discount' => 0,
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
    ]);
    $higher = Brand::create([
        'name' => 'Mayor',
        'slug' => 'mayor-prop',
        'vendor_id' => $vendor->id,
        'active' => 1,
        'discount' => 15,
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
    ]);
    $unrelated = Brand::create([
        'name' => 'Ajena',
        'slug' => 'ajena-prop',
        'vendor_id' => $other->id,
        'active' => 1,
        'discount' => 0,
        'discount_type' => 'percentage',
    ]);

    expect($vendor->propagateDiscountToBrands())->toBe(1);

    $plain->refresh();
    $higher->refresh();
    $unrelated->refresh();

    expect((float) $plain->discount)->toBe(10.0)
        ->and($plain->discount_type)->toBe('percentage')
        ->and((bool) $plain->first_purchase_only)->toBeTrue()
        ->and((float) $higher->discount)->toBe(15.0)
        ->and((bool) $higher->first_purchase_only)->toBeFalse()
        ->and((float) $unrelated->discount)->toBe(0.0);

    $tax = Tax::firstOrCreate(['name' => 'IVA prop'], ['tax' => 0]);
    $product = Product::create([
        'name' => 'Producto prop',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'SKU-PROP',
        'slug' => 'producto-prop',
        'active' => 1,
        'price' => 10000,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $plain->id,
        'package_quantity' => 1,
    ])->load('brand.vendor');

    expect((float) $product->getFinalPriceForUser(false)['discount'])->toBe(10.0);
});

it('inherits the proveedor discount when a linked brand is saved without its own discount', function () {
    $vendor = Vendor::create([
        'name' => 'Eterna save',
        'slug' => 'eterna-save',
        'minimum_purchase' => 0,
        'active' => 1,
        'discount' => 12,
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
    ]);

    $attributes = Vendor::applyInheritedDiscount([
        'vendor_id' => $vendor->id,
        'discount' => 0,
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
    ]);

    expect((float) $attributes['discount'])->toBe(12.0)
        ->and($attributes['discount_type'])->toBe('percentage');

    $kept = Vendor::applyInheritedDiscount([
        'vendor_id' => $vendor->id,
        'discount' => 20,
        'discount_type' => 'percentage',
    ]);

    expect((float) $kept['discount'])->toBe(20.0);
});
