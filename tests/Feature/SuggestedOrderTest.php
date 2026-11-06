<?php

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Models\Zone;
use App\Services\SuggestedOrderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    Cache::flush();
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->tax = Tax::factory()->create(['tax' => 0, 'name' => 'Sin IVA sugerido']);
});

function suggestedProduct(int $price): Product
{
    return Product::factory()->create([
        'price' => $price,
        'discount' => 0,
        'tax_id' => test()->tax->id,
        'package_quantity' => 1,
        'quantity_min' => 1,
        'quantity_max' => 20,
        'step' => 1,
        'active' => true,
    ]);
}

function suggestedOrderFor(User $client, array $lines, ?int $zoneId = null): Order
{
    $total = 0;
    foreach ($lines as $line) {
        $total += $line['price'] * $line['quantity'];
    }

    $order = Order::create([
        'user_id' => $client->id,
        'status_id' => Order::STATUS_PROCESSED,
        'total' => $total,
        'discount' => 0,
        'zone_id' => $zoneId,
    ]);

    foreach ($lines as $line) {
        OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $line['product']->id,
            'quantity' => $line['quantity'],
            'price' => $line['price'],
            'discount' => 0,
            'is_bonification' => false,
        ]);
    }

    return $order;
}

function suggestedWeights(int $history, int $together, int $bestseller, float $overorder): void
{
    $values = [
        'suggested_order_enabled' => '1',
        'suggested_order_weight_history' => $history,
        'suggested_order_weight_together' => $together,
        'suggested_order_weight_bestseller' => $bestseller,
        'suggested_order_overorder_percent' => $overorder,
    ];

    foreach ($values as $key => $value) {
        Setting::updateOrCreate(
            ['key' => $key],
            ['name' => $key, 'value' => (string) $value, 'show' => false]
        );
    }

    SuggestedOrderService::forgetConfigCache();
}

/**
 * @return array{client: User, staple: Product, together: Product, bestseller: Product}
 */
function suggestedCatalog(): array
{
    $client = User::factory()->create(['name' => 'Cliente Frecuente']);
    $staple = suggestedProduct(10000);
    $together = suggestedProduct(10000);
    $bestseller = suggestedProduct(10000);

    foreach (range(1, 4) as $ignored) {
        suggestedOrderFor($client, [
            ['product' => $staple, 'quantity' => 2, 'price' => 10000],
        ]);
    }

    $other = User::factory()->create();
    foreach (range(1, 10) as $ignored) {
        suggestedOrderFor($other, [
            ['product' => $staple, 'quantity' => 1, 'price' => 10000],
            ['product' => $together, 'quantity' => 1, 'price' => 10000],
        ]);
    }
    foreach (range(1, 5) as $ignored) {
        suggestedOrderFor($other, [
            ['product' => $bestseller, 'quantity' => 10, 'price' => 10000],
        ]);
    }

    return compact('client', 'staple', 'together', 'bestseller');
}

function suggestedQuantities(User $client): array
{
    $result = app(SuggestedOrderService::class)->build($client);
    $quantities = [];
    foreach ($result['lines'] as $line) {
        $quantities[$line['product_id']] = $line['quantity'];
    }

    return ['quantities' => $quantities, 'meta' => $result['meta']];
}

it('overorders the usual products when history has the highest priority', function () {
    $catalog = suggestedCatalog();
    suggestedWeights(100, 0, 0, 50);

    $suggestion = suggestedQuantities($catalog['client']);

    expect($suggestion['meta']['average_order'])->toEqual(20000.0)
        ->and($suggestion['meta']['target'])->toEqual(30000.0)
        ->and($suggestion['quantities'])->toBe([
            $catalog['staple']->id => 3,
        ]);
});

it('keeps the usual quantity when overorder is zero', function () {
    $catalog = suggestedCatalog();
    suggestedWeights(100, 0, 0, 0);

    $suggestion = suggestedQuantities($catalog['client']);

    expect($suggestion['quantities'])->toBe([
        $catalog['staple']->id => 2,
    ]);
});

