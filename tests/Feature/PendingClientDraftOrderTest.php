<?php

use App\Jobs\ProcessOrderAsync;
use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Services\DraftOrderReconciliationService;
use App\Services\PendingClientProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);

    Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '0', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'force_delivery_date_enabled'],
        ['name' => 'Force delivery', 'value' => '1', 'show' => false]
    );
});

function createSellerClientProductForDraftTest(): array
{
    $seller = User::factory()->create();
    $seller->assignRole('seller');

    $client = User::factory()->create([
        'document' => '900123456',
        'client_status' => User::CLIENT_STATUS_PENDIENTE,
    ]);

    $zone = Zone::create([
        'user_id' => $client->id,
        'route' => '1234',
        'zone' => '933',
        'day' => 'LUNES',
        'address' => 'Calle Test 1',
        'code' => null,
    ]);

    $tax = Tax::create(['name' => 'IVA-DRAFT-'.uniqid(), 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'V-DRAFT-'.uniqid(),
        'slug' => 'v-draft-'.uniqid(),
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'B-DRAFT-'.uniqid(),
        'slug' => 'b-draft-'.uniqid(),
        'vendor_id' => $vendor->id,
    ]);
    $product = Product::create([
        'name' => 'Product Draft Test',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'SKU-DRAFT-'.strtoupper(substr(uniqid(), -6)),
        'slug' => 'p-draft-'.uniqid(),
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

    return compact('seller', 'client', 'zone', 'product');
}

it('creates draft order for pending client checkout without dispatching transmission', function () {
    Bus::fake([ProcessOrderAsync::class]);

    ['seller' => $seller, 'client' => $client, 'zone' => $zone, 'product' => $product] = createSellerClientProductForDraftTest();

    $this->actingAs($seller)
        ->withSession(['user_id' => $client->id, 'cart' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => 10_000,
                'discount' => 0,
            ],
        ]])
        ->post(route('cart.process'), [
            'zone_id' => $zone->id,
            'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
            'observations' => '',
        ])
        ->assertRedirect();

    $order = Order::query()->latest('id')->first();

    expect($order)->not->toBeNull()
        ->and($order->status_id)->toBe(Order::STATUS_DRAFT)
        ->and($order->user_id)->toBe($client->id)
        ->and($order->seller_id)->toBe($seller->id);

    Bus::assertNotDispatched(ProcessOrderAsync::class);
});

it('provisions pending prospect when rutero is missing', function () {
    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft token', 'value' => 'test-token', 'show' => false]
    );

    Http::fake([
        '*' => Http::response('<?xml version="1.0"?><Envelope></Envelope>', 200),
    ]);

    $seller = User::factory()->create();
    $seller->assignRole('seller');

    $this->actingAs($seller)
        ->post(route('seller.setclient'), [
            'document' => 800111222,
            'zone' => 933,
        ])
        ->assertRedirect(route('cart'));

    $client = User::query()->where('document', '800111222')->first();

    expect($client)->not->toBeNull()
        ->and($client->client_status)->toBe(User::CLIENT_STATUS_PENDIENTE)
        ->and($client->zones)->toHaveCount(1);
});

