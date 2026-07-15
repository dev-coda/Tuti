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

function fvSoapResponse(string $salesOrderNumber = 'PV1547062', string $success = 'true', string $message = 'OK ~ PV1547062 ~ CONFIRMADO ~ No liberado'): string
{
    return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
        <s:Body>
            <CreateSalesOrderResponse xmlns="http://tempuri.org">
                <result xmlns:a="http://schemas.datacontract.org/2004/07/Dynamics.AX.Application" xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
                    <a:auditId>{6903BAC5-53B6-4126-A4F9-ED16BBAF967D}</a:auditId>
                    <a:documentStatus>CONFIRMADO</a:documentStatus>
                    <a:message>' . $message . '</a:message>
                    <a:releasedStatus>No liberado</a:releasedStatus>
                    <a:salesOrderNumber>' . $salesOrderNumber . '</a:salesOrderNumber>
                    <a:salesStatus>ABIERTO</a:salesStatus>
                    <a:success>' . $success . '</a:success>
                    <a:timestamp>2026-07-01T22:27:21Z</a:timestamp>
                    <a:warehouseWMS>false</a:warehouseWMS>
                </result>
            </CreateSalesOrderResponse>
        </s:Body>
    </s:Envelope>';
}

function makeCoordinadoraOrder(): Order
{
    [, , $product] = makeTaxBrandProduct();
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
        'email' => 'test-coordinadora-' . uniqid() . '@example.com',
        'password' => Hash::make('password123'),
        'document' => '901295332',
        'account_num' => '901295332',
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
        'services.fv.endpoint' => 'https://dynamics.test/soap/services/DYNPRODWSSalesForceGroup',
        'services.fv.default_warehouse' => 'MD15',
    ]);

    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft Token', 'value' => 'test-microsoft-token', 'show' => false]
    );

    return $order;
}

it('processes coordinadora fv plus guide workflow', function () {
    $order = makeCoordinadoraOrder();

    Http::fake([
        'https://coordinadora.test/oauth' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
        'https://coordinadora.test/guides' => Http::response([
            'guide_number' => '90012345678',
            'status_code' => 'CREATED',
            'status_text' => 'Guia creada',
        ], 200),
        'https://dynamics.test/*' => Http::response(fvSoapResponse(), 200),
    ]);

    app(CoordinadoraOrderProcessingService::class)->process($order);
    $order->refresh();

    expect($order->status_id)->toBe(Order::STATUS_PROCESSED);
    expect($order->fv_number)->toBe('PV1547062');
    expect($order->coordinadora_guide_number)->toBe('90012345678');
    expect($order->coordinadora_status_text)->toBe('Guia creada');

    $fvResponse = json_decode($order->fv_response_payload, true);
    expect($fvResponse['document_status'])->toBe('CONFIRMADO');
    expect($fvResponse['sales_order_number'])->toBe('PV1547062');

    Http::assertSent(function ($request) use ($order) {
        if (!str_contains($request->url(), 'dynamics.test')) {
            return true;
        }

        $body = $request->body();

        // Header must carry auth + SOAP action per docs/fv.pdf
        expect($request->header('SOAPAction')[0] ?? '')->toBe('http://tempuri.org/DWSSalesForce/CreateSalesOrder');
        expect($request->header('Authorization')[0] ?? '')->toBe('Bearer test-microsoft-token');

        expect($body)->toContain('<tem:CreateSalesOrder>');
        expect($body)->toContain('<dyn:custId>901295332</dyn:custId>');
        expect($body)->toContain('<dyn:origenventa>Tuti</dyn:origenventa>');
        expect($body)->toContain('<dyn:warehouse>MD15</dyn:warehouse>');
        expect($body)->toContain('<dyn:almacen>MD15</dyn:almacen>');
        expect($body)->toContain('<dyn:itemId>SKU-TEST-001</dyn:itemId>');
        expect($body)->toContain('<dyn:observationInternal>C001</dyn:observationInternal>');
        // External order number must be the third token of observationsCust
        preg_match('/<dyn:observationsCust>(.*?)<\/dyn:observationsCust>/', $body, $matches);
        $tokens = preg_split('/\s+/', trim($matches[1]));
        expect($tokens[2])->toBe((string) $order->id);
        // Shipping charge travels as FL0001 line
        expect($body)->toContain('<dyn:itemId>FL0001</dyn:itemId>');
        expect($body)->toContain('<dyn:unitPrice>5000.00</dyn:unitPrice>');

        return true;
    });
});

it('treats duplicate fv (YA_CREADO) as success', function () {
    $order = makeCoordinadoraOrder();

    Http::fake([
        'https://coordinadora.test/oauth' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
        'https://coordinadora.test/guides' => Http::response(['guide_number' => '90012345678'], 200),
        'https://dynamics.test/*' => Http::response(
            fvSoapResponse('PV1547000', 'false', 'YA_CREADO ~ PV1547000'),
            200
        ),
    ]);

    app(CoordinadoraOrderProcessingService::class)->process($order);
    $order->refresh();

    expect($order->status_id)->toBe(Order::STATUS_PROCESSED);
    expect($order->fv_number)->toBe('PV1547000');
});

it('throws when fv service rejects the order', function () {
    $order = makeCoordinadoraOrder();

    Http::fake([
        'https://dynamics.test/*' => Http::response(
            fvSoapResponse('', 'false', 'ERROR ~ Cliente no existe'),
            200
        ),
    ]);

    expect(fn () => app(CoordinadoraOrderProcessingService::class)->process($order))
        ->toThrow(RuntimeException::class);

    $order->refresh();
    expect($order->fv_number)->toBeNull();
    expect($order->status_id)->toBe(Order::STATUS_PENDING);
});
