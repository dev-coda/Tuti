<?php

use App\Models\Bonification;
use App\Models\Brand;
use App\Models\City;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderProductBonification;
use App\Models\Product;
use App\Models\Setting;
use App\Models\State;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Models\ZoneWarehouse;
use App\Services\Shipping\FvDynamicsService;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Locks the Dynamics CreateSalesOrder data contract: namespaces, element order,
 * order-data population, and "." defaults. Fixture values are not the sample order.
 */

function fvSoapConfig(array $overrides = []): void
{
    config(array_merge([
        'microsoft.order_consecutive_prefix' => 'TEST',
        'services.fv.company' => 'TRX',
        'services.fv.approval' => 'YES',
        'services.fv.business_unit' => '01',
        'services.fv.cost_center' => '1',
        'services.fv.delivery_mode' => 'TERRESTRE',
        'services.fv.doc_type' => 'Factura',
        'services.fv.drive' => '9000',
        'services.fv.location_invoice' => 'MEDELLIN',
        'services.fv.num_sequence_group' => 'MEDELLIN',
        'services.fv.order_type' => 'PDVTA',
        'services.fv.origen_venta' => 'Tuti',
        'services.fv.transportadora' => 'COORDINADORA',
        'services.fv.resource' => '9999',
        'services.fv.supervisor' => '9999',
        'services.fv.vendor' => '9999',
        'services.fv.sales_responsible' => '9999',
        'services.fv.shipping_item_id' => 'FL00001',
        'services.fv.default_warehouse' => 'SHOULD-NOT-USE',
    ], $overrides));
}

function fvSalesOrderChildNames(): array
{
    return [
        'LinesDetails',
        'TRO_E_obsequio',
        'approval',
        'businessUnit',
        'costCenter',
        'custId',
        'dateInvoice',
        'deliveryMode',
        'detail',
        'docType',
        'drive',
        'locationInvoice',
        'name',
        'numInvoice',
        'numSequenceGroup',
        'observationInternal',
        'observationsCust',
        'orderType',
        'origenventa',
        'phone',
        'resource',
        'salesResponsible',
        'shapeDispatch',
        'supervisor',
        'third',
        'transportadora',
        'vendor',
        'warehouse',
    ];
}

function fvLineChildNames(): array
{
    return [
        'almacen',
        'discountPercent',
        'itemId',
        'lote',
        'qty',
        'serial',
        'taxCode',
        'taxGroup',
        'taxValue',
        'unitPrice',
    ];
}

function fvDirectChildren(DOMElement $element): array
{
    $children = [];
    foreach ($element->childNodes as $child) {
        if ($child instanceof DOMElement) {
            $children[] = $child;
        }
    }

    return $children;
}

function fvChild(DOMElement $parent, string $namespace, string $localName): DOMElement
{
    foreach (fvDirectChildren($parent) as $child) {
        if ($child->localName === $localName && $child->namespaceURI === $namespace) {
            return $child;
        }
    }

    throw new RuntimeException("Missing {$localName} in {$parent->localName}");
}

function fvChildText(DOMElement $parent, string $localName): string
{
    foreach (fvDirectChildren($parent) as $child) {
        if ($child->localName === $localName) {
            return $child->textContent;
        }
    }

    throw new RuntimeException("Missing {$localName} in {$parent->localName}");
}

/**
 * @return array{salesOrder: DOMElement, lines: array<int, DOMElement>}
 */
