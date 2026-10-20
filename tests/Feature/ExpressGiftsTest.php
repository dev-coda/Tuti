<?php

use App\Models\Bonification;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderProductBonification;
use App\Models\Product;
use App\Models\Tax;
use App\Services\ExpressGifts\ExpressGiftAllocationService;
use App\Services\ExpressGifts\ExpressGiftContext;
use App\Services\ExpressGifts\ExpressGiftPlanner;
use App\Services\Shipping\FvDynamicsService;
use Tests\Helpers\SoapXmlPricing;

/*
|--------------------------------------------------------------------------
| Obsequios Express: descuento O obsequio + prorrateo en la factura FV
|--------------------------------------------------------------------------
*/

function expressGiftFixture(array $triggerOverrides = [], array $otherOverrides = [], array $bonifOverrides = []): array
{
    $tax = Tax::create(['name' => 'IVA '.uniqid(), 'tax' => 19]);
    $vendor = SoapXmlPricing::makeVendor();
    $brand = SoapXmlPricing::makeBrand($vendor);

    $trigger = SoapXmlPricing::makeProduct($brand, $tax, array_merge(['price' => 1000], $triggerOverrides));
    $other = SoapXmlPricing::makeProduct($brand, $tax, array_merge(['price' => 2000], $otherOverrides));
    $gift = SoapXmlPricing::makeProduct($brand, $tax, ['price' => 500, 'sku' => 'GIFT-SKU']);

    $bonification = Bonification::create(array_merge([
        'name' => 'Compra 10 lleva 2',
        'buy' => 10,
        'get' => 2,
        'product_id' => $gift->id,
        'max' => 10,
        'allow_discounts' => false,
        'gift_invoice_unit_value' => 100,
    ], $bonifOverrides));
    $trigger->bonifications()->attach($bonification->id);

    return compact('trigger', 'other', 'gift', 'bonification', 'tax');
}

function expressGiftCart(Product $trigger, Product $other, int $triggerQty = 10, int $otherQty = 5): array
{
    return [
        ['product_id' => $trigger->id, 'quantity' => $triggerQty, 'variation_id' => null],
        ['product_id' => $other->id, 'quantity' => $otherQty, 'variation_id' => null],
    ];
}

it('plans the gift (no conflict) for a product without discount', function () {
    ['trigger' => $trigger, 'other' => $other] = expressGiftFixture();

    $plan = app(ExpressGiftPlanner::class)->plan(expressGiftCart($trigger, $other), false);

    expect($plan['granted'])->toHaveKey($trigger->id)
        ->and($plan['denied'])->toBe([])
        ->and($plan['blocked'])->toBe([])
        ->and($plan['proration']['feasible'])->toBeTrue()
        ->and(ExpressGiftContext::current())->toBeNull();
});

it('keeps the discount when it is more favorable than the gift (never both)', function () {
    // Discount 50% on 10 x 1000 = 5000 savings; gift 2 x 500 = 1000 value.
    ['trigger' => $trigger, 'other' => $other] = expressGiftFixture(['discount' => 50]);

    $plan = app(ExpressGiftPlanner::class)->plan(expressGiftCart($trigger, $other), false);

    expect($plan['denied'])->toHaveKey($trigger->id)
        ->and($plan['granted'])->toBe([])
        ->and($plan['blocked'])->toBe([])
        ->and($plan['decisions'][$trigger->id]['decision'])->toBe('discount');
});

it('picks the gift and blocks the discount when the gift is worth more', function () {
    // Discount 1% on 10 x 1000 = 100 savings; gift 2 x 500 = 1000 value.
    ['trigger' => $trigger, 'other' => $other] = expressGiftFixture(['discount' => 1]);

    $plan = app(ExpressGiftPlanner::class)->plan(expressGiftCart($trigger, $other), false);

    expect($plan['granted'])->toHaveKey($trigger->id)
        ->and($plan['blocked'])->toHaveKey($trigger->id)
        ->and($plan['decisions'][$trigger->id]['decision'])->toBe('gift_over_discount');

    // While the context is active the blocked product has no discount and the others keep theirs.
    ExpressGiftContext::activate(ExpressGiftContext::MODE_ACTIVE, $plan['blocked']);
    try {
        expect($trigger->fresh()->getFinalPriceForUser(false)['discount'])->toEqual(0);
    } finally {
        ExpressGiftContext::deactivate();
    }
});

