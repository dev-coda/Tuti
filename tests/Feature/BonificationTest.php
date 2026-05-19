<?php

use App\Jobs\SyncProductInventory;
use App\Models\Bonification;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Models\Variation;
use App\Models\VariationItem;
use App\Models\Vendor;
use App\Models\Zone;
use App\Models\ZoneWarehouse;
use App\Repositories\OrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/**
 * Gift product required for bonification validation (product_id, max, etc.).
 */
function giftProductForBonificationCrud(): Product
{
    $tax = Tax::create(['name' => 'IVA-BONIF-'.uniqid(), 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'V-Bonif-'.uniqid(),
        'slug' => 'v-bonif-'.uniqid(),
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'B-Bonif-'.uniqid(),
        'slug' => 'b-bonif-'.uniqid(),
        'vendor_id' => $vendor->id,
    ]);

    return Product::create([
        'name' => 'Gift bonif CRUD',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'SKU-BONIF-'.uniqid(),
        'slug' => 'gift-bonif-'.uniqid(),
        'active' => 1,
        'price' => 100,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);
}

beforeEach(function () {
    $user = User::factory()->create();
    Role::create([
        'name' => 'admin',
        'guard_name' => 'web',
    ]);
    $user->assignRole('admin');
});

it('user not logged cannot access to bonification page', function () {
    get('/bonifications')
        ->assertRedirect('/login');
});

it('user logged can access to bonification page', function () {

    actingAs(User::first())
        ->get('/bonifications')
        ->assertStatus(200);
});

it('user logged can access to create bonification page', function () {

    actingAs(User::first())
        ->get('/bonifications/create')
        ->assertStatus(200);
});

it('user logged can create bonification', function () {
    $gift = giftProductForBonificationCrud();

    $response = actingAs(User::first())
        ->post('/bonifications', [
            'name' => 'Pague 10 lleve 2',
            'buy' => 10,
            'get' => 2,
            'max' => 10,
            'product_id' => $gift->id,
        ]);

    $bonification = Bonification::latest('id')->first();
    $response->assertRedirect(route('bonifications.edit', $bonification))
        ->assertSessionHas('success', 'Bonificación creada, agregue los productos');
});

it('user logged can access to edit bonification page', function () {

    $user = User::first();

    $gift = giftProductForBonificationCrud();
    $bonification = Bonification::create([
        'name' => 'Pague 10 lleve 2',
        'buy' => 10,
        'get' => 2,
        'max' => 10,
        'product_id' => $gift->id,
    ]);

    actingAs($user)
        ->get("/bonifications/{$bonification->id}/edit")
        ->assertStatus(200);
});

it('user logged can access to edit bonification', function () {

    $user = User::first();

    $gift = giftProductForBonificationCrud();
    $bonification = Bonification::create([
        'name' => 'Pague 10 lleve 2',
        'buy' => 10,
        'get' => 2,
        'max' => 10,
        'product_id' => $gift->id,
    ]);

    actingAs($user)
        ->put("/bonifications/{$bonification->id}", [
            'name' => 'Pague 20 lleve 4',
            'buy' => 20,
            'get' => 4,
            'max' => 20,
            'product_id' => $gift->id,
        ])
        ->assertRedirect('/bonifications')
        ->assertSessionHas('success', 'Bonificación actualizada');
});

it('user logged can delete bonification', function () {

    $user = User::first();

    $gift = giftProductForBonificationCrud();
    $bonification = Bonification::create([
        'name' => 'Pague 10 lleve 2',
        'buy' => 10,
        'get' => 2,
        'max' => 10,
        'product_id' => $gift->id,
    ]);

    actingAs($user)
        ->delete("/bonifications/{$bonification->id}")
        ->assertRedirect('/bonifications')
        ->assertSessionHas('success', 'La bonificacion se ha eliminado correctamente');
});

it('multiple bonifications can be applied to a single order', function () {
    // This test ensures that when a product qualifies for multiple bonifications,
    // all applicable bonifications are applied, not just the first one

    $user = User::first();

    // Create a tax for products
    $tax = \App\Models\Tax::create([
        'name' => 'IVA',
        'tax' => 0,
    ]);

    // Create a brand for the products
    $vendor = \App\Models\Vendor::create([
        'name' => 'Test Vendor',
        'slug' => 'test-vendor',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);

    $brand = \App\Models\Brand::create([
        'name' => 'Test Brand',
        'slug' => 'test-brand',
        'vendor_id' => $vendor->id,
    ]);

    // Create two gift products that will be given as bonifications
    $giftProduct1 = \App\Models\Product::create([
        'name' => 'Gift Product 1',
        'description' => 'Gift 1',
        'short_description' => 'Gift 1',
        'sku' => 'GIFT001',
        'slug' => 'gift-product-1',
        'active' => 1,
        'price' => 0,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    $giftProduct2 = \App\Models\Product::create([
        'name' => 'Gift Product 2',
        'description' => 'Gift 2',
        'short_description' => 'Gift 2',
        'sku' => 'GIFT002',
        'slug' => 'gift-product-2',
        'active' => 1,
        'price' => 0,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    // Create a regular product that will trigger bonifications
    $product = \App\Models\Product::create([
        'name' => 'Test Product',
        'description' => 'Test Product',
        'short_description' => 'Test Product',
        'sku' => 'TEST001',
        'slug' => 'test-product',
        'active' => 1,
        'price' => 100,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    // Create two different bonifications
    $bonification1 = Bonification::create([
        'name' => 'Buy 10 Get 1 Free',
        'buy' => 10,
        'get' => 1,
        'product_id' => $giftProduct1->id,
        'max' => 10,
    ]);

    $bonification2 = Bonification::create([
        'name' => 'Buy 5 Get 1 Free',
        'buy' => 5,
        'get' => 1,
        'product_id' => $giftProduct2->id,
        'max' => 5,
    ]);

    // Associate the product with BOTH bonifications
    $product->bonifications()->attach([$bonification1->id, $bonification2->id]);

    // Create a zone owned by the user (User::zones() is hasMany)
    $zone = \App\Models\Zone::create([
        'route' => '1',
        'zone' => 'Test Zone',
        'day' => 'Monday',
        'address' => 'Test Address',
        'code' => 'TEST01',
        'user_id' => $user->id,
    ]);

    // Disable inventory management for this test
    \App\Models\Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '0', 'show' => false]
    );

    // Add product to cart (buying 10 units should trigger both bonifications)
    session()->put('cart', [
        [
            'product_id' => $product->id,
            'quantity' => 10,
            'variation_id' => null,
        ],
    ]);

    // Process the order (POST /carrito — see routes/web.php)
    actingAs($user)
        ->post(route('cart.process'), [
            'zone_id' => $zone->id,
            'observations' => 'Test order with multiple bonifications',
        ]);

    // Verify that the order was created
    $order = \App\Models\Order::latest()->first();
    expect($order)->not->toBeNull();

    // Verify that BOTH bonifications were applied
    $bonifications = $order->bonifications;
    expect($bonifications->count())->toBe(2);

    // Verify bonification 1 (Buy 10 Get 1): 10 items / 10 * 1 = 1 free item
    $bonif1 = $bonifications->where('bonification_id', $bonification1->id)->first();
    expect($bonif1)->not->toBeNull();
    expect($bonif1->quantity)->toBe(1);
    expect($bonif1->product_id)->toBe($giftProduct1->id);

    // Verify bonification 2 (Buy 5 Get 1): 10 items / 5 * 1 = 2 free items
    $bonif2 = $bonifications->where('bonification_id', $bonification2->id)->first();
    expect($bonif2)->not->toBeNull();
    expect($bonif2->quantity)->toBe(2);
    expect($bonif2->product_id)->toBe($giftProduct2->id);
});

it('creates separate bonification rows when different trigger products share the same gift product', function () {
    $user = User::first();

    $tax = \App\Models\Tax::create([
        'name' => 'IVA',
        'tax' => 0,
    ]);

    $vendor = \App\Models\Vendor::create([
        'name' => 'Vendor Shared Gift',
        'slug' => 'vendor-shared-gift',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);

    $brand = \App\Models\Brand::create([
        'name' => 'Brand Shared Gift',
        'slug' => 'brand-shared-gift',
        'vendor_id' => $vendor->id,
    ]);

    $sharedGift = \App\Models\Product::create([
        'name' => 'Shared Gift',
        'description' => 'Gift',
        'short_description' => 'Gift',
        'sku' => 'GIFT-SHARED',
        'slug' => 'shared-gift',
        'active' => 1,
        'price' => 0,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    $triggerA = \App\Models\Product::create([
        'name' => 'Trigger A',
        'description' => 'A',
        'short_description' => 'A',
        'sku' => 'TRIG-A',
        'slug' => 'trigger-a',
        'active' => 1,
        'price' => 100,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    $triggerB = \App\Models\Product::create([
        'name' => 'Trigger B',
        'description' => 'B',
        'short_description' => 'B',
        'sku' => 'TRIG-B',
        'slug' => 'trigger-b',
        'active' => 1,
        'price' => 100,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    $bonificationA = Bonification::create([
        'name' => 'Rule A',
        'buy' => 10,
        'get' => 1,
        'product_id' => $sharedGift->id,
        'max' => 10,
    ]);

    $bonificationB = Bonification::create([
        'name' => 'Rule B',
        'buy' => 10,
        'get' => 1,
        'product_id' => $sharedGift->id,
        'max' => 10,
    ]);

    $triggerA->bonifications()->attach($bonificationA->id);
    $triggerB->bonifications()->attach($bonificationB->id);

    $zone = \App\Models\Zone::create([
        'route' => '1',
        'zone' => 'Z',
        'day' => 'Monday',
        'address' => 'Addr',
        'code' => 'Z01',
        'user_id' => $user->id,
    ]);

    \App\Models\Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '0', 'show' => false]
    );

    session()->put('cart', [
        ['product_id' => $triggerA->id, 'quantity' => 10, 'variation_id' => null],
        ['product_id' => $triggerB->id, 'quantity' => 10, 'variation_id' => null],
    ]);

    actingAs($user)
        ->post(route('cart.process'), [
            'zone_id' => $zone->id,
            'observations' => 'Shared gift bonifications',
        ]);

    $order = \App\Models\Order::latest()->first();
    expect($order)->not->toBeNull();

    $rows = $order->bonifications;
    expect($rows->count())->toBe(2);
    expect($rows->where('bonification_id', $bonificationA->id)->first()->quantity)->toBe(1);
    expect($rows->where('bonification_id', $bonificationB->id)->first()->quantity)->toBe(1);
    expect($rows->pluck('product_id')->unique()->count())->toBe(1);
    expect($rows->first()->product_id)->toBe($sharedGift->id);
});

it('blocks the order when stock cannot cover all gifted items involved', function () {
    $user = User::first();

    $tax = Tax::create([
        'name' => 'IVA stock',
        'tax' => 0,
    ]);

    $vendor = Vendor::create([
        'name' => 'Vendor stock',
        'slug' => 'vendor-stock',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);

    $brand = Brand::create([
        'name' => 'Brand stock',
        'slug' => 'brand-stock',
        'vendor_id' => $vendor->id,
    ]);

    $gift = Product::create([
        'name' => 'Gift constrained',
        'description' => 'Gift constrained',
        'short_description' => 'Gift constrained',
        'sku' => 'GIFT-CONSTRAINED',
        'slug' => 'gift-constrained',
        'active' => 1,
        'price' => 0,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
        'safety_stock' => 0,
        'inventory_opt_out' => 0,
    ]);

    $trigger = Product::create([
        'name' => 'Trigger constrained',
        'description' => 'Trigger constrained',
        'short_description' => 'Trigger constrained',
        'sku' => 'TRIGGER-CONSTRAINED',
        'slug' => 'trigger-constrained',
        'active' => 1,
        'price' => 100,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
        'safety_stock' => 0,
        'inventory_opt_out' => 0,
    ]);

    $bonificationA = Bonification::create([
        'name' => 'Rule constrained A',
        'buy' => 1,
        'get' => 3,
        'product_id' => $gift->id,
        'max' => 100,
    ]);
    $bonificationB = Bonification::create([
        'name' => 'Rule constrained B',
        'buy' => 1,
        'get' => 4,
        'product_id' => $gift->id,
        'max' => 100,
    ]);
    $trigger->bonifications()->attach([$bonificationA->id, $bonificationB->id]);

    $zone = \App\Models\Zone::create([
        'route' => '1',
        'zone' => '933',
        'day' => 'Monday',
        'address' => 'Addr',
        'code' => 'Z-STOCK',
        'user_id' => $user->id,
    ]);

    Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '1', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'global_minimum_inventory'],
        ['name' => 'Global minimum inventory', 'value' => '5', 'show' => false]
    );
    ZoneWarehouse::updateOrCreate(
        ['zone_code' => '933'],
        ['bodega_code' => 'BOD-1']
    );

    // Trigger product has enough stock to purchase 1 unit.
    ProductInventory::create([
        'product_id' => $trigger->id,
        'bodega_code' => 'BOD-1',
        'available' => 20,
        'physical' => 20,
        'reserved' => 0,
    ]);

    // Gift product available=11 with global minimum=5 => max givable=6.
    // Combined demand from both bonifications is 7, so BOTH should be skipped.
    ProductInventory::create([
        'product_id' => $gift->id,
        'bodega_code' => 'BOD-1',
        'available' => 11,
        'physical' => 11,
        'reserved' => 0,
    ]);

    session()->put('cart', [
        ['product_id' => $trigger->id, 'quantity' => 1, 'variation_id' => null],
    ]);

    $response = actingAs($user)->post(route('cart.process'), [
        'zone_id' => $zone->id,
        'observations' => 'Constrained gift stock',
    ]);

    $response->assertSessionHas('error');
    expect((string) session('error'))->toContain('Inventario insuficiente');

    expect(\App\Models\Order::query()->count())->toBe(0);

    $giftInventoryAfter = ProductInventory::where('product_id', $gift->id)
        ->where('bodega_code', 'BOD-1')
        ->first();
    expect((int) $giftInventoryAfter->available)->toBe(11);
});

function checkoutInventoryProduct(Brand $brand, Tax $tax, string $name, string $sku, int $price = 100, ?int $variationId = null): Product
{
    return Product::create([
        'name' => $name,
        'description' => $name,
        'short_description' => $name,
        'sku' => $sku,
        'slug' => str($name.'-'.uniqid())->slug()->toString(),
        'active' => 1,
        'price' => $price,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
        'variation_id' => $variationId,
        'safety_stock' => 0,
        'inventory_opt_out' => 0,
    ]);
}

function configureBonificationInventoryCheckout(User $user): App\Models\Zone
{
    Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '1', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'global_minimum_inventory'],
        ['name' => 'Global minimum inventory', 'value' => '5', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'min_amount'],
        ['name' => 'Min amount', 'value' => '0', 'show' => false]
    );
    ZoneWarehouse::updateOrCreate(
        ['zone_code' => '933'],
        ['bodega_code' => 'BOD-1']
    );

    return \App\Models\Zone::create([
        'route' => '1',
        'zone' => '933',
        'day' => 'Monday',
        'address' => 'Addr',
        'code' => 'Z-VAR-'.uniqid(),
        'user_id' => $user->id,
    ]);
}

function bonificationInventorySyncSoapResponse(array $items): string
{
    $rows = collect($items)->map(function (array $item): string {
        return '<a:ListItemExists>'
            .'<a:ItemId>'.htmlspecialchars($item['sku']).'</a:ItemId>'
            .'<a:AvailPhysical>'.(int) $item['available'].'</a:AvailPhysical>'
            .'<a:PhysicalInvent>'.(int) ($item['physical'] ?? $item['available']).'</a:PhysicalInvent>'
            .'<a:ReservPhysical>'.(int) ($item['reserved'] ?? 0).'</a:ReservPhysical>'
            .'<a:WMSLocation>DISPONIBLE</a:WMSLocation>'
            .'</a:ListItemExists>';
    })->implode('');

    return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:a="http://schemas.datacontract.org/2004/07/Dynamics.AX.Application">'
        .'<s:Body>'
        .'<obtenerExistenciaDeInventarioEspecificaResponse>'
        .'<result>'
        .'<a:obtenerExistenciaDeInventarioEspecificaResult>'
        .$rows
        .'</a:obtenerExistenciaDeInventarioEspecificaResult>'
        .'</result>'
        .'</obtenerExistenciaDeInventarioEspecificaResponse>'
        .'</s:Body>'
        .'</s:Envelope>';
}

function successfulPresalesSoapResponse(): string
{
    return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/" xmlns:a="http://schemas.datacontract.org/2004/07/Dynamics.AX.Application">'
        .'<s:Body>'
        .'<PreSaslesProcessResponse>'
        .'<result>'
        .'<a:PreSaslesProcessResult>OK</a:PreSaslesProcessResult>'
        .'</result>'
        .'</PreSaslesProcessResponse>'
        .'</s:Body>'
        .'</s:Envelope>';
}

it('transmits separate order and bonification XMLs with rule-correct variation SKUs and quantities', function () {
    $user = User::first();
    $zone = configureBonificationInventoryCheckout($user);
    config(['microsoft.resource' => 'https://dynamics.test']);
    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft token', 'value' => 'token', 'show' => false]
    );

    $tax = Tax::create(['name' => 'IVA xml bonif', 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Vendor xml bonif',
        'slug' => 'vendor-xml-bonif',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Brand xml bonif',
        'slug' => 'brand-xml-bonif',
        'vendor_id' => $vendor->id,
    ]);

    $variation = Variation::create(['name' => 'Presentacion xml']);
    $first = VariationItem::create(['name' => 'Unidad', 'variation_id' => $variation->id]);
    $giftVariation = VariationItem::create(['name' => 'Caja', 'variation_id' => $variation->id]);
    $giftParent = checkoutInventoryProduct($brand, $tax, 'Gift XML parent dummy', '', 0, $variation->id);
    $giftParent->items()->sync([
        $first->id => ['price' => 0, 'enabled' => 1, 'sku' => ''],
        $giftVariation->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-XML-VARIATION-SKU'],
    ]);

    $trigger = checkoutInventoryProduct($brand, $tax, 'Trigger XML bonif', 'TRIGGER-XML-SKU', 100);
    $bonification = Bonification::create([
        'name' => 'XML rule capped variation gift',
        'buy' => 2,
        'get' => 3,
        'product_id' => $giftParent->id,
        'max' => 5,
    ]);
    $trigger->bonifications()->attach($bonification->id);

    ProductInventory::create([
        'product_id' => $trigger->id,
        'bodega_code' => 'BOD-1',
        'available' => 20,
        'physical' => 20,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $giftParent->id,
        'bodega_code' => 'BOD-1',
        'available' => 0,
        'physical' => 0,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $giftParent->id,
        'variation_item_id' => $giftVariation->id,
        'source_sku' => 'GIFT-XML-VARIATION-SKU',
        'bodega_code' => 'BOD-1',
        'available' => 20,
        'physical' => 20,
        'reserved' => 0,
    ]);

    session()->put('cart', [
        ['product_id' => $trigger->id, 'quantity' => 4, 'variation_id' => null],
    ]);

    actingAs($user)->post(route('cart.process'), [
        'zone_id' => $zone->id,
        'observations' => 'Two XML bonification check',
    ])->assertRedirect();

    $order = Order::latest('id')->with(['zone', 'user', 'products', 'bonifications'])->first();
    expect($order)->not->toBeNull()
        ->and($order->products()->count())->toBe(1)
        ->and($order->bonifications()->count())->toBe(1);

    $bonificationRow = $order->bonifications()->first();
    expect($bonificationRow->product_id)->toBe($giftParent->id)
        ->and($bonificationRow->variation_item_id)->toBe($giftVariation->id)
        ->and($bonificationRow->quantity)->toBe(5); // floor(4 / 2 * 3) = 6, capped by max=5

    Http::fake([
        '*' => Http::response(successfulPresalesSoapResponse(), 200),
    ]);

    OrderRepository::presalesOrder($order);

    Http::assertSentCount(2);
    $bodies = collect(Http::recorded())
        ->map(fn (array $record) => $record[0]->body())
        ->values();

    $orderXml = $bodies[0];
    $giftXml = $bodies[1];

    expect($orderXml)->toContain('<dyn:TRO_E_obsequio>0</dyn:TRO_E_obsequio>')
        ->and($orderXml)->toContain('<dyn:itemId>TRIGGER-XML-SKU</dyn:itemId>')
        ->and($orderXml)->toContain('<dyn:qty>4</dyn:qty>')
        ->and($orderXml)->not->toContain('GIFT-XML-VARIATION-SKU')
        ->and($giftXml)->toContain('<dyn:TRO_E_obsequio>1</dyn:TRO_E_obsequio>')
        ->and($giftXml)->toContain('<dyn:itemId>GIFT-XML-VARIATION-SKU</dyn:itemId>')
        ->and($giftXml)->toContain('<dyn:qty>5</dyn:qty>')
        ->and($giftXml)->toContain('<dyn:qtyCust>5</dyn:qtyCust>')
        ->and($giftXml)->toContain('<dyn:unitPrice>0</dyn:unitPrice>')
        ->and($giftXml)->not->toContain('<dyn:itemId>TRIGGER-XML-SKU</dyn:itemId>');
});

it('syncs variation stock automatically before applying a bonification gift', function () {
    $user = User::first();
    $zone = configureBonificationInventoryCheckout($user);
    config(['microsoft.resource' => 'https://dynamics.test']);
    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft token', 'value' => 'token', 'show' => false]
    );

    $tax = Tax::create(['name' => 'IVA synced var gift', 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Vendor synced var gift',
        'slug' => 'vendor-synced-var-gift',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Brand synced var gift',
        'slug' => 'brand-synced-var-gift',
        'vendor_id' => $vendor->id,
    ]);

    $variation = Variation::create(['name' => 'Presentacion synced']);
    $selected = VariationItem::create(['name' => 'Caja', 'variation_id' => $variation->id]);
    $giftParent = checkoutInventoryProduct($brand, $tax, 'Gift parent synced dummy', 'DUMMY-SYNCED-PARENT', 0, $variation->id);
    $giftParent->items()->sync([
        $selected->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-SYNCED-VARIATION'],
    ]);

    $trigger = checkoutInventoryProduct($brand, $tax, 'Trigger synced gift', 'TRIGGER-SYNCED-GIFT', 100);
    $bonification = Bonification::create([
        'name' => 'Synced variation gift',
        'buy' => 1,
        'get' => 1,
        'product_id' => $giftParent->id,
        'max' => 100,
    ]);
    $trigger->bonifications()->attach($bonification->id);

    Http::fake([
        '*' => Http::response(bonificationInventorySyncSoapResponse([
            ['sku' => $trigger->sku, 'available' => 20],
            ['sku' => 'GIFT-SYNCED-VARIATION', 'available' => 8],
        ]), 200),
    ]);

    (new SyncProductInventory())->handle();

    expect((int) ProductInventory::where('product_id', $giftParent->id)->whereNull('variation_item_id')->where('bodega_code', 'BOD-1')->value('available'))->toBe(0)
        ->and((int) ProductInventory::where('product_id', $giftParent->id)->where('variation_item_id', $selected->id)->where('bodega_code', 'BOD-1')->value('available'))->toBe(8);

    session()->put('cart', [
        ['product_id' => $giftParent->id, 'quantity' => 1, 'variation_id' => $selected->id],
        ['product_id' => $trigger->id, 'quantity' => 1, 'variation_id' => null],
    ]);

    actingAs($user)->post(route('cart.process'), [
        'zone_id' => $zone->id,
        'observations' => 'Synced selected variation gift stock',
    ])->assertRedirect();

    $order = Order::latest('id')->first();
    expect($order)->not->toBeNull()
        ->and($order->bonifications()->count())->toBe(1);

    $bonificationRow = $order->bonifications()->first();
    expect($bonificationRow->product_id)->toBe($giftParent->id)
        ->and($bonificationRow->variation_item_id)->toBe($selected->id);

    expect((int) ProductInventory::where('product_id', $giftParent->id)->whereNull('variation_item_id')->where('bodega_code', 'BOD-1')->value('available'))->toBe(0)
        ->and((int) ProductInventory::where('product_id', $giftParent->id)->where('variation_item_id', $selected->id)->where('bodega_code', 'BOD-1')->value('available'))->toBe(6);
});

it('uses selected bonification variation stock when parent inventory is zero', function () {
    $user = User::first();
    $zone = configureBonificationInventoryCheckout($user);

    $tax = Tax::create(['name' => 'IVA var stock', 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Vendor var stock',
        'slug' => 'vendor-var-stock',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Brand var stock',
        'slug' => 'brand-var-stock',
        'vendor_id' => $vendor->id,
    ]);

    $variation = Variation::create(['name' => 'Presentacion']);
    $selected = VariationItem::create(['name' => 'Caja', 'variation_id' => $variation->id]);
    $giftParent = checkoutInventoryProduct($brand, $tax, 'Gift parent dummy', 'DUMMY-PARENT', 0, $variation->id);
    $giftParent->items()->sync([
        $selected->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-VARIATION-SKU'],
    ]);

    $trigger = checkoutInventoryProduct($brand, $tax, 'Trigger selected gift', 'TRIGGER-VAR-GIFT', 100);
    $bonification = Bonification::create([
        'name' => 'Selected variation gift',
        'buy' => 1,
        'get' => 1,
        'product_id' => $giftParent->id,
        'max' => 100,
    ]);
    $trigger->bonifications()->attach($bonification->id);

    ProductInventory::create([
        'product_id' => $giftParent->id,
        'bodega_code' => 'BOD-1',
        'available' => 0,
        'physical' => 0,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $giftParent->id,
        'variation_item_id' => $selected->id,
        'source_sku' => 'GIFT-VARIATION-SKU',
        'bodega_code' => 'BOD-1',
        'available' => 8,
        'physical' => 8,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $trigger->id,
        'bodega_code' => 'BOD-1',
        'available' => 20,
        'physical' => 20,
        'reserved' => 0,
    ]);

    session()->put('cart', [
        ['product_id' => $giftParent->id, 'quantity' => 1, 'variation_id' => $selected->id],
        ['product_id' => $trigger->id, 'quantity' => 1, 'variation_id' => null],
    ]);

    actingAs($user)->post(route('cart.process'), [
        'zone_id' => $zone->id,
        'observations' => 'Selected variation gift stock',
    ])->assertRedirect();

    $order = Order::latest('id')->first();
    expect($order)->not->toBeNull()
        ->and($order->products()->count())->toBe(2)
        ->and($order->bonifications()->count())->toBe(1);

    $bonificationRow = $order->bonifications()->first();
    expect($bonificationRow->product_id)->toBe($giftParent->id)
        ->and($bonificationRow->variation_item_id)->toBe($selected->id)
        ->and($bonificationRow->quantity)->toBe(1);

    expect((int) ProductInventory::where('product_id', $giftParent->id)->whereNull('variation_item_id')->where('bodega_code', 'BOD-1')->value('available'))->toBe(0)
        ->and((int) ProductInventory::where('product_id', $giftParent->id)->where('variation_item_id', $selected->id)->where('bodega_code', 'BOD-1')->value('available'))->toBe(6);
});

it('uses default variable gift stock when bonification product is not already in the cart', function () {
    $user = User::first();
    $zone = configureBonificationInventoryCheckout($user);

    $tax = Tax::create(['name' => 'IVA default var gift', 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Vendor default var gift',
        'slug' => 'vendor-default-var-gift',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Brand default var gift',
        'slug' => 'brand-default-var-gift',
        'vendor_id' => $vendor->id,
    ]);

    $variation = Variation::create(['name' => 'Presentacion default']);
    $first = VariationItem::create(['name' => 'Unidad', 'variation_id' => $variation->id]);
    $withSku = VariationItem::create(['name' => 'Caja', 'variation_id' => $variation->id]);
    $giftParent = checkoutInventoryProduct($brand, $tax, 'Gift parent default', '', 0, $variation->id);
    $giftParent->items()->sync([
        $first->id => ['price' => 0, 'enabled' => 1, 'sku' => ''],
        $withSku->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-DEFAULT-VARIATION'],
    ]);

    $trigger = checkoutInventoryProduct($brand, $tax, 'Trigger default gift', 'TRIGGER-DEFAULT-GIFT', 100);
    $bonification = Bonification::create([
        'name' => 'Default variation gift',
        'buy' => 1,
        'get' => 1,
        'product_id' => $giftParent->id,
        'max' => 100,
    ]);
    $trigger->bonifications()->attach($bonification->id);

    ProductInventory::create([
        'product_id' => $giftParent->id,
        'bodega_code' => 'BOD-1',
        'available' => 0,
        'physical' => 0,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $giftParent->id,
        'variation_item_id' => $withSku->id,
        'source_sku' => 'GIFT-DEFAULT-VARIATION',
        'bodega_code' => 'BOD-1',
        'available' => 6,
        'physical' => 6,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $trigger->id,
        'bodega_code' => 'BOD-1',
        'available' => 20,
        'physical' => 20,
        'reserved' => 0,
    ]);

    session()->put('cart', [
        ['product_id' => $trigger->id, 'quantity' => 1, 'variation_id' => null],
    ]);

    actingAs($user)->post(route('cart.process'), [
        'zone_id' => $zone->id,
        'observations' => 'Default variable gift stock',
    ])->assertRedirect();

    $order = Order::latest('id')->first();
    expect($order)->not->toBeNull()
        ->and($order->products()->count())->toBe(1)
        ->and($order->bonifications()->count())->toBe(1);

    $bonificationRow = $order->bonifications()->first();
    expect($bonificationRow->product_id)->toBe($giftParent->id)
        ->and($bonificationRow->variation_item_id)->toBe($withSku->id)
        ->and($bonificationRow->quantity)->toBe(1);

    expect((int) ProductInventory::where('product_id', $giftParent->id)->whereNull('variation_item_id')->where('bodega_code', 'BOD-1')->value('available'))->toBe(0)
        ->and((int) ProductInventory::where('product_id', $giftParent->id)->where('variation_item_id', $withSku->id)->where('bodega_code', 'BOD-1')->value('available'))->toBe(5);
});

it('blocks the order when the selected variation stock cannot cover the bonification gift', function () {
    $user = User::first();
    $zone = configureBonificationInventoryCheckout($user);

    $tax = Tax::create(['name' => 'IVA var constrained', 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Vendor var constrained',
        'slug' => 'vendor-var-constrained',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Brand var constrained',
        'slug' => 'brand-var-constrained',
        'vendor_id' => $vendor->id,
    ]);

    $variation = Variation::create(['name' => 'Color']);
    $selected = VariationItem::create(['name' => 'Azul', 'variation_id' => $variation->id]);
    $giftParent = checkoutInventoryProduct($brand, $tax, 'Gift parent abundant', 'DUMMY-PARENT-ABUNDANT', 0, $variation->id);
    $giftParent->items()->sync([
        $selected->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-VARIATION-LOW'],
    ]);

    $trigger = checkoutInventoryProduct($brand, $tax, 'Trigger constrained gift', 'TRIGGER-VAR-LOW', 100);
    $bonification = Bonification::create([
        'name' => 'Selected variation constrained',
        'buy' => 1,
        'get' => 1,
        'product_id' => $giftParent->id,
        'max' => 100,
    ]);
    $trigger->bonifications()->attach($bonification->id);

    ProductInventory::create([
        'product_id' => $giftParent->id,
        'bodega_code' => 'BOD-1',
        'available' => 100,
        'physical' => 100,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $giftParent->id,
        'variation_item_id' => $selected->id,
        'source_sku' => 'GIFT-VARIATION-LOW',
        'bodega_code' => 'BOD-1',
        'available' => 6,
        'physical' => 6,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $trigger->id,
        'bodega_code' => 'BOD-1',
        'available' => 20,
        'physical' => 20,
        'reserved' => 0,
    ]);

    session()->put('cart', [
        ['product_id' => $giftParent->id, 'quantity' => 1, 'variation_id' => $selected->id],
        ['product_id' => $trigger->id, 'quantity' => 1, 'variation_id' => null],
    ]);

    $response = actingAs($user)->post(route('cart.process'), [
        'zone_id' => $zone->id,
        'observations' => 'Constrained selected variation gift stock',
    ]);

    $response->assertSessionHas('error');
    expect((string) session('error'))->toContain('Inventario insuficiente');

    expect(Order::query()->count())->toBe(0);

    expect((int) ProductInventory::where('product_id', $giftParent->id)->whereNull('variation_item_id')->where('bodega_code', 'BOD-1')->value('available'))->toBe(100)
        ->and((int) ProductInventory::where('product_id', $giftParent->id)->where('variation_item_id', $selected->id)->where('bodega_code', 'BOD-1')->value('available'))->toBe(6);
});

it('creates bonification when gift parent has a SKU but only variation inventory is stocked', function () {
    $user = User::first();
    $zone = configureBonificationInventoryCheckout($user);

    $tax = Tax::create(['name' => 'IVA gift sku var', 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Vendor gift sku var',
        'slug' => 'vendor-gift-sku-var',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Brand gift sku var',
        'slug' => 'brand-gift-sku-var',
        'vendor_id' => $vendor->id,
    ]);

    // Trigger has variations.
    $triggerVariation = Variation::create(['name' => 'Trigger Pres']);
    $triggerV1 = VariationItem::create(['name' => 'Caja 6', 'variation_id' => $triggerVariation->id]);
    $trigger = checkoutInventoryProduct($brand, $tax, 'Trigger gift sku var', 'TRIGGER-GIFT-SKU-VAR', 100, $triggerVariation->id);
    $trigger->items()->sync([
        $triggerV1->id => ['price' => 100, 'enabled' => 1, 'sku' => 'TRIGGER-GIFT-SKU-VAR-V1'],
    ]);

    // Gift parent has a REAL parent SKU AND variations enabled. Inventory is only on the variation.
    $giftVariation = Variation::create(['name' => 'Gift Pres']);
    $giftV1 = VariationItem::create(['name' => 'Unidad', 'variation_id' => $giftVariation->id]);
    $giftParent = checkoutInventoryProduct($brand, $tax, 'Gift parent real sku', 'GIFT-PARENT-REAL-SKU', 0, $giftVariation->id);
    $giftParent->items()->sync([
        $giftV1->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-VAR-REAL-SKU'],
    ]);

    $bonification = Bonification::create([
        'name' => 'Real-sku gift buy 10 get 1',
        'buy' => 10,
        'get' => 1,
        'product_id' => $giftParent->id,
        'max' => 10,
    ]);
    $trigger->bonifications()->attach($bonification->id);

    ProductInventory::create([
        'product_id' => $trigger->id,
        'variation_item_id' => $triggerV1->id,
        'source_sku' => 'TRIGGER-GIFT-SKU-VAR-V1',
        'bodega_code' => 'BOD-1',
        'available' => 50,
        'physical' => 50,
        'reserved' => 0,
    ]);
    // Parent inventory is zero — this is the realistic case where the parent SKU is
    // either a placeholder or simply not stocked, while the variation is the real SKU.
    ProductInventory::create([
        'product_id' => $giftParent->id,
        'bodega_code' => 'BOD-1',
        'available' => 0,
        'physical' => 0,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $giftParent->id,
        'variation_item_id' => $giftV1->id,
        'source_sku' => 'GIFT-VAR-REAL-SKU',
        'bodega_code' => 'BOD-1',
        'available' => 30,
        'physical' => 30,
        'reserved' => 0,
    ]);

    session()->put('cart', [
        ['product_id' => $trigger->id, 'quantity' => 10, 'variation_id' => $triggerV1->id],
    ]);

    actingAs($user)->post(route('cart.process'), [
        'zone_id' => $zone->id,
        'observations' => 'Gift parent sku with variation stock',
    ])->assertRedirect();

    $order = Order::latest('id')->first();
    expect($order)->not->toBeNull()
        ->and($order->bonifications()->count())->toBe(1);

    $bonificationRow = $order->bonifications()->first();
    expect($bonificationRow->product_id)->toBe($giftParent->id)
        ->and($bonificationRow->variation_item_id)->toBe($giftV1->id)
        ->and($bonificationRow->quantity)->toBe(1);
});

it('creates bonification when both trigger and gift have variations across multiple cart rows', function () {
    $user = User::first();
    $zone = configureBonificationInventoryCheckout($user);

    $tax = Tax::create(['name' => 'IVA both var bonif', 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Vendor both var',
        'slug' => 'vendor-both-var',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Brand both var',
        'slug' => 'brand-both-var',
        'vendor_id' => $vendor->id,
    ]);

    // Trigger product has 2 variations and is the parent.
    $triggerVariation = Variation::create(['name' => 'Trigger Presentacion']);
    $triggerV1 = VariationItem::create(['name' => 'Caja 12', 'variation_id' => $triggerVariation->id]);
    $triggerV2 = VariationItem::create(['name' => 'Caja 24', 'variation_id' => $triggerVariation->id]);
    $trigger = checkoutInventoryProduct($brand, $tax, 'Trigger both var', 'TRIGGER-BOTH-VAR', 100, $triggerVariation->id);
    $trigger->items()->sync([
        $triggerV1->id => ['price' => 100, 'enabled' => 1, 'sku' => 'TRIGGER-BOTH-VAR-V1'],
        $triggerV2->id => ['price' => 100, 'enabled' => 1, 'sku' => 'TRIGGER-BOTH-VAR-V2'],
    ]);

    // Gift parent has dummy SKU and 1 enabled variation with its own SKU.
    $giftVariation = Variation::create(['name' => 'Gift Presentacion']);
    $giftV1 = VariationItem::create(['name' => 'Unidad', 'variation_id' => $giftVariation->id]);
    $giftParent = checkoutInventoryProduct($brand, $tax, 'Gift parent both var', '', 0, $giftVariation->id);
    $giftParent->items()->sync([
        $giftV1->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-BOTH-VAR-V1'],
    ]);

    $bonification = Bonification::create([
        'name' => 'Both vars buy 10 get 1',
        'buy' => 10,
        'get' => 1,
        'product_id' => $giftParent->id,
        'max' => 10,
    ]);
    $trigger->bonifications()->attach($bonification->id);

    ProductInventory::create([
        'product_id' => $trigger->id,
        'variation_item_id' => $triggerV1->id,
        'source_sku' => 'TRIGGER-BOTH-VAR-V1',
        'bodega_code' => 'BOD-1',
        'available' => 50,
        'physical' => 50,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $trigger->id,
        'variation_item_id' => $triggerV2->id,
        'source_sku' => 'TRIGGER-BOTH-VAR-V2',
        'bodega_code' => 'BOD-1',
        'available' => 50,
        'physical' => 50,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $giftParent->id,
        'bodega_code' => 'BOD-1',
        'available' => 0,
        'physical' => 0,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $giftParent->id,
        'variation_item_id' => $giftV1->id,
        'source_sku' => 'GIFT-BOTH-VAR-V1',
        'bodega_code' => 'BOD-1',
        'available' => 30,
        'physical' => 30,
        'reserved' => 0,
    ]);

    // Cart with two variation rows for the SAME trigger product, totalling 10 units (qualifies for 1 free).
    session()->put('cart', [
        ['product_id' => $trigger->id, 'quantity' => 5, 'variation_id' => $triggerV1->id],
        ['product_id' => $trigger->id, 'quantity' => 5, 'variation_id' => $triggerV2->id],
    ]);

    actingAs($user)->post(route('cart.process'), [
        'zone_id' => $zone->id,
        'observations' => 'Both variations bonification',
    ])->assertRedirect();

    $order = Order::latest('id')->first();
    expect($order)->not->toBeNull()
        ->and($order->products()->count())->toBe(2)
        ->and($order->bonifications()->count())->toBe(1);

    $bonificationRow = $order->bonifications()->first();
    expect($bonificationRow->product_id)->toBe($giftParent->id)
        ->and($bonificationRow->variation_item_id)->toBe($giftV1->id)
        ->and($bonificationRow->quantity)->toBe(1);
});

it('shows bonification preview in cart when quantities are split across variations', function () {
    $user = User::first();

    Setting::updateOrCreate(
        ['key' => 'min_amount'],
        ['name' => 'Min amount', 'value' => '0', 'show' => false]
    );

    $tax = Tax::create(['name' => 'IVA preview var bonif', 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Vendor preview var',
        'slug' => 'vendor-preview-var',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Brand preview var',
        'slug' => 'brand-preview-var',
        'vendor_id' => $vendor->id,
    ]);

    $triggerVariation = Variation::create(['name' => 'Talla']);
    $sizeA = VariationItem::create(['name' => 'A', 'variation_id' => $triggerVariation->id]);
    $sizeB = VariationItem::create(['name' => 'B', 'variation_id' => $triggerVariation->id]);
    $trigger = checkoutInventoryProduct($brand, $tax, 'Trigger preview var', 'TRIGGER-PREVIEW-VAR', 100, $triggerVariation->id);
    $trigger->items()->sync([
        $sizeA->id => ['price' => 100, 'enabled' => 1, 'sku' => 'TRIGGER-PREVIEW-A'],
        $sizeB->id => ['price' => 100, 'enabled' => 1, 'sku' => 'TRIGGER-PREVIEW-B'],
    ]);

    $gift = checkoutInventoryProduct($brand, $tax, 'Gift preview var', 'GIFT-PREVIEW-VAR', 0);

    $bonification = Bonification::create([
        'name' => 'Buy 6 Get 1 preview',
        'buy' => 6,
        'get' => 1,
        'product_id' => $gift->id,
        'max' => 10,
    ]);
    $trigger->bonifications()->attach($bonification->id);

    session()->put('cart', [
        ['product_id' => $trigger->id, 'quantity' => 4, 'variation_id' => $sizeA->id],
        ['product_id' => $trigger->id, 'quantity' => 2, 'variation_id' => $sizeB->id],
    ]);

    actingAs($user)
        ->get(route('cart'))
        ->assertOk()
        ->assertSeeText('Bonificaciones aplicables')
        ->assertSeeText('Buy 6 Get 1 preview')
        ->assertSeeText('Gift preview var')
        ->assertSeeText('Cantidad acumulada en carrito: 6');
});

it('does not show cart bonification preview when max cap resolves to zero', function () {
    $user = User::first();

    Setting::updateOrCreate(
        ['key' => 'min_amount'],
        ['name' => 'Min amount', 'value' => '0', 'show' => false]
    );

    $tax = Tax::create(['name' => 'IVA preview cap', 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Vendor preview cap',
        'slug' => 'vendor-preview-cap',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Brand preview cap',
        'slug' => 'brand-preview-cap',
        'vendor_id' => $vendor->id,
    ]);

    $trigger = checkoutInventoryProduct($brand, $tax, 'Trigger preview cap', 'TRIGGER-PREVIEW-CAP', 100);
    $gift = checkoutInventoryProduct($brand, $tax, 'Gift preview cap', 'GIFT-PREVIEW-CAP', 0);

    $bonification = Bonification::create([
        'name' => 'Buy 6 Get 1 capped to zero',
        'buy' => 6,
        'get' => 1,
        'product_id' => $gift->id,
        'max' => 0,
    ]);
    $trigger->bonifications()->attach($bonification->id);

    session()->put('cart', [
        ['product_id' => $trigger->id, 'quantity' => 6, 'variation_id' => null],
    ]);

    actingAs($user)
        ->get(route('cart'))
        ->assertOk()
        ->assertDontSeeText('Bonificaciones aplicables')
        ->assertDontSeeText('Buy 6 Get 1 capped to zero');
});

it('shows bonifications section in order details page', function () {
    $user = User::first();
    $zone = Zone::create([
        'user_id' => $user->id,
        'route' => 'R-BONIF-DETAIL',
        'zone' => '933',
        'day' => 'Monday',
        'address' => 'Bonification detail address',
        'code' => 'BONIF-DETAIL',
    ]);

    $tax = Tax::create(['name' => 'IVA order bonif detail', 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Vendor order bonif detail',
        'slug' => 'vendor-order-bonif-detail',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $brand = Brand::create([
        'name' => 'Brand order bonif detail',
        'slug' => 'brand-order-bonif-detail',
        'vendor_id' => $vendor->id,
    ]);

    $trigger = checkoutInventoryProduct($brand, $tax, 'Trigger order bonif detail', 'TRIGGER-ORDER-BONIF-DETAIL', 100);
    $gift = checkoutInventoryProduct($brand, $tax, 'Gift order bonif detail', 'GIFT-ORDER-BONIF-DETAIL', 0);

    $bonification = Bonification::create([
        'name' => 'Order detail bonification',
        'buy' => 6,
        'get' => 1,
        'product_id' => $gift->id,
        'max' => 10,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'zone_id' => $zone->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 1000,
        'discount' => 0,
    ]);

    $orderProduct = \App\Models\OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $trigger->id,
        'quantity' => 6,
        'price' => 100,
        'discount' => 0,
        'percentage' => 0,
        'package_quantity' => 1,
    ]);

    \App\Models\OrderProductBonification::create([
        'bonification_id' => $bonification->id,
        'order_product_id' => $orderProduct->id,
        'product_id' => $gift->id,
        'variation_item_id' => null,
        'quantity' => 1,
        'order_id' => $order->id,
    ]);

    actingAs($user)
        ->get(route('clients.orders.show', $order))
        ->assertOk()
        ->assertSeeText('Bonificaciones aplicadas')
        ->assertSeeText('Order detail bonification')
        ->assertSeeText('Gift order bonif detail');
});