function fvAssertSalesOrderContract(string $xml): array
{
    $soap = 'http://schemas.xmlsoap.org/soap/envelope/';
    $dat = 'http://schemas.microsoft.com/dynamics/2013/01/datacontracts';
    $tem = 'http://tempuri.org';
    $dyn = 'http://schemas.datacontract.org/2004/07/Dynamics.AX.Application';

    $doc = new DOMDocument();
    expect($doc->loadXML($xml))->toBeTrue();

    $envelope = $doc->documentElement;
    expect($envelope->localName)->toBe('Envelope');
    expect($envelope->namespaceURI)->toBe($soap);
    expect($envelope->lookupNamespaceURI('soapenv'))->toBe($soap);
    expect($envelope->lookupNamespaceURI('dat'))->toBe($dat);
    expect($envelope->lookupNamespaceURI('tem'))->toBe($tem);
    expect($envelope->lookupNamespaceURI('dyn'))->toBe($dyn);

    $header = fvChild($envelope, $soap, 'Header');
    $body = fvChild($envelope, $soap, 'Body');
    $callContext = fvChild($header, $dat, 'CallContext');

    expect(array_map(fn (DOMElement $el) => $el->localName, fvDirectChildren($callContext)))->toBe([
        'Company',
        'Language',
        'MessageId',
        'PartitionKey',
    ]);
    expect(fvChildText($callContext, 'Company'))->toBe('TRX');
    expect(fvChildText($callContext, 'Language'))->toBe('');
    expect(fvChildText($callContext, 'MessageId'))->toBe('');
    expect(fvChildText($callContext, 'PartitionKey'))->toBe('');

    $create = fvChild($body, $tem, 'CreateSalesOrder');
    $request = fvChild($create, $tem, '_request');
    $salesOrder = fvChild($request, $dyn, 'salesOrder');

    $salesOrderChildren = fvDirectChildren($salesOrder);
    expect(array_map(fn (DOMElement $el) => $el->localName, $salesOrderChildren))->toBe(fvSalesOrderChildNames());

    $lines = fvDirectChildren($salesOrderChildren[0]);
    expect($lines)->not->toBeEmpty();
    foreach ($lines as $line) {
        expect($line->localName)->toBe('LinesDetails');
        expect(array_map(fn (DOMElement $el) => $el->localName, fvDirectChildren($line)))->toBe(fvLineChildNames());
        expect(fvChildText($line, 'lote'))->toBe('.');
        expect(fvChildText($line, 'serial'))->toBe('.');
        expect(fvChildText($line, 'taxCode'))->toBe('.');
        expect(fvChildText($line, 'taxGroup'))->toBe('.');
    }

    return ['salesOrder' => $salesOrder, 'lines' => $lines];
}

function fvBuildSoapXml(Order $order): string
{
    $method = new ReflectionMethod(FvDynamicsService::class, 'buildRequestXml');

    return $method->invoke(app(FvDynamicsService::class), $order);
}

function fvMakeSoapOrder(array $overrides = []): array
{
    $in = array_merge([
        'name' => 'Cliente Norte',
        'mobile_phone' => '3005550101',
        'phone' => null,
        'zone_code' => 'RUTERO-77',
        'zone_number' => '14',
        'bodega' => 'BOD-14',
        'shipping_provider' => Order::SHIPPING_PROVIDER_COORDINADORA,
        'seller_document' => 'VEND-19',
        'shipping_amount' => 1850.5,
        'account_num' => '830111222',
        'document' => '830111222',
        'qty' => 10,
        'price' => 5000,
        'percentage' => 5,
        'tax_rate' => 19,
        'created_at' => '2026-04-18 12:00:00',
    ], $overrides);

    $suffix = uniqid();
    $in['zone_number'] .= $suffix;
    $in['bodega'] .= $suffix;
    $tax = Tax::create(['name' => 'IVA ' . $in['tax_rate'] . ' ' . $suffix, 'tax' => $in['tax_rate']]);
    $vendor = Vendor::create([
        'name' => 'Vendor ' . $suffix,
        'slug' => 'vendor-' . $suffix,
        'vendor_type' => 'V',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Brand ' . $suffix,
        'slug' => 'brand-' . $suffix,
        'vendor_id' => $vendor->id,
    ]);
    $sku = 'ITEM-FV-' . $suffix;
    $product = Product::create([
        'name' => 'Producto ' . $suffix,
        'slug' => 'producto-' . $suffix,
        'description' => '',
        'short_description' => '',
        'sku' => $sku,
        'active' => 1,
        'price' => $in['price'],
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
    ]);

    $zone = Zone::create([
        'route' => 'R1',
        'zone' => $in['zone_number'],
        'day' => '1',
        'address' => 'Calle 1',
        'code' => $in['zone_code'] !== '' ? $in['zone_code'] : null,
    ]);
    ZoneWarehouse::create([
        'zone_code' => $in['zone_number'],
        'bodega_code' => $in['bodega'],
    ]);

    $user = User::create([
        'name' => $in['name'] !== '' ? $in['name'] : 'pending',
        'email' => 'fv-soap-' . $suffix . '@example.com',
        'password' => Hash::make('password123'),
        'document' => $in['document'],
        'account_num' => $in['account_num'],
        'phone' => $in['phone'],
        'mobile_phone' => $in['mobile_phone'],
    ]);
    if ($in['name'] === '') {
        $user->name = '';
        $user->save();
    }

    $sellerId = null;
    if ($in['seller_document'] !== null) {
        $seller = User::create([
            'name' => 'Vendedor ' . $suffix,
            'email' => 'fv-seller-' . $suffix . '@example.com',
            'password' => Hash::make('password123'),
            'document' => $in['seller_document'],
        ]);
        $sellerId = $seller->id;
    }

    $order = Order::create([
        'user_id' => $user->id,
        'seller_id' => $sellerId,
        'total' => $in['price'] * $in['qty'],
        'discount' => 0,
        'status_id' => Order::STATUS_PENDING,
        'zone_id' => $zone->id,
        'zone_snapshot' => [
            'code' => $in['zone_code'],
            'zone' => $in['zone_number'],
        ],
        'delivery_date' => '2026-04-20',
        'delivery_method' => Order::DELIVERY_METHOD_EXPRESS,
        'shipping_provider' => $in['shipping_provider'],
        'shipping_quote_amount' => $in['shipping_amount'],
        'observations' => 'nota de pedido',
    ]);
    $order->created_at = $in['created_at'];
    $order->save();

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => $in['qty'],
        'price' => $in['price'],
        'discount' => 0,
        'percentage' => $in['percentage'],
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
        'package_quantity' => 1,
    ]);

    $in['order'] = $order->fresh();
    $in['sku'] = $sku;

    return $in;
}