it('does not offer the gift when proration would leave a line at zero', function () {
    // Gift invoiced at 100000 per unit against ~10000 + 10000 of non-discounted lines.
    ['trigger' => $trigger, 'other' => $other] = expressGiftFixture([], [], ['gift_invoice_unit_value' => 100000]);

    $plan = app(ExpressGiftPlanner::class)->plan(expressGiftCart($trigger, $other), false);

    expect($plan['granted'])->toBe([])
        ->and($plan['denied'])->toHaveKey($trigger->id)
        ->and($plan['proration']['feasible'])->toBeFalse()
        ->and($plan['messages'][0])->toContain('No se pudo aplicar el obsequio');
});

it('trigger scope prorates the gift only onto the product that qualified', function () {
    ['trigger' => $trigger, 'other' => $other] = expressGiftFixture([], [], ['gift_proration_scope' => 'trigger']);

    $plan = app(ExpressGiftPlanner::class)->plan(expressGiftCart($trigger, $other), false);

    expect($plan['granted'])->toHaveKey($trigger->id)
        ->and($plan['failed_gift_keys'])->toBe([])
        ->and($plan['proration']['deductions']['cart:0'])->toBeGreaterThan(0)
        ->and($plan['proration']['deductions'])->not->toHaveKey('cart:1');
});

it('legacy discount rule is untouched outside of an express context', function () {
    ['trigger' => $trigger] = expressGiftFixture(['discount' => 20]);

    // allow_discounts=false on every bonification => classic rule removes the discount.
    expect($trigger->fresh()->getFinalPriceForUser(false)['discount'])->toEqual(0);
});

it('prorates the gift into the FV invoice and the totals match', function () {
    ['trigger' => $trigger, 'other' => $other, 'gift' => $gift, 'bonification' => $bonification] = expressGiftFixture();
    $zone = SoapXmlPricing::makeZone();
    $user = SoapXmlPricing::makeUser($zone);

    $order = Order::create([
        'user_id' => $user->id,
        'zone_id' => $zone->id,
        'total' => 0,
        'discount' => 0,
        'status_id' => Order::STATUS_PENDING,
        'delivery_method' => Order::DELIVERY_METHOD_EXPRESS,
        'shipping_provider' => Order::SHIPPING_PROVIDER_COORDINADORA,
    ]);

    OrderProduct::create([
        'order_id' => $order->id, 'product_id' => $trigger->id, 'quantity' => 10, 'price' => 1000,
        'discount' => 0, 'percentage' => 0, 'package_quantity' => 1, 'discount_type' => 'percentage', 'flat_discount_amount' => 0,
    ]);
    OrderProduct::create([
        'order_id' => $order->id, 'product_id' => $other->id, 'quantity' => 5, 'price' => 2000,
        'discount' => 0, 'percentage' => 0, 'package_quantity' => 1, 'discount_type' => 'percentage', 'flat_discount_amount' => 0,
    ]);
    OrderProductBonification::create([
        'bonification_id' => $bonification->id,
        'order_product_id' => $order->products()->first()->id,
        'product_id' => $gift->id,
        'quantity' => 2,
        'order_id' => $order->id,
        'invoice_unit_value' => 100,
    ]);

    $originalInclTotal = (10 * 1000 + 5 * 2000) * 1.19; // order total with IVA

    $allocation = app(ExpressGiftAllocationService::class)->allocate($order->fresh());
    expect($allocation['success'])->toBeTrue()->and($allocation['applied'])->toBeTrue();

    $order = $order->fresh(['products.product', 'user', 'zone']);

    $service = app(FvDynamicsService::class);
    $method = new ReflectionMethod($service, 'buildLinesXml');
    $method->setAccessible(true);
    $xml = $method->invoke($service, $order, 'WH1');

    preg_match_all('#<dyn:LinesDetails>(.*?)</dyn:LinesDetails>#s', $xml, $blocks);
    expect($blocks[1])->not->toBeEmpty();

    $invoiceTotal = 0.0;
    $giftLines = 0;
    foreach ($blocks[1] as $block) {
        preg_match('#<dyn:qty>(.*?)</dyn:qty>#', $block, $qty);
        preg_match('#<dyn:unitPrice>(.*?)</dyn:unitPrice>#', $block, $price);
        preg_match('#<dyn:discountPercent>(.*?)</dyn:discountPercent>#', $block, $disc);
        preg_match('#<dyn:taxValue>(.*?)</dyn:taxValue>#', $block, $tax);
        preg_match('#<dyn:itemId>(.*?)</dyn:itemId>#', $block, $item);

        $net = (float) $qty[1] * (float) $price[1] * (1 - (float) $disc[1] / 100);
        $invoiceTotal += $net + (float) $tax[1];
        expect((float) $price[1])->toBeGreaterThan(0);
        if ($item[1] === 'GIFT-SKU') {
            $giftLines++;
        }
    }

    expect($giftLines)->toBeGreaterThan(0)
        ->and(abs($invoiceTotal - $originalInclTotal))->toBeLessThanOrEqual(0.10);

    // The paid lines were reduced and the audit values were stored.
    expect((float) $order->products->first()->gift_deduction_per_unit)->toBeGreaterThan(0);
    $row = OrderProductBonification::where('order_id', $order->id)->first();
    expect((float) $row->invoice_total_value)->toBeGreaterThan(0);
});

