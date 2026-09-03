<?php

use App\Models\Bonification;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Services\BonificationCheckoutService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

function bonificationExpressFixtures(): array
{
    Setting::updateOrCreate(['key' => 'inventory_enabled'], ['name' => 'inv', 'value' => '0', 'show' => false]);
    Setting::updateOrCreate(['key' => 'min_amount'], ['name' => 'min', 'value' => '0', 'show' => false]);
    Setting::updateOrCreate(['key' => 'express_48h_enabled'], ['name' => '48h', 'value' => '1', 'show' => false]);
    Cache::forget('setting_express_48h_enabled');

    $tax = Tax::create(['name' => 'IVA bonif express', 'tax' => 0]);
    $vendor = Vendor::create(['name' => 'V bonif express', 'slug' => 'v-bonif-ex', 'minimum_purchase' => 0, 'active' => 1]);
    $brand = Brand::create(['name' => 'B bonif express', 'slug' => 'b-bonif-ex', 'vendor_id' => $vendor->id]);

    $gift = Product::create([
        'name' => 'Gift bonif express',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'GIFT-BONIF-EX',
        'slug' => 'gift-bonif-ex',
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
    ]);

    $product = Product::create([
        'name' => 'Trigger bonif express',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'TRIG-BONIF-EX',
        'slug' => 'trig-bonif-ex',
        'active' => 1,
        'price' => 10_000,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    $bonification = Bonification::create([
        'name' => 'Lleva 10 lleva 1',
        'buy' => 10,
        'get' => 1,
        'product_id' => $gift->id,
        'max' => 10,
        'allow_discounts' => true,
    ]);
    $product->bonifications()->attach($bonification->id);

    $client = User::factory()->create();
    $zone = Zone::create([
        'user_id' => $client->id,
        'route' => 'R-BONIF',
        'zone' => '102',
        'day' => '1',
        'address' => 'Calle Bonif',
        'code' => 'BONIF-1',
        'fulfillment_provider_48h' => 'coordinadora',
        'shipping_standard_enabled' => true,
        'shipping_express_enabled' => true,
        'dane_code' => '05001000',
    ]);

    return compact('client', 'zone', 'product', 'gift', 'bonification');
}

it('detects when the cart qualifies for a bonification', function () {
    ['product' => $product] = bonificationExpressFixtures();

    expect(BonificationCheckoutService::cartHasQualifyingBonifications([
        ['product_id' => $product->id, 'quantity' => 9],
    ]))->toBeFalse()
        ->and(BonificationCheckoutService::cartHasQualifyingBonifications([
            ['product_id' => $product->id, 'quantity' => 10],
        ]))->toBeTrue();
});

it('rejects express checkout when the cart has qualifying bonifications', function () {
    ['client' => $client, 'zone' => $zone, 'product' => $product] = bonificationExpressFixtures();

    actingAs($client)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 10, 'variation_id' => null],
            ],
        ])
        ->get(route('cart'))
        ->assertOk()
        ->assertSee(BonificationCheckoutService::expressBlockedByBonificationsMessage(), false)
        ->assertSee('cartHasBonifications = true', false);

    actingAs($client)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 10, 'variation_id' => null],
            ],
        ])
        ->post(route('cart.process'), [
            'zone_id' => $zone->id,
            'delivery_method' => Order::DELIVERY_METHOD_EXPRESS,
            'observations' => '',
        ])
        ->assertSessionHas('error', BonificationCheckoutService::expressBlockedByBonificationsMessage());

    expect(Order::query()->count())->toBe(0);
});

it('still allows standard checkout when the cart has bonifications', function () {
    ['client' => $client, 'zone' => $zone, 'product' => $product] = bonificationExpressFixtures();

    actingAs($client)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 10, 'variation_id' => null],
            ],
        ])
        ->post(route('cart.process'), [
            'zone_id' => $zone->id,
            'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
            'observations' => '',
        ])
        ->assertSessionMissing('error');

    expect(Order::query()->count())->toBe(1)
        ->and(Order::query()->first()->delivery_method)->toBe(Order::DELIVERY_METHOD_TRONEX);
});

it('allows express when the cart does not yet qualify for a bonification', function () {
    ['client' => $client, 'zone' => $zone, 'product' => $product] = bonificationExpressFixtures();

    $product->update([
        'coordinadora_weight_kg' => 0.5,
        'coordinadora_height_cm' => 10,
        'coordinadora_width_cm' => 8,
        'coordinadora_length_cm' => 12,
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

    actingAs($client)
        ->withSession([
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 5, 'variation_id' => null],
            ],
        ])
        ->post(route('cart.process'), [
            'zone_id' => $zone->id,
            'delivery_method' => Order::DELIVERY_METHOD_EXPRESS,
            'observations' => '',
        ])
        ->assertSessionMissing('error');

    $order = Order::query()->first();
    expect($order)->not->toBeNull()
        ->and($order->delivery_method)->toBe(Order::DELIVERY_METHOD_EXPRESS);
});
