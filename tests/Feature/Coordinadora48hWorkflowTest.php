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
use Illuminate\Http\Client\RequestException;
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
    Setting::updateOrCreate(
        ['key' => 'express_free_shipping_min'],
        ['name' => 'Envío 48h gratis desde', 'value' => '0', 'show' => false]
    );
    Cache::forget('setting_express_free_shipping_min');

    $zone = Zone::create([
        'route' => 'R1',
        'zone' => 'Z1',
        'day' => '1',
        'address' => 'Calle 1',
        'code' => 'C001',
        'dane_code' => '11001000',
        'fulfillment_provider_48h' => 'coordinadora',
    ]);

    [, , $product] = makeTaxBrandProduct();

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

    session()->put('cart', [
        ['product_id' => $product->id, 'quantity' => 1, 'price' => 10000],
    ]);

    $this->getJson('/api/shipping-quote/express?zone_id=' . $zone->id)
        ->assertOk()
        ->assertJson([
            'success' => true,
            'provider' => Order::SHIPPING_PROVIDER_COORDINADORA,
            'shipping_cost' => 12900.0,
            'free_shipping_applied' => false,
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

it('applies free express shipping when merchandise meets the configured minimum', function () {
    Setting::updateOrCreate(
        ['key' => 'express_48h_enabled'],
        ['name' => 'Express 48h', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_express_48h_enabled');
    Setting::updateOrCreate(
        ['key' => 'express_free_shipping_min'],
        ['name' => 'Envío 48h gratis desde', 'value' => '100000', 'show' => false]
    );
    Cache::forget('setting_express_free_shipping_min');
    Setting::updateOrCreate(
        ['key' => 'express_free_shipping_enabled'],
        ['name' => 'Envío especial gratuito por compra mínima', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_express_free_shipping_enabled');

    $zone = Zone::create([
        'route' => 'R1',
        'zone' => 'Z1',
        'day' => '1',
        'address' => 'Calle 1',
        'code' => 'C001',
        'dane_code' => '11001000',
        'fulfillment_provider_48h' => 'coordinadora',
    ]);

    [, , $product] = makeTaxBrandProduct();

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

    session()->put('cart', [
        ['product_id' => $product->id, 'quantity' => 1, 'price' => 10000],
    ]);

    $this->getJson('/api/shipping-quote/express?zone_id=' . $zone->id . '&merchandise_total=150000')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'provider' => Order::SHIPPING_PROVIDER_COORDINADORA,
            'shipping_cost' => 0,
            'quoted_shipping_cost' => 12900.0,
            'free_shipping_applied' => true,
            'free_shipping_min' => 100000.0,
        ]);

    // Below the threshold still charges shipping.
    $this->getJson('/api/shipping-quote/express?zone_id=' . $zone->id . '&merchandise_total=50000')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'shipping_cost' => 12900.0,
            'free_shipping_applied' => false,
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
        'dane_code' => '11001000',
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
        'microsoft.resource' => 'https://dynamics.test/',
        'microsoft.url_token' => 'https://login.microsoftonline.com/token',
        'microsoft.client_id' => 'client-id',
        'microsoft.client_secret' => 'client-secret',
        'services.coordinadora.oauth_url' => 'https://coordinadora.test/oauth/token',
        'services.coordinadora.base_url' => 'https://coordinadora.test',
        'services.coordinadora.guides_path' => '/guias',
        'services.coordinadora.key' => 'k',
        'services.coordinadora.secret' => 's',
        'services.coordinadora.id_proceso' => '11577',
        'services.fv.endpoint' => 'https://dynamics.test/soap/services/DYNPRODWSSalesForceGroup',
        'services.fv.default_warehouse' => 'MD15',
        'services.coordinadora.nit' => '811025446',
        'services.coordinadora.origin_dane' => '05001000',
        'services.coordinadora.origin_name' => 'Tronex',
        'services.coordinadora.origin_address' => 'Calle 10 # 45-20',
        'services.coordinadora.origin_phone' => '3001234567',
    ]);

    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft Token', 'value' => 'test-microsoft-token', 'show' => false]
    );

    return $order;
}

it('processes coordinadora fv workflow without creating a guide by default', function () {
    $order = makeCoordinadoraOrder();

    Http::fake([
        'https://coordinadora.test/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
        'https://coordinadora.test/guias' => Http::response([
            'data' => ['numero_guia' => '90012345678'],
            'status_code' => 'CREATED',
            'status_text' => 'Guia creada',
        ], 200),
        'https://dynamics.test/*' => Http::response(fvSoapResponse(), 200),
    ]);

    app(CoordinadoraOrderProcessingService::class)->process($order);
    $order->refresh();

    expect($order->status_id)->toBe(Order::STATUS_PROCESSED);
    expect($order->fv_number)->toBe('PV1547062');
    expect($order->coordinadora_guide_number)->toBeNull();
    expect($order->coordinadora_status_code)->toBe('FV_ONLY');

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
        expect($body)->toContain('<dyn:resource></dyn:resource>');
        expect($body)->toContain('<dyn:drive></dyn:drive>');
        // External order number must be the third token of observationsCust
        preg_match('/<dyn:observationsCust>(.*?)<\/dyn:observationsCust>/', $body, $matches);
        $tokens = preg_split('/\s+/', trim($matches[1]));
        expect($tokens[2])->toBe((string) $order->id);
        // Shipping charge travels as FL0001 line
        expect($body)->toContain('<dyn:itemId>FL0001</dyn:itemId>');
        expect($body)->toContain('<dyn:unitPrice>5000.00</dyn:unitPrice>');

        return true;
    });

    // Activity owns guides; default path must not call Coordinadora /guias.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/guias'));
});

