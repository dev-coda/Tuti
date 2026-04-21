<?php

namespace Tests\Helpers;

use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use Illuminate\Support\Collection;

/**
 * Shared fixtures for SOAP/XML pricing tests (OrderRepository::buildOrderXmlForDiagnostic).
 * Keeps E2E scenarios aligned with CouponXmlTest helpers without loading that file twice.
 */
final class SoapXmlPricing
{
    public static function makeTax(): Tax
    {
        return Tax::create(['name' => 'Tax '.uniqid(), 'tax' => 0]);
    }

    public static function makeVendor(array $overrides = []): Vendor
    {
        return Vendor::create(array_merge([
            'name' => 'Vendor '.uniqid(),
            'slug' => 'vendor-'.uniqid(),
            'vendor_type' => 'V',
            'minimum_purchase' => 0,
            'active' => 1,
        ], $overrides));
    }

    public static function makeBrand(Vendor $vendor, array $overrides = []): Brand
    {
        return Brand::create(array_merge([
            'name' => 'Brand '.uniqid(),
            'slug' => 'brand-'.uniqid(),
            'vendor_id' => $vendor->id,
        ], $overrides));
    }

    public static function makeZone(): Zone
    {
        return Zone::create([
            'route' => 'R'.substr(uniqid(), -3),
            'zone' => 'Z'.substr(uniqid(), -3),
            'day' => 'Lunes',
            'address' => 'Test',
            'code' => 'T'.substr(uniqid(), -4),
        ]);
    }

    public static function makeUser(Zone $zone): User
    {
        $user = User::factory()->create([
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ]);
        $zone->update(['user_id' => $user->id]);

        return $user;
    }

    public static function makeProduct(Brand $brand, Tax $tax, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Product '.uniqid(),
            'slug' => 'product-'.uniqid(),
            'description' => '',
            'short_description' => '',
            'sku' => 'SKU-'.strtoupper(substr(uniqid(), -8)),
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

    /**
     * @param  array<int, array{product: Product, quantity: int, price: float|int, percentage?: float|int, discount_type?: string, flat_discount_amount?: float|int, variation_item_id?: int|null}>  $lines
     * @return array{0: Order, 1: Collection<int, OrderProduct>}
     */
    public static function mockOrder(User $user, Zone $zone, array $lines): array
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
}
