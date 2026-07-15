<?php

use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\PackageType;
use App\Models\Product;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Services\Shipping\CoordinadoraGuideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('includes assigned empaques in the coordinadora guide payload', function () {
    PackageType::create(['code' => 'S', 'name' => 'Pequeño', 'max_weight_kg' => 3, 'max_length_cm' => 30, 'max_width_cm' => 25, 'max_height_cm' => 15, 'position' => 1, 'active' => true]);
    PackageType::create(['code' => 'L', 'name' => 'Grande', 'max_weight_kg' => 15, 'max_length_cm' => 60, 'max_width_cm' => 40, 'max_height_cm' => 40, 'position' => 2, 'active' => true]);

    $tax = Tax::create(['name' => 'IVA 0', 'tax' => 0]);
    $vendor = Vendor::create(['name' => 'Vendor', 'slug' => 'vendor-guide', 'vendor_type' => 'V', 'minimum_purchase' => 0, 'active' => 1]);
    $brand = Brand::create(['name' => 'Brand', 'slug' => 'brand-guide', 'vendor_id' => $vendor->id]);
    $product = Product::create([
        'name' => 'Producto Guia',
        'slug' => 'producto-guia',
        'description' => '',
        'short_description' => '',
        'sku' => 'SKU-GUIDE-001',
        'active' => 1,
        'price' => 10000,
        'delivery_days' => 1,
        'discount' => 0,
        'discount_type' => 'percentage',
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
        'calculate_package_price' => false,
        'coordinadora_weight_kg' => 0.5,
        'coordinadora_height_cm' => 10,
        'coordinadora_width_cm' => 8,
        'coordinadora_length_cm' => 12,
    ]);

    $zone = Zone::create([
        'route' => 'R1',
        'zone' => 'Z1',
        'day' => '1',
        'address' => 'Calle 1',
        'code' => 'C001',
        'zip_code' => '110111',
        'fulfillment_provider_48h' => 'coordinadora',
    ]);
    $user = User::create([
        'name' => 'Cliente Guia',
        'email' => 'guide-test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'total' => 10000,
        'discount' => 0,
        'status_id' => Order::STATUS_PENDING,
        'zone_id' => $zone->id,
        'delivery_method' => Order::DELIVERY_METHOD_EXPRESS,
        'shipping_provider' => Order::SHIPPING_PROVIDER_COORDINADORA,
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 2,
        'price' => 10000,
        'discount' => 0,
        'percentage' => 0,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
        'package_quantity' => 1,
    ]);

    config([
        'services.coordinadora.oauth_url' => 'https://coordinadora.test/oauth',
        'services.coordinadora.base_url' => 'https://coordinadora.test',
        'services.coordinadora.key' => 'k',
        'services.coordinadora.secret' => 's',
        'services.coordinadora.id_proceso' => '11577',
    ]);

    Http::fake([
        'https://coordinadora.test/oauth' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
        'https://coordinadora.test/guides' => Http::response(['guide_number' => '90012345678'], 200),
    ]);

    $result = app(CoordinadoraGuideService::class)->createGuide($order);

    expect($result['guide_number'])->toBe('90012345678');
    expect($result['packages'])->toHaveCount(1);
    expect($result['packages'][0]['code'])->toBe('S');
    expect($result['packages'][0]['count'])->toBe(1);
    expect($result['request_payload']['empaques'])->toBe([
        ['codigo' => 'S', 'nombre' => 'Pequeño', 'cantidad' => 1],
    ]);

    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), '/guides')) {
            return true;
        }

        $body = json_decode($request->body(), true);

        return isset($body['empaques'])
            && $body['empaques'][0]['codigo'] === 'S'
            && $body['empaques'][0]['cantidad'] === 1;
    });

    // Persistence round trip through the order json cast
    $order->update(['coordinadora_packages' => $result['packages']]);
    $order->refresh();
    expect($order->coordinadora_packages[0]['code'])->toBe('S');
});
