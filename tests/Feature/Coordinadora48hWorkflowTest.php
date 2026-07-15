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
        'dane_code' => '11001000',
        'fulfillment_provider_48h' => 'coordinadora',
    ]);

    config([
        'services.coordinadora.oauth_url' => 'https://coordinadora.test/oauth/token',
        'services.coordinadora.base_url' => 'https://coordinadora.test',
        'services.coordinadora.key' => 'k',
        'services.coordinadora.secret' => 's',
        'services.coordinadora.id_proceso' => '11577',
        'services.coordinadora.nit' => '811025446',
        'services.coordinadora.origin_dane' => '05001000',
    ]);

    Http::fake([
        'https://coordinadora.test/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
        'https://coordinadora.test/cotizador/nacional' => Http::response([
            'isError' => false,
            'data' => [
                'flete_total' => 12900,
                'valor_envio' => 12900,
                'dias_entrega' => 1,
            ],
        ], 200),
    ]);

    session()->put('cart', []);

    $this->getJson('/api/shipping-quote/express?zone_id=' . $zone->id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'provider' => Order::SHIPPING_PROVIDER_COORDINADORA,
            'shipping_cost' => 12900.0,
        ]);

    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), '/cotizador/nacional')) {
            return $request->url() === 'https://coordinadora.test/oauth/token';
        }

        return $request['destino'] === '11001000'
            && $request['origen'] === '05001000'
            && $request['codigo_postal_origen'] === ''
            && $request['codigo_postal_destino'] === '';
    });
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
        'dane_code' => '11001000',
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
        'services.coordinadora.oauth_url' => 'https://coordinadora.test/oauth/token',
        'services.coordinadora.base_url' => 'https://coordinadora.test',
        'services.coordinadora.guides_path' => '/guias',
        'services.coordinadora.key' => 'k',
        'services.coordinadora.secret' => 's',
        'services.coordinadora.id_proceso' => '11577',
        'services.coordinadora.nit' => '811025446',
        'services.coordinadora.origin_dane' => '05001000',
        'services.coordinadora.origin_name' => 'Tronex',
        'services.coordinadora.origin_address' => 'Calle 10 # 45-20',
        'services.coordinadora.origin_phone' => '3001234567',
    ]);

    Http::fake([
        'https://coordinadora.test/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
        'https://coordinadora.test/guias' => Http::response([
            'data' => ['numero_guia' => '90012345678'],
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

    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), '/guias')) {
            return true;
        }

        return $request['datosDestinatario']['codigoCiudadDestinatario'] === '11001000'
            && $request['datosRemitente']['codigoCiudadRemitente'] === '05001000'
            && $request['codigoPais'] === 170;
    });
});

it('resolves zone dane codes from explicit values, legacy zip and user city', function () {
    // Explicit dane_code wins and is normalized from 5-digit divipola form.
    $explicit = Zone::create([
        'route' => 'R1', 'zone' => 'Z1', 'day' => '1', 'address' => 'Calle 1', 'code' => 'D001',
        'dane_code' => '11001',
    ]);
    expect($explicit->coordinadoraDaneCode())->toBe('11001000');

    // A DANE-looking value stored in the legacy zip_code field is honored.
    $legacyZip = Zone::create([
        'route' => 'R1', 'zone' => 'Z1', 'day' => '1', 'address' => 'Calle 2', 'code' => 'D002',
        'zip_code' => '05001000',
    ]);
    expect($legacyZip->coordinadoraDaneCode())->toBe('05001000');

    // A real 6-digit postal code is not mistaken for a DANE code.
    $postal = Zone::create([
        'route' => 'R1', 'zone' => 'Z1', 'day' => '1', 'address' => 'Calle 3', 'code' => 'D003',
        'zip_code' => '110111',
    ]);
    expect($postal->coordinadoraDaneCode())->toBeNull();

    // Falls back to the owning user's Dynamics city code.
    $user = User::create([
        'name' => 'Dane User',
        'email' => 'dane-user@example.com',
        'password' => Hash::make('password123'),
        'city_code' => '76001',
    ]);
    $fromUser = Zone::create([
        'route' => 'R1', 'zone' => 'Z1', 'day' => '1', 'address' => 'Calle 4', 'code' => 'D004',
        'user_id' => $user->id,
    ]);
    expect($fromUser->coordinadoraDaneCode())->toBe('76001000');
});

it('resolves dane codes from the city catalog by name', function () {
    expect(\App\Services\Shipping\DaneCodeService::forCity('Medellín', 'Antioquia'))->toBe('05001000');
    expect(\App\Services\Shipping\DaneCodeService::forCity('medellin'))->toBe('05001000');
    expect(\App\Services\Shipping\DaneCodeService::forCity('Cali'))->toBe('76001000');
    expect(\App\Services\Shipping\DaneCodeService::forCity('Ciudad Inexistente'))->toBeNull();
    // Municipality codes whose trailing zeros were trimmed by the spreadsheet ("5.03").
    expect(\App\Services\Shipping\DaneCodeService::forCity('Amagá', 'Antioquia'))->toBe('05030000');
});

it('fails the express quote when the zone has no dane destination', function () {
    Setting::updateOrCreate(
        ['key' => 'express_48h_enabled'],
        ['name' => 'Express 48h', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_express_48h_enabled');

    $zone = Zone::create([
        'route' => 'R1', 'zone' => 'Z1', 'day' => '1', 'address' => 'Calle 1', 'code' => 'N001',
        'zip_code' => '110111', // postal code only; no DANE resolvable
        'fulfillment_provider_48h' => 'coordinadora',
    ]);

    config([
        'services.coordinadora.oauth_url' => 'https://coordinadora.test/oauth/token',
        'services.coordinadora.base_url' => 'https://coordinadora.test',
        'services.coordinadora.key' => 'k',
        'services.coordinadora.secret' => 's',
        'services.coordinadora.origin_dane' => '05001000',
    ]);

    Http::fake();

    session()->put('cart', []);

    $this->getJson('/api/shipping-quote/express?zone_id=' . $zone->id)
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    Http::assertNothingSent();
});