it('builds CreateSalesOrder xml in contract order with order data', function () {
    fvSoapConfig();
    $fx = fvMakeSoapOrder();
    $order = $fx['order'];
    $xml = fvBuildSoapXml($order);
    $parsed = fvAssertSalesOrderContract($xml);
    $salesOrder = $parsed['salesOrder'];
    $lines = $parsed['lines'];

    $consecutive = 'TEST' . $order->id;
    expect($order->soapConsecutive())->toBe($consecutive);

    expect(fvChildText($salesOrder, 'TRO_E_obsequio'))->toBe('0');
    expect(fvChildText($salesOrder, 'approval'))->toBe('YES');
    expect(fvChildText($salesOrder, 'businessUnit'))->toBe('01');
    expect(fvChildText($salesOrder, 'costCenter'))->toBe('1');
    expect(fvChildText($salesOrder, 'custId'))->toBe('830111222');
    expect(fvChildText($salesOrder, 'third'))->toBe('830111222');
    expect(fvChildText($salesOrder, 'dateInvoice'))->toBe('2026-04-18');
    expect(fvChildText($salesOrder, 'deliveryMode'))->toBe('TERRESTRE');
    expect(fvChildText($salesOrder, 'detail'))->toBe($consecutive);
    expect(fvChildText($salesOrder, 'docType'))->toBe('Factura');
    expect(fvChildText($salesOrder, 'drive'))->toBe('9000');
    expect(fvChildText($salesOrder, 'locationInvoice'))->toBe('MEDELLIN');
    expect(fvChildText($salesOrder, 'name'))->toBe('Cliente Norte');
    expect(fvChildText($salesOrder, 'numInvoice'))->toBe('NEXT');
    expect(fvChildText($salesOrder, 'numSequenceGroup'))->toBe('MEDELLIN');
    expect(fvChildText($salesOrder, 'observationInternal'))->toBe('RUTERO-77');
    expect(fvChildText($salesOrder, 'orderType'))->toBe('PDVTA');
    expect(fvChildText($salesOrder, 'origenventa'))->toBe('Tuti');
    expect(fvChildText($salesOrder, 'phone'))->toBe('3005550101');
    expect(fvChildText($salesOrder, 'resource'))->toBe('9999');
    expect(fvChildText($salesOrder, 'salesResponsible'))->toBe('VEND-19');
    expect(fvChildText($salesOrder, 'shapeDispatch'))->toBe('');
    expect(fvChildText($salesOrder, 'supervisor'))->toBe('9999');
    expect(fvChildText($salesOrder, 'transportadora'))->toBe('COORDINADORA');
    expect(fvChildText($salesOrder, 'vendor'))->toBe('9999');
    expect(fvChildText($salesOrder, 'warehouse'))->toBe($fx['bodega']);

    $observations = fvChildText($salesOrder, 'observationsCust');
    $tokens = preg_split('/\s+/', trim($observations));
    expect($tokens[2])->toBe($consecutive);
    expect($observations)->toContain('2026/04/18');

    expect($lines)->toHaveCount(2);

    $productLine = $lines[0];
    $expectedTax = parseCurrency(round(10 * 5000 * (1 - 5 / 100) * 19 / 100, 2));
    expect(fvChildText($productLine, 'almacen'))->toBe($fx['bodega']);
    expect(fvChildText($productLine, 'discountPercent'))->toBe('5');
    expect(fvChildText($productLine, 'itemId'))->toBe($fx['sku']);
    expect(fvChildText($productLine, 'qty'))->toBe('10');
    expect(fvChildText($productLine, 'taxValue'))->toBe($expectedTax);
    expect(fvChildText($productLine, 'unitPrice'))->toBe('5000.00');

    $freightLine = $lines[1];
    expect(fvChildText($freightLine, 'almacen'))->toBe($fx['bodega']);
    expect(fvChildText($freightLine, 'discountPercent'))->toBe('0');
    expect(fvChildText($freightLine, 'itemId'))->toBe('FL00001');
    expect(fvChildText($freightLine, 'qty'))->toBe('1');
    expect(fvChildText($freightLine, 'taxValue'))->toBe('0');
    expect(fvChildText($freightLine, 'unitPrice'))->toBe('1850.50');
});