it('releases invoiced gifts when the allocation is not feasible and keeps the order intact', function () {
    ['trigger' => $trigger, 'gift' => $gift, 'bonification' => $bonification] = expressGiftFixture([], [], ['gift_invoice_unit_value' => 100000]);
    $zone = SoapXmlPricing::makeZone();
    $user = SoapXmlPricing::makeUser($zone);

    $order = Order::create([
        'user_id' => $user->id, 'zone_id' => $zone->id, 'total' => 0, 'discount' => 0,
        'status_id' => Order::STATUS_PENDING,
        'delivery_method' => Order::DELIVERY_METHOD_EXPRESS,
        'shipping_provider' => Order::SHIPPING_PROVIDER_COORDINADORA,
    ]);
    $line = OrderProduct::create([
        'order_id' => $order->id, 'product_id' => $trigger->id, 'quantity' => 10, 'price' => 1000,
        'discount' => 0, 'percentage' => 0, 'package_quantity' => 1, 'discount_type' => 'percentage', 'flat_discount_amount' => 0,
    ]);
    OrderProductBonification::create([
        'bonification_id' => $bonification->id, 'order_product_id' => $line->id, 'product_id' => $gift->id,
        'quantity' => 2, 'order_id' => $order->id, 'invoice_unit_value' => 100000,
    ]);

    $service = app(ExpressGiftAllocationService::class);
    $allocation = $service->allocate($order->fresh());

    expect($allocation['success'])->toBeFalse()
        ->and($allocation['message'])->toContain('No se pudo aplicar el obsequio');

    expect($service->releaseInvoicedGifts($order->fresh(), false, null))->toBe(1)
        ->and(OrderProductBonification::where('order_id', $order->id)->count())->toBe(0)
        ->and((float) $line->fresh()->gift_deduction_per_unit)->toEqual(0.0);
});


/*
|--------------------------------------------------------------------------
| Checkout end-to-end (CartController::processOrder)
|--------------------------------------------------------------------------
*/

function expressCheckoutSetup(string $deliveryMethod, array $triggerOverrides = [], ?callable $beforePost = null): array
{
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    $seller = \App\Models\User::factory()->create();
    $seller->assignRole('seller');
    $client = \App\Models\User::factory()->create(['document' => null]);

    $fixture = expressGiftFixture($triggerOverrides);
    $zone = \App\Models\Zone::create([
        'user_id' => $client->id,
        'route' => 'R100',
        'zone' => '301',
        'day' => 'Lunes',
        'address' => 'Calle 1',
        'code' => 'SUC1',
    ]);

    foreach ([
        'inventory_enabled' => '0',
        'force_delivery_date_enabled' => '0',
        'min_amount' => '0',
        'express_48h_enabled' => '1',
    ] as $key => $value) {
        \App\Models\Setting::updateOrCreate(['key' => $key], ['name' => $key, 'value' => $value, 'show' => false]);
    }

    app()->instance(
        \App\Services\Shipping\CoordinadoraQuoteService::class,
        \Mockery::mock(\App\Services\Shipping\CoordinadoraQuoteService::class, function ($mock) {
            $mock->shouldReceive('quoteFromCart')->andReturn(['shipping_cost' => 0]);
        })
    );
    \Illuminate\Support\Facades\Queue::fake();

    session([
        'user_id' => $client->id,
        'cart' => [
            ['product_id' => $fixture['trigger']->id, 'quantity' => 10, 'variation_id' => null],
            ['product_id' => $fixture['other']->id, 'quantity' => 5, 'variation_id' => null],
        ],
    ]);

    if ($beforePost) {
        $beforePost($fixture);
    }

    $response = \Pest\Laravel\actingAs($seller)->post(route('cart.process'), [
        'zone_id' => (string) $zone->id,
        'sucursal_zone_id' => (string) $zone->id,
        'sucursal_code' => $zone->code,
        'sucursal_route' => $zone->route,
        'sucursal_zone' => $zone->zone,
        'sucursal_day' => $zone->day,
        'sucursal_address' => $zone->address,
        'delivery_method' => $deliveryMethod,
        'observations' => 'Express gift checkout',
    ]);

    return [$response, Order::latest('id')->first(), $fixture];
}