it('fills the overorder with products bought together when that heuristic wins', function () {
    $catalog = suggestedCatalog();
    suggestedWeights(0, 100, 0, 50);

    $suggestion = suggestedQuantities($catalog['client']);

    expect($suggestion['quantities'][$catalog['staple']->id])->toBe(2)
        ->and($suggestion['quantities'][$catalog['together']->id])->toBe(1)
        ->and($suggestion['quantities'])->not->toHaveKey($catalog['bestseller']->id);
});

it('fills the overorder with bestsellers when that heuristic wins', function () {
    $catalog = suggestedCatalog();
    suggestedWeights(0, 0, 100, 50);

    $suggestion = suggestedQuantities($catalog['client']);

    expect($suggestion['quantities'][$catalog['staple']->id])->toBe(2)
        ->and($suggestion['quantities'][$catalog['bestseller']->id])->toBe(1)
        ->and($suggestion['quantities'])->not->toHaveKey($catalog['together']->id);
});

it('treats curated combinations as products bought together', function () {
    $client = User::factory()->create();
    $staple = suggestedProduct(10000);
    $companion = suggestedProduct(10000);

    foreach (range(1, 4) as $ignored) {
        suggestedOrderFor($client, [
            ['product' => $staple, 'quantity' => 2, 'price' => 10000],
        ]);
    }

    DB::table('product_combination')->insert([
        'product_id' => $companion->id,
        'parent_id' => $staple->id,
        'variation_item_id' => null,
        'price' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    suggestedWeights(0, 100, 0, 50);

    $suggestion = suggestedQuantities($client);

    expect($suggestion['quantities'][$staple->id])->toBe(2)
        ->and($suggestion['quantities'][$companion->id])->toBe(1);
});

it('shows suggested order and older orders on the client card', function () {
    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    $client = User::factory()->create([
        'name' => 'Tienda Del Parque',
        'document' => '901000111',
    ]);
    Zone::create([
        'user_id' => $client->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => (string) Carbon::now('America/Bogota')->dayOfWeek,
        'address' => 'Calle 10',
        'code' => 'RUT-SUG',
    ]);

    actingAs($seller)
        ->get('/ordenes?tab=mi-ruta&ruta=R100')
        ->assertOk()
        ->assertSee('Pedido sugerido')
        ->assertSee('Pedidos anteriores')
        ->assertSee('data-suggested-order', false)
        ->assertSee('data-client-orders', false);
});

it('loads the suggested order into the seller cart', function () {
    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    $client = User::factory()->create([
        'name' => 'Tienda Del Parque',
        'document' => '901000222',
    ]);
    Zone::create([
        'user_id' => $client->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => '1',
        'address' => 'Calle 10',
        'code' => 'RUT-CART',
    ]);

    $staple = suggestedProduct(10000);
    foreach (range(1, 4) as $ignored) {
        suggestedOrderFor($client, [
            ['product' => $staple, 'quantity' => 2, 'price' => 10000],
        ]);
    }
    suggestedWeights(100, 0, 0, 50);

    actingAs($seller);
    post(route('clients.suggested-order.store', $client))
        ->assertRedirect(route('cart'));

    $cart = session('cart');
    expect($cart)->toHaveCount(1)
        ->and($cart[0]['product_id'])->toBe($staple->id)
        ->and($cart[0]['quantity'])->toBe(3)
        ->and(session('user_id'))->toBe($client->id);

    get(route('cart'))
        ->assertOk()
        ->assertSee('data-suggested-order-banner', false)
        ->assertSee($staple->name)
        ->assertSee('Ticket promedio')
        ->assertSee('Volver a calcular');
});

it('lists only that clients older orders from the client card', function () {
    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    $client = User::factory()->create(['name' => 'Cliente Visible', 'document' => '901000333']);
    $other = User::factory()->create(['name' => 'Cliente Oculto', 'document' => '901000444']);

    $zone = Zone::create([
        'user_id' => $client->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => '1',
        'address' => 'Calle 1',
        'code' => 'RUT-HIST',
    ]);
    $otherZone = Zone::create([
        'user_id' => $other->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => '1',
        'address' => 'Calle 2',
        'code' => 'RUT-HIST-2',
    ]);

    $product = suggestedProduct(5000);
    suggestedOrderFor($client, [['product' => $product, 'quantity' => 1, 'price' => 5000]], $zone->id);
    suggestedOrderFor($other, [['product' => $product, 'quantity' => 1, 'price' => 5000]], $otherZone->id);

    actingAs($seller)
        ->get(route('clients.orders.index', [
            'tab' => 'orders',
            'client_id' => $client->id,
            'return_ruta' => 'R100',
        ]))
        ->assertOk()
        ->assertSee('data-client-orders-banner', false)
        ->assertSee('Cliente Visible')
        ->assertSee('Pedidos anteriores')
        ->assertDontSee('Cliente Oculto');
});

it('refuses a suggested order for a client outside the seller zone', function () {
    $seller = User::factory()->create(['zone' => '999']);
    $seller->assignRole('seller');
    $client = User::factory()->create();
    Zone::create([
        'user_id' => $client->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => '1',
        'address' => 'Calle 9',
        'code' => 'RUT-FUERA',
    ]);

    actingAs($seller)
        ->post(route('clients.suggested-order.store', $client))
        ->assertForbidden();

    actingAs($seller)
        ->get(route('clients.orders.index', ['tab' => 'orders', 'client_id' => $client->id]))
        ->assertForbidden();
});

it('hides the suggested order when the admin turns it off', function () {
    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');
    $client = User::factory()->create(['document' => '901000555']);
    Zone::create([
        'user_id' => $client->id,
        'zone' => '301',
        'route' => 'R100',
        'day' => (string) Carbon::now('America/Bogota')->dayOfWeek,
        'address' => 'Calle 5',
        'code' => 'RUT-OFF',
    ]);

    Setting::updateOrCreate(
        ['key' => 'suggested_order_enabled'],
        ['name' => 'Pedido sugerido activo', 'value' => '0', 'show' => false]
    );
    SuggestedOrderService::forgetConfigCache();

    actingAs($seller)
        ->get('/ordenes?tab=mi-ruta&ruta=R100')
        ->assertOk()
        ->assertDontSee('data-suggested-order', false)
        ->assertSee('Pedidos anteriores');

    actingAs($seller)
        ->from('/ordenes?tab=mi-ruta&ruta=R100')
        ->post(route('clients.suggested-order.store', $client))
        ->assertRedirect('/ordenes?tab=mi-ruta&ruta=R100')
        ->assertSessionHas('error');
});

it('saves suggested order settings from the admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    actingAs($admin)
        ->get(route('settings.pedido-sugerido'))
        ->assertOk()
        ->assertSee('Sobrepedido');

    actingAs($admin)
        ->post(route('settings.pedido-sugerido.update'), [
            'enabled' => '1',
            'weight_history' => 10,
            'weight_together' => 70,
            'weight_bestseller' => 20,
            'overorder_percent' => 25,
            'history_limit' => 8,
            'max_lines' => 12,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Setting::getByKey('suggested_order_weight_together'))->toBe('70')
        ->and(Setting::getByKey('suggested_order_overorder_percent'))->toBe('25');

    actingAs($admin)
        ->post(route('settings.pedido-sugerido.update'), [
            'enabled' => '1',
            'weight_history' => 0,
            'weight_together' => 0,
            'weight_bestseller' => 0,
            'overorder_percent' => 10,
            'history_limit' => 8,
            'max_lines' => 12,
        ])
        ->assertSessionHasErrors('weight_history');
});
