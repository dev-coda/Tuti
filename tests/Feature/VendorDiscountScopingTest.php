<?php
use App\Models\Brand;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
uses(RefreshDatabase::class);
function scopedProduct(Vendor $vendor, string $slug, float $brandDiscount = 0): Product
{
    $tax = Tax::firstOrCreate(['name' => 'IVA scope'], ['tax' => 0]);
    $brand = Brand::create(['name' => 'Marca '.$slug, 'slug' => 'marca-'.$slug, 'vendor_id' => $vendor->id, 'active' => 1, 'discount' => $brandDiscount]);
    return Product::create(['name' => 'Producto '.$slug, 'description' => 'd', 'short_description' => 'd', 'sku' => 'SKU-'.$slug, 'slug' => $slug, 'active' => 1, 'price' => 10000, 'delivery_days' => 1, 'discount' => 0, 'quantity_min' => 1, 'quantity_max' => 100, 'step' => 1, 'tax_id' => $tax->id, 'brand_id' => $brand->id, 'package_quantity' => 1]);
}
it('applies a vendor discount only to that vendor own brands', function () {
    $vendorA = Vendor::create(['name' => 'Vendor A', 'slug' => 'vendor-a-scope', 'minimum_purchase' => 0, 'active' => 1, 'discount' => 20]);
    $vendorB = Vendor::create(['name' => 'Vendor B', 'slug' => 'vendor-b-scope', 'minimum_purchase' => 0, 'active' => 1, 'discount' => 0]);
    $own = scopedProduct($vendorA, 'propio-scope')->load('brand.vendor');
    $other = scopedProduct($vendorB, 'ajeno-scope')->load('brand.vendor');
    expect((float) $own->getFinalPriceForUser(false)['discount'])->toBe(20.0)
        ->and($own->getFinalPriceForUser(false)['discount_on'])->toBe('Vendor')
        ->and((float) $other->getFinalPriceForUser(false)['discount'])->toBe(0.0)
        ->and($other->getFinalPriceForUser(false)['has_discount'])->toBeFalse();
});
it('applies a brand discount only to products of that brand and leaves no-discount baseline unchanged', function () {
    $vendor = Vendor::create(['name' => 'Vendor C', 'slug' => 'vendor-c-scope', 'minimum_purchase' => 0, 'active' => 1, 'discount' => 0]);
    $withBrand = scopedProduct($vendor, 'con-marca-scope', 15)->load('brand.vendor');
    $plain = scopedProduct($vendor, 'sin-descuento-scope')->load('brand.vendor');
    expect((float) $withBrand->getFinalPriceForUser(false)['discount'])->toBe(15.0)
        ->and((float) $plain->getFinalPriceForUser(false)['discount'])->toBe(0.0)
        ->and((float) $plain->getFinalPriceForUser(false)['price'])->toBe(10000.0);
});