it('express checkout invoices the gift, prorates it and keeps the order total', function () {
    [$response, $order, $fixture] = expressCheckoutSetup('express');

    $response->assertRedirect();
    expect($order)->not->toBeNull()
        ->and($order->delivery_method)->toBe(Order::DELIVERY_METHOD_EXPRESS);

    $row = OrderProductBonification::where('order_id', $order->id)->first();
    expect($row)->not->toBeNull()
        ->and((float) $row->invoice_unit_value)->toEqual(100.0)
        ->and((float) $row->invoice_total_value)->toBeGreaterThan(0)
        ->and($order->fresh()->express_gift_audit)->toBeArray();

    foreach ($order->products as $line) {
        expect((float) $line->gift_deduction_per_unit)->toBeGreaterThan(0);
    }
    // Merchandise total unchanged (gift is paid out of the same lines): (10*1000 + 5*2000) * 1.19
    expect(round((float) $order->total, 2))->toEqual(23800.0);
});

it('express checkout keeps the better discount and grants no gift', function () {
    [$response, $order] = expressCheckoutSetup('express', ['discount' => 50]);

    $response->assertRedirect();
    expect(OrderProductBonification::where('order_id', $order->id)->count())->toBe(0);

    $triggerLine = $order->products->firstWhere('percentage', '>', 0);
    expect($triggerLine)->not->toBeNull()
        ->and((int) $triggerLine->percentage)->toBe(50);
    expect($order->products->every(fn ($l) => (float) $l->gift_deduction_per_unit === 0.0))->toBeTrue();
});

it('express checkout grants different gifts when each spending threshold is met', function () {
    [$response, $order, $fixture] = expressCheckoutSetup('express', [], function (array $fixture) {
        $brand = \App\Models\Brand::find($fixture['trigger']->brand_id);
        $mug = SoapXmlPricing::makeProduct($brand, $fixture['tax'], ['price' => 300, 'sku' => 'MUG-GIFT']);
        $spoon = SoapXmlPricing::makeProduct($brand, $fixture['tax'], ['price' => 80, 'sku' => 'SPOON-GIFT']);

        $mugRule = \App\Models\SpendingGift::create([
            'name' => 'Taza por compra',
            'minimum_amount' => 1,
            'product_id' => $mug->id,
            'gift_quantity' => 1,
            'gift_proration_scope' => 'trigger',
            'active' => true,
        ]);
        $mugRule->products()->attach([$fixture['trigger']->id, $fixture['other']->id]);

        $spoonRule = \App\Models\SpendingGift::create([
            'name' => 'Cuchara por compra alta',
            'minimum_amount' => 99999999,
            'product_id' => $spoon->id,
            'gift_quantity' => 1,
            'active' => true,
        ]);
        $spoonRule->products()->attach($fixture['other']->id);
    });

    $response->assertRedirect();

    $granted = OrderProductBonification::where('order_id', $order->id)->whereNotNull('spending_gift_id')->get();
    expect($granted)->toHaveCount(1)
        ->and($granted->first()->product->sku)->toBe('MUG-GIFT')
        ->and((int) $granted->first()->quantity)->toBe(1)
        ->and($granted->first()->bonification_id)->toBeNull();

    expect(collect($order->fresh()->express_gift_audit['messages'] ?? [])->implode(' '))
        ->toContain('Taza por compra')
        ->not->toContain('Cuchara por compra alta');

    expect(round((float) $order->fresh()->total, 2))->toEqual(23800.0);
});