it('promotes pending client and queues draft after rutero code is available', function () {
    Bus::fake([ProcessOrderAsync::class]);

    $client = User::factory()->create([
        'document' => '800333444',
        'client_status' => User::CLIENT_STATUS_PENDIENTE,
    ]);

    $zone = Zone::create([
        'user_id' => $client->id,
        'route' => '1001',
        'zone' => '933',
        'day' => 'LUNES',
        'address' => 'Addr',
        'code' => null,
    ]);

    $tax = Tax::create(['name' => 'IVA-RECON-'.uniqid(), 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'V-RECON-'.uniqid(),
        'slug' => 'v-recon-'.uniqid(),
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'B-RECON-'.uniqid(),
        'slug' => 'b-recon-'.uniqid(),
        'vendor_id' => $vendor->id,
    ]);
    $product = Product::create([
        'name' => 'Product Recon',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'SKU-RECON-'.strtoupper(substr(uniqid(), -6)),
        'slug' => 'p-recon-'.uniqid(),
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

    $order = Order::create([
        'user_id' => $client->id,
        'total' => 10000,
        'discount' => 0,
        'status_id' => Order::STATUS_DRAFT,
        'zone_id' => $zone->id,
        'zone_snapshot' => [
            'id' => $zone->id,
            'code' => null,
            'route' => $zone->route,
            'zone' => $zone->zone,
            'day' => $zone->day,
            'address' => $zone->address,
        ],
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'shipping_provider' => Order::SHIPPING_PROVIDER_TRONEX,
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 10_000,
        'discount' => 0,
    ]);

    $zone->update(['code' => 'CUST-12345']);

    $service = app(DraftOrderReconciliationService::class);
    expect($service->promoteUserIfReady($client->fresh(['zones'])))->toBeTrue();

    $client->refresh();
    expect($client->client_status)->toBe(User::CLIENT_STATUS_CLIENTE)
        ->and($client->status_id)->toBe(User::ACTIVE);

    $result = $service->attemptTransmitDraft($order->fresh());
    expect($result)->toBe('drafts_queued');

    $order->refresh();
    expect($order->status_id)->toBe(Order::STATUS_PENDING);

    Bus::assertDispatched(ProcessOrderAsync::class);
});

it('provisions local user from new client payload', function () {
    $user = app(PendingClientProvisioningService::class)->provisionFromNewClient([
        'Documento' => '900555666',
        'RazonSocial' => 'Razon Test SAS',
        'NombreNegocio' => 'Negocio Test',
        'Zona' => '933',
        'RutaZonaVentas' => '1234',
        'DiaRecorrido' => 'LUNES',
        'Direccion' => 'Calle 1',
        'Barrio' => 'Centro',
    ]);

    expect($user->document)->toBe('900555666')
        ->and($user->client_status)->toBe(User::CLIENT_STATUS_PENDIENTE)
        ->and($user->zones)->toHaveCount(1);
});

it('creates prospecto for self-created client payload and blocks draft processing', function () {
    Bus::fake([ProcessOrderAsync::class]);

    $prospecto = app(PendingClientProvisioningService::class)->provisionFromNewClient([
        'Documento' => '911000111',
        'RazonSocial' => 'Self Service SAS',
        'NombreNegocio' => 'Tienda Self',
        'Direccion' => 'Calle 10',
        'Barrio' => 'Centro',
    ], null, User::CLIENT_STATUS_PROSPECTO);

    expect($prospecto->client_status)->toBe(User::CLIENT_STATUS_PROSPECTO)
        ->and($prospecto->zones)->toHaveCount(1);

    $tax = Tax::create(['name' => 'IVA-PROSP-'.uniqid(), 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'V-PROSP-'.uniqid(),
        'slug' => 'v-prosp-'.uniqid(),
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'B-PROSP-'.uniqid(),
        'slug' => 'b-prosp-'.uniqid(),
        'vendor_id' => $vendor->id,
    ]);
    $product = Product::create([
        'name' => 'Product Prospecto',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'SKU-PROSP-'.strtoupper(substr(uniqid(), -6)),
        'slug' => 'p-prosp-'.uniqid(),
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

    $zone = $prospecto->zones()->first();
    $order = Order::create([
        'user_id' => $prospecto->id,
        'total' => 10000,
        'discount' => 0,
        'status_id' => Order::STATUS_DRAFT,
        'zone_id' => $zone?->id,
        'zone_snapshot' => [
            'id' => $zone?->id,
            'code' => null,
            'route' => $zone?->route,
            'zone' => $zone?->zone,
            'day' => $zone?->day,
            'address' => $zone?->address,
        ],
        'delivery_method' => Order::DELIVERY_METHOD_TRONEX,
        'shipping_provider' => Order::SHIPPING_PROVIDER_TRONEX,
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => 10_000,
        'discount' => 0,
    ]);

    $service = app(DraftOrderReconciliationService::class);
    $result = $service->attemptTransmitDraft($order->fresh());

    expect($result)->toBe('drafts_failed');
    $order->refresh();
    expect($order->status_id)->toBe(Order::STATUS_DRAFT)
        ->and($order->draft_reconciliation_note)->toContain('Prospecto');

    Bus::assertNotDispatched(ProcessOrderAsync::class);
});