it('uses dot defaults and the customer document when contact and account fields are empty', function () {
    fvSoapConfig();
    $fx = fvMakeSoapOrder([
        'name' => '',
        'mobile_phone' => null,
        'phone' => null,
        'zone_code' => '',
        'account_num' => null,
        'document' => '901000333',
        'shipping_provider' => Order::SHIPPING_PROVIDER_TRONEX,
        'seller_document' => null,
    ]);

    $parsed = fvAssertSalesOrderContract(fvBuildSoapXml($fx['order']));
    $salesOrder = $parsed['salesOrder'];

    expect(fvChildText($salesOrder, 'name'))->toBe('.');
    expect(fvChildText($salesOrder, 'phone'))->toBe('.');
    expect(fvChildText($salesOrder, 'observationInternal'))->toBe('.');
    expect(fvChildText($salesOrder, 'custId'))->toBe('901000333');
    expect(fvChildText($salesOrder, 'third'))->toBe('901000333');
    expect(fvChildText($salesOrder, 'transportadora'))->toBe('');
    expect(fvChildText($salesOrder, 'salesResponsible'))->toBe('9999');
    expect(fvChildText($salesOrder, 'resource'))->toBe('9999');
    expect(fvChildText($salesOrder, 'supervisor'))->toBe('9999');
    expect(fvChildText($salesOrder, 'vendor'))->toBe('9999');
    expect(fvChildText($salesOrder, 'shapeDispatch'))->toBe('');
    expect(fvChildText($salesOrder, 'numInvoice'))->toBe('NEXT');
    expect(fvChildText($parsed['lines'][1], 'itemId'))->toBe('FL00001');
});