it('admin can open the spending gift screen and save a gift with its threshold', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = \App\Models\User::factory()->create();
    $admin->assignRole('admin');
    ['gift' => $gift, 'other' => $other] = expressGiftFixture();

    $this->actingAs($admin)->get(route('spending-gifts.index'))
        ->assertOk()
        ->assertSee('Obsequios por monto');

    $this->actingAs($admin)->get(route('spending-gifts.create'))
        ->assertOk()
        ->assertSee('Monto mínimo (con IVA)');

    $this->actingAs($admin)->post(route('spending-gifts.store'), [
        'name' => 'Taza desde 50000',
        'minimum_amount' => 50000,
        'product_id' => $gift->id,
        'gift_quantity' => 1,
        'gift_proration_scope' => 'trigger',
        'active' => '1',
    ])->assertRedirect();

    $rule = \App\Models\SpendingGift::where('name', 'Taza desde 50000')->first();
    expect($rule)->not->toBeNull()
        ->and((float) $rule->minimum_amount)->toEqual(50000.0)
        ->and((int) $rule->product_id)->toBe($gift->id)
        ->and($rule->active)->toBeTrue();

    $rule->products()->attach($other->id);

    $this->actingAs($admin)->get(route('spending-gifts.edit', $rule))
        ->assertOk()
        ->assertSee('Productos que suman')
        ->assertSee($other->name);
});

it('sums units across different products when the bonification is set to add them', function () {
    ['trigger' => $trigger, 'other' => $other, 'bonification' => $bonification] = expressGiftFixture([], [], [
        'buy' => 10,
        'get' => 1,
        'max' => 1,
        'sum_products' => true,
    ]);
    $other->bonifications()->attach($bonification->id);
    $cart = [
        ['product_id' => $trigger->id, 'quantity' => 6, 'variation_id' => null],
        ['product_id' => $other->id, 'quantity' => 4, 'variation_id' => null],
    ];

    $plan = app(ExpressGiftPlanner::class)->plan($cart, false);

    expect($plan['granted_sums'])->toHaveKey($bonification->id)
        ->and($plan['granted_sums'][$bonification->id]['units'])->toBe(10)
        ->and($plan['granted_sums'][$bonification->id]['quantity'])->toBe(1)
        ->and($plan['granted'])->toBe([]);

    $onlySugar = app(ExpressGiftPlanner::class)->plan([
        ['product_id' => $trigger->id, 'quantity' => 10, 'variation_id' => null],
    ], false);
    expect($onlySugar['granted_sums'][$bonification->id]['quantity'])->toBe(1);
});

it('keeps each product separate when the bonification does not sum quantities', function () {
    ['trigger' => $trigger, 'other' => $other, 'bonification' => $bonification] = expressGiftFixture([], [], [
        'buy' => 10,
        'get' => 1,
        'max' => 1,
        'sum_products' => false,
    ]);
    $other->bonifications()->attach($bonification->id);

    $plan = app(ExpressGiftPlanner::class)->plan([
        ['product_id' => $trigger->id, 'quantity' => 6, 'variation_id' => null],
        ['product_id' => $other->id, 'quantity' => 4, 'variation_id' => null],
    ], false);

    expect($plan['granted'])->toBe([])
        ->and($plan['granted_sums'])->toBe([]);
});

it('express checkout grants one gift when 6 of one product and 4 of another add up to 10', function () {
    [$response, $order] = expressCheckoutSetup('express', [], function (array $fixture) {
        $fixture['bonification']->update([
            'buy' => 10,
            'get' => 1,
            'max' => 1,
            'sum_products' => true,
            'gift_invoice_unit_value' => 100,
        ]);
        $fixture['other']->bonifications()->attach($fixture['bonification']->id);
        session([
            'user_id' => session('user_id'),
            'cart' => [
                ['product_id' => $fixture['trigger']->id, 'quantity' => 6, 'variation_id' => null],
                ['product_id' => $fixture['other']->id, 'quantity' => 4, 'variation_id' => null],
            ],
        ]);
    });

    $response->assertRedirect();
    $rows = OrderProductBonification::where('order_id', $order->id)->get();
    expect($rows)->toHaveCount(1)
        ->and((int) $rows->first()->quantity)->toBe(1)
        ->and((float) $rows->first()->invoice_unit_value)->toEqual(100.0);
    expect(round((float) $order->fresh()->total, 2))->toEqual(16660.0);
});

it('tronex checkout keeps the classic $0 obsequio untouched', function () {
    [$response, $order] = expressCheckoutSetup('tronex');

    $response->assertRedirect();
    $row = OrderProductBonification::where('order_id', $order->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->invoice_unit_value)->toBeNull()
        ->and($row->invoice_total_value)->toBeNull()
        ->and($order->products->every(fn ($l) => (float) $l->gift_deduction_per_unit === 0.0))->toBeTrue();
});
