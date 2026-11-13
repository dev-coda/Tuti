<?php

use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Setting;
use App\Models\SupervisorRoute;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'seller', 'supervisor', 'supernumerario'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

function allProfilesClient(string $zoneCode = '301', string $route = 'R100'): array
{
    $client = User::factory()->create([
        'name' => 'Cliente Matriz',
        'document' => '901111222',
        'business_name' => 'Tienda Matriz',
        'email' => 'matriz@example.com',
    ]);
    $zone = Zone::create([
        'user_id' => $client->id,
        'zone' => $zoneCode,
        'route' => $route,
        'day' => (string) Zone::deliveryDayOfWeekForVisitOn(Carbon::now('America/Bogota')),
        'address' => 'Calle 1 # 2-3',
        'code' => 'RUT-MATRIZ-'.$zoneCode,
    ]);
    Order::create([
        'user_id' => $client->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 5000,
        'discount' => 0,
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'zone_id' => $zone->id,
        'zone_snapshot' => ['zone' => $zoneCode, 'route' => $route, 'code' => $zone->code],
    ]);

    return [$client, $zone];
}

function staffForProfile(string $profile): User
{
    $user = match ($profile) {
        'admin' => User::factory()->create(['zone' => null]),
        'supervisor' => User::factory()->create(['zone' => '301']),
        'seller' => User::factory()->create(['zone' => '301']),
        'supernumerario' => User::factory()->create(['zone' => null]),
    };
    $user->assignRole($profile);
    if ($profile === 'supervisor') {
        SupervisorRoute::create(['user_id' => $user->id, 'zone' => '301', 'route' => '']);
    }

    return $user;
}

dataset('card profiles', ['admin', 'supervisor', 'seller', 'supernumerario']);

it('renders the client card with all actions for every TUTI profile', function (string $profile) {
    allProfilesClient();
    $staff = staffForProfile($profile);
    $url = in_array($profile, ['admin', 'supernumerario'], true)
        ? '/ordenes?tab=mi-ruta&zona=301&ruta=R100'
        : '/ordenes?tab=mi-ruta&ruta=R100';

    actingAs($staff)->get($url)
        ->assertOk()
        ->assertSee('Cliente Matriz')
        ->assertSee('Pedidos anteriores')
        ->assertSee('Actualizar datos')
        ->assertSee('Agregar sucursal')
        ->assertSee('Crear pedido');
})->with('card profiles');

it('lets every TUTI profile open previous orders and card actions in coverage', function (string $profile) {
    [$client, $zone] = allProfilesClient();
    $staff = staffForProfile($profile);

    actingAs($staff)->get('/ordenes?tab=orders&all=1&client_id='.$client->id)
        ->assertOk()
        ->assertSee('Cliente Matriz');

    actingAs($staff)->get(route('client-data-updates.edit', $zone))
        ->assertOk()
        ->assertSee('Actualizar datos');

    actingAs($staff)->get(route('new-client.create', ['mode' => 'sucursal', 'document' => '901111222', 'return' => 'mi-ruta']))
        ->assertOk()
        ->assertSee('901111222');

    actingAs($staff)->post(route('seller.setclient'), [])
        ->assertSessionHasErrors(['document']);
})->with('card profiles');

it('blocks out-of-coverage client history and data update for zone-bound profiles', function (string $profile) {
    [$client, $zone] = allProfilesClient('999', 'R900');
    $staff = staffForProfile($profile);

    actingAs($staff)->get('/ordenes?tab=orders&all=1&client_id='.$client->id)
        ->assertForbidden();

    actingAs($staff)->get(route('client-data-updates.edit', $zone))
        ->assertForbidden();
})->with(['seller', 'supervisor']);

it('seller report line explanations are visible only to admins', function () {
    $seller = User::factory()->create(['zone' => '301']);
    $seller->assignRole('seller');

    $response = actingAs($seller)->getJson(route('api.seller.dashboard'));
    $response->assertOk();
    foreach ($response->json('sales_buckets') as $bucket) {
        expect($bucket['description'] ?? null)->toBeNull();
    }

    $admin = User::factory()->create(['zone' => '301']);
    $admin->assignRole(['seller', 'admin']);

    $response = actingAs($admin)->getJson(route('api.seller.dashboard'));
    $response->assertOk();
    foreach ($response->json('sales_buckets') as $bucket) {
        expect($bucket['description'] ?? '')->not->toBe('');
    }
    expect(collect($response->json('sales_buckets'))->firstWhere('label', 'Otros')['description'])
        ->toContain('sin clasificar')
        ->toContain('diferencia a favor vs ventas totales');
});

function bodegaProduct(): Product
{
    Setting::updateOrCreate(['key' => 'inventory_enabled'], ['name' => 'Inventario', 'value' => '1', 'show' => false]);
    Setting::updateOrCreate(['key' => 'global_minimum_inventory'], ['name' => 'Minimo', 'value' => '0', 'show' => false]);
    $tax = Tax::create(['name' => 'IVA matriz', 'tax' => 0]);
    $vendor = Vendor::create(['name' => 'Vendor matriz', 'slug' => 'vendor-matriz', 'minimum_purchase' => 0, 'active' => 1]);
    $brand = Brand::create(['name' => 'Marca matriz', 'slug' => 'marca-matriz', 'vendor_id' => $vendor->id, 'active' => 1]);
    $product = Product::create([
        'name' => 'Pila Matriz', 'description' => 'd', 'short_description' => 'd', 'sku' => 'SKU-MATRIZ',
        'slug' => 'pila-matriz', 'active' => 1, 'price' => 1000, 'delivery_days' => 1, 'discount' => 0,
        'quantity_min' => 1, 'quantity_max' => 100, 'step' => 1, 'tax_id' => $tax->id, 'brand_id' => $brand->id, 'package_quantity' => 1,
    ]);
    ProductInventory::create(['product_id' => $product->id, 'bodega_code' => 'CU00', 'available' => 80, 'physical' => 80, 'reserved' => 0]);
    ProductInventory::create(['product_id' => $product->id, 'bodega_code' => 'MDTAT', 'available' => 12, 'physical' => 12, 'reserved' => 0]);

    return $product;
}

it('staff and admin viewing a selected Cucuta client see CU00 stock, not their own city stock', function (string $profile) {
    $product = bodegaProduct();
    [$client] = allProfilesClient('409', 'R409');
    $staff = staffForProfile($profile);
    if ($profile === 'seller') {
        $staff->update(['zone' => '102']);
    }

    actingAs($staff)->withSession(['user_id' => $client->id])
        ->get(route('product', $product->slug))
        ->assertOk()
        ->assertSee('80 unidades')
        ->assertDontSee('12 unidades');
})->with(['admin', 'seller']);

it('guest with no client context uses the documented MDTAT default', function () {
    $product = bodegaProduct();

    // Guests see availability without a count; the underlying bodega is MDTAT.
    $this->get(route('product', $product->slug))
        ->assertOk()
        ->assertSee('Disponible')
        ->assertDontSee('80 unidades');
});