it('creates a coordinadora guide when create_guides is enabled', function () {
    config(['services.coordinadora.create_guides' => true]);
    $order = makeCoordinadoraOrder();

    Http::fake([
        'https://coordinadora.test/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
        'https://coordinadora.test/guias' => Http::response([
            'data' => ['numero_guia' => '90012345678'],
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

    Http::assertSent(function ($request) {
        if (!str_contains($request->url(), '/guias')) {
            return true;
        }

        return $request['datosDestinatario']['codigoCiudadDestinatario'] === '11001000'
            && $request['datosRemitente']['codigoCiudadRemitente'] === '05001000'
            && $request['codigoPais'] === 170;
    });
});

it('treats duplicate fv (YA_CREADO) as success', function () {
    $order = makeCoordinadoraOrder();

    Http::fake([
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

it('keeps the fv number when guide creation fails so a retry can resume', function () {
    config(['services.coordinadora.create_guides' => true]);
    $order = makeCoordinadoraOrder();

    Http::fake([
        'https://coordinadora.test/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600], 200),
        'https://coordinadora.test/guias' => Http::response(['message' => 'direccionRemitente requerido'], 422),
        'https://dynamics.test/*' => Http::response(fvSoapResponse(), 200),
    ]);

    expect(fn () => app(CoordinadoraOrderProcessingService::class)->process($order))
        ->toThrow(RequestException::class);

    // The FV exists in Dynamics, so it must exist locally too; otherwise the
    // operator has no record of the document that was created.
    $order->refresh();
    expect($order->fv_number)->toBe('PV1547062');
    expect($order->coordinadora_guide_number)->toBeNull();
    expect($order->status_id)->not->toBe(Order::STATUS_PROCESSED);
});

it('skips CreateSalesOrder on retry when the order already has an fv number', function () {
    $order = makeCoordinadoraOrder();
    $order->update(['fv_number' => 'PV1547062']);

    Http::fake([
        'https://dynamics.test/*' => Http::response(fvSoapResponse(), 200),
    ]);

    app(CoordinadoraOrderProcessingService::class)->process($order);
    $order->refresh();

    expect($order->status_id)->toBe(Order::STATUS_PROCESSED);
    expect($order->fv_number)->toBe('PV1547062');
    expect($order->coordinadora_guide_number)->toBeNull();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'dynamics.test'));
});

it('routes a manual retry of an express order to fv instead of tronex presales', function () {
    $order = makeCoordinadoraOrder();

    expect($order->usesFvFulfillment())->toBeTrue();

    // The retry refreshes the Microsoft token before transmitting.
    config([
        'microsoft.url_token' => 'https://login.test/oauth2/token',
        'microsoft.client_id' => 'client-id',
        'microsoft.client_secret' => 'client-secret',
        'microsoft.resource' => 'https://dynamics.test/',
    ]);

    Http::fake([
        'https://login.test/oauth2/token' => Http::response(['access_token' => 'test-microsoft-token'], 200),
        'https://dynamics.test/*' => Http::response(fvSoapResponse(), 200),
    ]);

    $result = OrderRepository::retryXmlTransmission($order);
    $order->refresh();

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toContain('PV1547062');
    expect($order->fv_number)->toBe('PV1547062');
    expect($order->status_id)->toBe(Order::STATUS_PROCESSED);

    // The legacy presales webservice must not be touched for express orders.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'Presales')
        || str_contains($request->url(), 'presales'));
});

it('does not treat a tronex order as fv fulfilled', function () {
    $order = makeCoordinadoraOrder();
    $order->update(['shipping_provider' => Order::SHIPPING_PROVIDER_TRONEX]);

    expect($order->usesFvFulfillment())->toBeFalse();

    $order->update([
        'shipping_provider' => Order::SHIPPING_PROVIDER_COORDINADORA,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
    ]);

    expect($order->usesFvFulfillment())->toBeFalse();
});

it('offers manual transmission for orders that were never attempted', function () {
    $order = makeCoordinadoraOrder();

    // Never-attempted orders sit in PENDING, which previously hid the retry action.
    expect($order->status_id)->toBe(Order::STATUS_PENDING);
    expect($order->awaitingTransmission())->toBeTrue();

    $order->update(['status_id' => Order::STATUS_PROCESSED]);
    expect($order->awaitingTransmission())->toBeFalse();
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
