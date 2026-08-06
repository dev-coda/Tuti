<?php

use App\Models\Order;
use App\Models\Setting;
use App\Models\Zone;
use Illuminate\Support\Facades\Cache;

it('toggles zone shipping methods independently', function () {
    $zone = Zone::create([
        'route' => 'R1',
        'zone' => 'Z1',
        'day' => '1',
        'address' => 'Calle Zona',
        'code' => 'Z-SHIP-1',
        'shipping_standard_enabled' => true,
        'shipping_express_enabled' => false,
    ]);

    expect($zone->allowsShippingMethod(Order::DELIVERY_METHOD_TRONEX))->toBeTrue()
        ->and($zone->allowsShippingMethod(Order::DELIVERY_METHOD_EXPRESS))->toBeFalse();

    $zone->update([
        'shipping_standard_enabled' => false,
        'shipping_express_enabled' => true,
    ]);

    expect($zone->fresh()->allowsShippingMethod(Order::DELIVERY_METHOD_TRONEX))->toBeFalse()
        ->and($zone->fresh()->allowsShippingMethod(Order::DELIVERY_METHOD_EXPRESS))->toBeTrue();
});

it('requires the free special shipping toggle before applying the minimum', function () {
    Setting::updateOrCreate(
        ['key' => 'express_free_shipping_min'],
        ['name' => 'min', 'value' => '100000', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'express_free_shipping_enabled'],
        ['name' => 'enabled', 'value' => '0', 'show' => false]
    );
    Cache::forget('setting_express_free_shipping_min');
    Cache::forget('setting_express_free_shipping_enabled');

    expect(Setting::qualifiesForExpressFreeShipping(150000))->toBeFalse();

    Setting::updateOrCreate(
        ['key' => 'express_free_shipping_enabled'],
        ['name' => 'enabled', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_express_free_shipping_enabled');

    expect(Setting::qualifiesForExpressFreeShipping(150000))->toBeTrue()
        ->and(Setting::qualifiesForExpressFreeShipping(50000))->toBeFalse();
});

it('includes freight in the thank-you payable total', function () {
    $order = new Order([
        'total' => 100000,
        'shipping_quote_amount' => 13500,
    ]);

    // Avoid line-item recompute by using a plain model without relations loaded.
    expect($order->totalPayable())->toBe(113500.0);
});