it('keeps numInvoice NEXT when the stage consecutive prefix is applied or cleared', function () {
    fvSoapConfig(['microsoft.order_consecutive_prefix' => 'TEST']);
    $prefixed = fvMakeSoapOrder();
    $prefixedXml = fvAssertSalesOrderContract(fvBuildSoapXml($prefixed['order']));
    $consecutive = 'TEST' . $prefixed['order']->id;

    expect(fvChildText($prefixedXml['salesOrder'], 'detail'))->toBe($consecutive);
    expect(fvChildText($prefixedXml['salesOrder'], 'numInvoice'))->toBe('NEXT');
    $tokens = preg_split('/\s+/', trim(fvChildText($prefixedXml['salesOrder'], 'observationsCust')));
    expect($tokens[2])->toBe($consecutive);

    fvSoapConfig(['microsoft.order_consecutive_prefix' => '']);
    $plain = fvMakeSoapOrder();
    $plainXml = fvAssertSalesOrderContract(fvBuildSoapXml($plain['order']));

    expect(fvChildText($plainXml['salesOrder'], 'detail'))->toBe((string) $plain['order']->id);
    expect(fvChildText($plainXml['salesOrder'], 'numInvoice'))->toBe('NEXT');
    expect($plain['order']->soapConsecutive())->toBe((string) $plain['order']->id);
});

function fvForgetBonificacionSetting(): void
{
    Cache::forget('setting_'.Setting::FV_BONIFICACIONES_CITIES_KEY);
}

function fvEnableBonificaciones(array $enabledKeys): void
{
    $stored = [];
    foreach (array_keys((array) config('zone_warehouses.cities', [])) as $key) {
        $stored[$key] = in_array($key, $enabledKeys, true) ? '1' : '0';
    }

    Setting::updateOrCreate(
        ['key' => Setting::FV_BONIFICACIONES_CITIES_KEY],
        ['name' => 'Bonificaciones en FV por ciudad', 'value' => json_encode($stored), 'show' => false]
    );
    fvForgetBonificacionSetting();
}

function fvAssignCity(Order $order, string $cityName): void
{
    $state = State::create(['name' => 'Departamento '.uniqid()]);
    $city = City::create([
        'name' => $cityName,
        'state_id' => $state->id,
        'active' => true,
        'is_preferred' => true,
    ]);
    $order->user->city_id = $city->id;
    $order->user->city_code = null;
    $order->user->save();
}

function fvAttachGift(Order $order, int $firstQty = 2, int $secondQty = 3): string
{
    $suffix = uniqid();
    $paid = $order->products()->first();
    $paidProduct = Product::findOrFail($paid->product_id);
    $gift = Product::create([
        'name' => 'Obsequio '.$suffix,
        'slug' => 'obsequio-'.$suffix,
        'description' => '',
        'short_description' => '',
        'sku' => 'GIFT-FV-'.$suffix,
        'active' => 1,
        'price' => 8000,
        'delivery_days' => 1,
        'discount' => 0,
        'discount_type' => 'percentage',
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $paidProduct->tax_id,
        'brand_id' => $paidProduct->brand_id,
        'package_quantity' => 6,
        'calculate_package_price' => true,
    ]);
    $bonification = Bonification::create([
        'name' => 'Bonif '.$suffix,
        'buy' => 2,
        'get' => 1,
        'product_id' => $gift->id,
    ]);

    foreach ([$firstQty, $secondQty] as $qty) {
        OrderProductBonification::create([
            'order_product_id' => $paid->id,
            'bonification_id' => $bonification->id,
            'product_id' => $gift->id,
            'quantity' => $qty,
            'order_id' => $order->id,
        ]);
    }

    return $gift->sku;
}

it('includes consolidated bonificacion lines when the order city is enabled', function () {
    fvSoapConfig();
    fvForgetBonificacionSetting();
    $fx = fvMakeSoapOrder();
    fvAssignCity($fx['order'], 'Bogotá');
    fvEnableBonificaciones(['bogota']);
    $giftSku = fvAttachGift($fx['order']);

    $parsed = fvAssertSalesOrderContract(fvBuildSoapXml($fx['order']->fresh()));
    $salesOrder = $parsed['salesOrder'];
    $lines = $parsed['lines'];

    expect(array_map(fn (DOMElement $el) => $el->localName, fvDirectChildren($salesOrder)))->toBe(fvSalesOrderChildNames());
    expect(fvChildText($salesOrder, 'TRO_E_obsequio'))->toBe('0');
    expect($lines)->toHaveCount(3);

    expect(fvChildText($lines[0], 'itemId'))->toBe($fx['sku']);

    $giftLine = $lines[1];
    expect(array_map(fn (DOMElement $el) => $el->localName, fvDirectChildren($giftLine)))->toBe(fvLineChildNames());
    expect(fvChildText($giftLine, 'itemId'))->toBe($giftSku);
    expect(fvChildText($giftLine, 'qty'))->toBe('5');
    expect(fvChildText($giftLine, 'discountPercent'))->toBe('0');
    expect(fvChildText($giftLine, 'unitPrice'))->toBe('0.00');
    expect(fvChildText($giftLine, 'taxValue'))->toBe('0.00');
    expect(fvChildText($giftLine, 'almacen'))->toBe($fx['bodega']);
    expect(fvChildText($giftLine, 'lote'))->toBe('.');

    expect(fvChildText($lines[2], 'itemId'))->toBe('FL00001');
});

