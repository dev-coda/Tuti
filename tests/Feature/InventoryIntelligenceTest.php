<?php

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->withoutVite();
});

function inventoryAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function inventoryClient(): User
{
    return User::factory()->create();
}

it('hides inventory control from guests and non admins', function () {
    get(route('admin.inventory.index'))->assertRedirect();

    actingAs(inventoryClient())
        ->get(route('admin.inventory.index'))
        ->assertRedirect();
});

it('shows warehouse stock and excludes products opted out of inventory', function () {
    Setting::create([
        'key' => 'global_minimum_inventory',
        'name' => 'Mínimo',
        'value' => '5',
        'show' => false,
    ]);

    $fast = Product::factory()->create(['name' => 'Marcador rápido', 'sku' => 'FAST-1', 'safety_stock' => 0]);
    $quiet = Product::factory()->create(['name' => 'Caja quieta', 'sku' => 'DEAD-1', 'safety_stock' => 0]);
    $ignored = Product::factory()->create(['name' => 'Servicio suelto', 'sku' => 'OPT-1', 'inventory_opt_out' => true]);

    ProductInventory::create(['product_id' => $fast->id, 'bodega_code' => 'MDTAT', 'available' => 2, 'physical' => 4, 'reserved' => 2]);
    ProductInventory::create(['product_id' => $quiet->id, 'bodega_code' => 'MDTAT', 'available' => 80, 'physical' => 80, 'reserved' => 0]);
    ProductInventory::create(['product_id' => $ignored->id, 'bodega_code' => 'MDTAT', 'available' => 1, 'physical' => 1, 'reserved' => 0]);

    actingAs(inventoryAdmin());

    get(route('admin.inventory.index'))
        ->assertOk()
        ->assertSee('Marcador rápido')
        ->assertSee('FAST-1')
        ->assertSee('Caja quieta')
        ->assertSee('Bajo piso')
        ->assertDontSee('OPT-1');

    get(route('admin.inventory.index', ['status' => 'stockout']))
        ->assertOk()
        ->assertDontSee('FAST-1');
});

it('suggests replenishment from recent sales and ignores bonifications', function () {
    Setting::create([
        'key' => 'global_minimum_inventory',
        'name' => 'Mínimo',
        'value' => '5',
        'show' => false,
    ]);

    $fast = Product::factory()->create(['name' => 'Marcador rápido', 'sku' => 'FAST-1', 'safety_stock' => 0]);
    $quiet = Product::factory()->create(['name' => 'Caja quieta', 'sku' => 'DEAD-1', 'safety_stock' => 0]);
    ProductInventory::create(['product_id' => $fast->id, 'bodega_code' => 'MDTAT', 'available' => 2, 'physical' => 2, 'reserved' => 0]);
    ProductInventory::create(['product_id' => $quiet->id, 'bodega_code' => 'MDTAT', 'available' => 80, 'physical' => 80, 'reserved' => 0]);

    $client = inventoryClient();
    $order = Order::create([
        'user_id' => $client->id,
        'status_id' => Order::STATUS_PROCESSED,
        'total' => 1000,
        'discount' => 0,
    ]);
    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $fast->id,
        'quantity' => 60,
        'price' => 1000,
        'discount' => 0,
        'is_bonification' => false,
    ]);
    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $fast->id,
        'quantity' => 500,
        'price' => 0,
        'discount' => 0,
        'is_bonification' => true,
    ]);

    actingAs(inventoryAdmin());

    get(route('admin.inventory.intelligence'))
        ->assertOk()
        ->assertSee('Reposición sugerida')
        ->assertSee('FAST-1')
        ->assertSee('40')
        ->assertSee('DEAD-1')
        ->assertSee('Sin rotación');
});

it('answers inventory questions from local tools and can call the model', function () {
    Setting::create([
        'key' => 'global_minimum_inventory',
        'name' => 'Mínimo',
        'value' => '5',
        'show' => false,
    ]);

    $fast = Product::factory()->create(['name' => 'Marcador rápido', 'sku' => 'FAST-1', 'safety_stock' => 0]);
    ProductInventory::create(['product_id' => $fast->id, 'bodega_code' => 'MDTAT', 'available' => 2, 'physical' => 2, 'reserved' => 0]);

    $admin = inventoryAdmin();
    actingAs($admin);

    $reply = postJson(route('admin.inventory.ask'), ['message' => 'productos bajo el piso'])
        ->assertOk()
        ->assertJsonPath('mode', 'local')
        ->json('reply');

    expect($reply)->toContain('FAST-1')->toContain('2 disponibles');

    config(['services.openai.key' => 'test-key', 'services.openai.model' => 'gpt-4o-mini']);
    Http::fake([
        'api.openai.com/*' => Http::sequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'buscar_producto',
                                'arguments' => '{"consulta":"FAST-1"}',
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'FAST-1 tiene 2 unidades en MDTAT.',
                    ],
                ]],
            ]),
    ]);

    postJson(route('admin.inventory.ask'), ['message' => 'stock de FAST-1'])
        ->assertOk()
        ->assertJsonPath('mode', 'model')
        ->assertJsonPath('reply', 'FAST-1 tiene 2 unidades en MDTAT.');

    Http::assertSentCount(2);

    delete(route('admin.inventory.agent.clear'))
        ->assertRedirect(route('admin.inventory.agent'));
});
