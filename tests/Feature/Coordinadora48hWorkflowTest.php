<?php

use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Repositories\OrderRepository;
use App\Services\Shipping\CoordinadoraOrderProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function makeTaxBrandProduct(): array
{
    $tax = Tax::create(['name' => 'IVA 0', 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Vendor Test',
        'slug' => 'vendor-test',
        'vendor_type' => 'V',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Brand Test',
        'slug' => 'brand-test',
        'vendor_id' => $vendor->id,
    ]);

    $product = Product::create([
        'name' => 'Producto Test',
        'slug' => 'producto-test',
        'description' => '',
        'short_description' => '',
        'sku' => 'SKU-TEST-001',
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

    return [$tax, $brand, $product];
}

it('quotes express shipping for coordinadora zones', function () {
    Setting::updateOrCreate(
        ['key' => 'express_48h_enabled'],
        ['name' => 'Express 48h', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_express_48h_enabled');

    $zone = Zone::create([
        'route' => 'R1',
        'zone' => 'Z1',
        'day' => '1',
        'address' => 'Calle 1',
        'code' => 'C001',
        'zip_code' => '110111',
        'fulfillment_provider_48h' => 'coordinadora',
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
        'https://coordinadora.test/quote' => Http::response(['shipping_cost' => 12900], 200),
    ]);

    session()->put('cart', []);

    $this->getJson('/api/shipping-quote/express?zone_id=' . $zone->id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'provider' => Order::SHIPPING_PROVIDER_COORDINADORA,
            'shipping_cost' => 12900.0,
        ]);
});

it('appends fl0001 line in diagnostic xml when shipping exists', function () {
    [, $brand, $product] = makeTaxBrandProduct();
    $zone = Zone::create([
        'route' => 'R1',
        'zone' => 'Z1',
        'day' => '1',
        'address' => 'Calle 1',
        'code' => 'C001',
    ]);
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test-coordinadora@example.com',
        'password' => Hash::make('password123'),
    ]);

    $order = new Order([
        'id' => 0,
        'user_id' => $user->id,
        'zone_id' => $zone->id,
        'delivery_date' => now()->addDay()->format('Y-m-d'),
        'observations' => 'test',
        'shipping_quote_amount' => 1234,
        'created_at' => now(),
    ]);
    $order->setRelation('zone', $zone);
    $order->setRelation('user', $user);
    $order->setRelation('bonifications', collect());

    $orderProduct = new OrderProduct([
        'order_id' => 0,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 10000,
        'discount' => 0,
        'percentage' => 0,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
        'package_quantity' => 1,
    ]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, collect([$orderProduct]));

    expect($xml)->toContain('<dyn:itemId>FL0001</dyn:itemId>');
    expect($xml)->toContain('<dyn:unitPrice>1234.00</dyn:unitPrice>');
});

it('processes coordinadora fv plus guide workflow', function () {
    [, $brand, $product] = makeTaxBrandProduct();
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
        'name' => 'Test User 2',
        'email' => 'test-coordinadora-2@example.com',
        'password' => Hash::make('password123'),
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'total' => 10000,
        'discount' => 0,
        'status_id' => Order::STATUS_PENDING,
        'zone_id' => $zone->id,
        'delivery_date' => now()->addDay()->format('Y-m-d'),
        'delivery_method' => Order::DELIVERY_METHOD_EXPRESS,
        'shipping_provider' => Order::SHIPPING_PROVIDER_COORDINADORA,
        'shipping_quote_amount' => 5000,
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 10000,
        'discount' => 0,
        'percentage' => 0,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
        'package_quantity' => 1,
    ]);

    config([
        'app.url' => '',
        'services.coordinadora.oauth_url' => 'https://coordinadora.test/oauth',
        'services.coordinadora.base_url' => 'https://coordinadora.test',
        'services.coordinadora.key' => 'k',
        'services.coordinadora.secret' => 's',
        'services.coordinadora.id_proceso' => '11577',
    ]);

    Http::fake([
        'https://coordinadora.test/oauth' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
        'https://coordinadora.test/guides' => Http::response([
            'guide_number' => '90012345678',
            'status_code' => 'CREATED',
            'status_text' => 'Guia creada',
        ], 200),
    ]);

    app(CoordinadoraOrderProcessingService::class)->process($order);
    $order->refresh();

    expect($order->status_id)->toBe(Order::STATUS_PROCESSED);
    expect($order->fv_number)->toBe('FV-MOCK-' . $order->id);
    expect($order->coordinadora_guide_number)->toBe('90012345678');
    expect($order->coordinadora_status_text)->toBe('Guia creada');
});