it('includes bonificaciones from the zone warehouse when the client has no city', function () {
    fvSoapConfig();
    fvForgetBonificacionSetting();
    $fx = fvMakeSoapOrder();
    ZoneWarehouse::where('zone_code', $fx['zone_number'])->update(['bodega_code' => 'BG05']);
    fvEnableBonificaciones(['bogota']);
    $giftSku = fvAttachGift($fx['order']);

    $parsed = fvAssertSalesOrderContract(fvBuildSoapXml($fx['order']->fresh()));

    expect(fvChildText($parsed['lines'][1], 'itemId'))->toBe($giftSku);
    expect(fvChildText($parsed['lines'][1], 'qty'))->toBe('5');
    expect(fvChildText($parsed['lines'][1], 'unitPrice'))->toBe('0.00');
    expect(fvChildText($parsed['salesOrder'], 'TRO_E_obsequio'))->toBe('0');
    expect(array_map(fn (DOMElement $el) => $el->localName, fvDirectChildren($parsed['salesOrder'])))->toBe(fvSalesOrderChildNames());
});

it('excludes bonificaciones when the city is disabled', function () {
    fvSoapConfig();
    fvForgetBonificacionSetting();
    $fx = fvMakeSoapOrder();
    fvAssignCity($fx['order'], 'Medellín');
    fvEnableBonificaciones(['bogota']);
    $giftSku = fvAttachGift($fx['order']);

    $xml = fvBuildSoapXml($fx['order']->fresh());
    $parsed = fvAssertSalesOrderContract($xml);

    expect($parsed['lines'])->toHaveCount(2);
    expect($xml)->not->toContain($giftSku);
    expect(fvChildText($parsed['salesOrder'], 'TRO_E_obsequio'))->toBe('0');
    expect(fvChildText($parsed['lines'][0], 'itemId'))->toBe($fx['sku']);
    expect(fvChildText($parsed['lines'][1], 'itemId'))->toBe('FL00001');
});

it('excludes bonificaciones for an unknown city and when the setting is missing', function () {
    fvSoapConfig();
    fvForgetBonificacionSetting();
    $fx = fvMakeSoapOrder();
    fvAssignCity($fx['order'], 'Bello');
    fvEnableBonificaciones([
        'bogota', 'villavicencio', 'medellin', 'monteria', 'cali', 'pereira', 'cucuta', 'bucaramanga', 'barranquilla',
    ]);
    $giftSku = fvAttachGift($fx['order']);

    $xml = fvBuildSoapXml($fx['order']->fresh());
    expect($xml)->not->toContain($giftSku);
    expect(fvAssertSalesOrderContract($xml)['lines'])->toHaveCount(2);

    fvForgetBonificacionSetting();
    $plain = fvMakeSoapOrder();
    $plainGift = fvAttachGift($plain['order']);
    Setting::query()->where('key', Setting::FV_BONIFICACIONES_CITIES_KEY)->delete();
    fvForgetBonificacionSetting();

    $plainXml = fvBuildSoapXml($plain['order']->fresh());
    expect($plainXml)->not->toContain($plainGift);
    expect(fvAssertSalesOrderContract($plainXml)['lines'])->toHaveCount(2);
    expect(fvChildText(fvAssertSalesOrderContract($plainXml)['salesOrder'], 'TRO_E_obsequio'))->toBe('0');
});
