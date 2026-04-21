<?php

use App\Repositories\OrderRepository;
use Tests\Helpers\SoapXmlPricing;

/*
|--------------------------------------------------------------------------
| SOAP XML pricing E2E (OrderRepository → full envelope)
|--------------------------------------------------------------------------
| End-to-end checks: persisted Product + OrderProduct lines as stored by checkout,
| then OrderRepository::buildOrderXmlForDiagnostic output. Guards against regressions
| in dyn:unitPrice / dyn:discount for Dynamics SOAP.
*/

it('E2E SOAP XML: non-discounted line uses stored price as dyn:unitPrice', function () {
    $tax = SoapXmlPricing::makeTax();
    $vendor = SoapXmlPricing::makeVendor();
    $brand = SoapXmlPricing::makeBrand($vendor);
    $zone = SoapXmlPricing::makeZone();
    $user = SoapXmlPricing::makeUser($zone);
    $p = SoapXmlPricing::makeProduct($brand, $tax, ['price' => 750]);

    [$order, $prods] = SoapXmlPricing::mockOrder($user, $zone, [[
        'product' => $p,
        'quantity' => 3,
        'price' => 750,
        'percentage' => 0,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);

    expect($xml)->not->toBeNull()
        ->and($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>750.00</dyn:unitPrice>')
        ->and($xml)->toContain('<dyn:qty>3</dyn:qty>');
});

it('E2E SOAP XML: calculate_package_price divides stored package total for dyn:unitPrice', function () {
    $tax = SoapXmlPricing::makeTax();
    $vendor = SoapXmlPricing::makeVendor();
    $brand = SoapXmlPricing::makeBrand($vendor);
    $zone = SoapXmlPricing::makeZone();
    $user = SoapXmlPricing::makeUser($zone);
    $p = SoapXmlPricing::makeProduct($brand, $tax, [
        'price' => 600,
        'package_quantity' => 3,
        'calculate_package_price' => true,
    ]);

    [$order, $prods] = SoapXmlPricing::mockOrder($user, $zone, [[
        'product' => $p,
        'quantity' => 2,
        'price' => 1800,
        'percentage' => 0,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);

    expect($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>600.00</dyn:unitPrice>');
});

it('E2E SOAP XML: percentage discount maps to dyn:discount with base unit from package split', function () {
    $tax = SoapXmlPricing::makeTax();
    $vendor = SoapXmlPricing::makeVendor();
    $brand = SoapXmlPricing::makeBrand($vendor);
    $zone = SoapXmlPricing::makeZone();
    $user = SoapXmlPricing::makeUser($zone);
    $p = SoapXmlPricing::makeProduct($brand, $tax, [
        'price' => 600,
        'package_quantity' => 3,
        'calculate_package_price' => true,
    ]);

    [$order, $prods] = SoapXmlPricing::mockOrder($user, $zone, [[
        'product' => $p,
        'quantity' => 1,
        'price' => 1800,
        'percentage' => 20,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);

    expect($xml)->toContain('<dyn:discount>20</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>600.00</dyn:unitPrice>');
});

it('E2E SOAP XML: fixed_amount reduces dyn:unitPrice and sets dyn:discount to 0', function () {
    $tax = SoapXmlPricing::makeTax();
    $vendor = SoapXmlPricing::makeVendor();
    $brand = SoapXmlPricing::makeBrand($vendor);
    $zone = SoapXmlPricing::makeZone();
    $user = SoapXmlPricing::makeUser($zone);
    $p = SoapXmlPricing::makeProduct($brand, $tax, [
        'price' => 600,
        'package_quantity' => 3,
        'calculate_package_price' => true,
    ]);

    [$order, $prods] = SoapXmlPricing::mockOrder($user, $zone, [[
        'product' => $p,
        'quantity' => 1,
        'price' => 1800,
        'percentage' => 0,
        'discount_type' => 'fixed_amount',
        'flat_discount_amount' => 100,
    ]]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);

    expect($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>500.00</dyn:unitPrice>');
});

it('E2E SOAP XML: multi-line order preserves independent discount and unit price per line', function () {
    $tax = SoapXmlPricing::makeTax();
    $vendor = SoapXmlPricing::makeVendor();
    $brand = SoapXmlPricing::makeBrand($vendor);
    $zone = SoapXmlPricing::makeZone();
    $user = SoapXmlPricing::makeUser($zone);
    $p1 = SoapXmlPricing::makeProduct($brand, $tax, ['price' => 1000]);
    $p2 = SoapXmlPricing::makeProduct($brand, $tax, [
        'price' => 500,
        'package_quantity' => 5,
        'calculate_package_price' => true,
    ]);

    [$order, $prods] = SoapXmlPricing::mockOrder($user, $zone, [
        [
            'product' => $p1,
            'quantity' => 2,
            'price' => 1000,
            'percentage' => 10,
            'discount_type' => 'percentage',
            'flat_discount_amount' => 0,
        ],
        [
            'product' => $p2,
            'quantity' => 1,
            'price' => 2500,
            'percentage' => 0,
            'discount_type' => 'percentage',
            'flat_discount_amount' => 0,
        ],
    ]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false, $prods);

    expect($xml)->toContain('<dyn:discount>10</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>1000.00</dyn:unitPrice>')
        ->and($xml)->toContain('<dyn:discount>0</dyn:discount>')
        ->and($xml)->toContain('<dyn:unitPrice>500.00</dyn:unitPrice>');
});
