<?php

use App\Models\Brand;
use App\Models\InventorySyncLog;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Models\ZoneWarehouse;
use App\Services\InventoryDaySalesAdjuster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function clearSalesUnitBackfillFlag(): void
{
    Setting::query()->where('key', InventoryDaySalesAdjuster::FLAG)->delete();
    Cache::forget('setting_'.InventoryDaySalesAdjuster::FLAG);
}

function salesInventoryProduct(bool $calculatePackagePrice, int $packageQuantity): Product
{
    $tax = Tax::firstOrCreate(['name' => 'IVA sales'], ['tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Vendor '.uniqid(),
        'slug' => 'vendor-'.uniqid(),
        'minimum_purchase' => 0,
        'active' => 1,
        'discount' => 0,
    ]);
    $brand = Brand::create([
        'name' => 'Brand '.uniqid(),
        'slug' => 'brand-'.uniqid(),
        'vendor_id' => $vendor->id,
        'active' => 1,
        'discount' => 0,
    ]);

    return Product::create([
        'name' => 'Packed '.uniqid(),
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'SKU-'.uniqid(),
        'slug' => 'packed-'.uniqid(),
        'active' => 1,
        'price' => 1000,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => $packageQuantity,
        'calculate_package_price' => $calculatePackagePrice,
        'safety_stock' => 0,
        'inventory_opt_out' => 0,
    ]);
}

it('counts package-priced sales in individual units and leaves package-as-unit products unchanged', function () {
    $packed = salesInventoryProduct(true, 6);
    $bundle = salesInventoryProduct(false, 6);

    expect($packed->inventoryUnitsForQuantity(2))->toBe(12)
        ->and($bundle->inventoryUnitsForQuantity(2))->toBe(2);
});

it('removes the package-size gap from stock sold after the last bodega sync', function () {
    clearSalesUnitBackfillFlag();
    ZoneWarehouse::updateOrCreate(['zone_code' => '933'], ['bodega_code' => 'BOD-SALES']);

    $packed = salesInventoryProduct(true, 6);
    $bundle = salesInventoryProduct(false, 6);
    $beforeSync = salesInventoryProduct(true, 6);

    foreach ([$packed, $bundle, $beforeSync] as $product) {
        ProductInventory::create([
            'product_id' => $product->id,
            'bodega_code' => 'BOD-SALES',
            'available' => 98,
            'physical' => 100,
            'reserved' => 0,
        ]);
    }

    $user = User::factory()->create();
    $zone = Zone::create([
        'user_id' => $user->id,
        'route' => '1',
        'zone' => '933',
        'day' => 'Monday',
        'address' => 'Addr',
        'code' => 'Z-SALES',
    ]);

    $log = InventorySyncLog::create([
        'bodega_code' => 'BOD-SALES',
        'status' => 'success',
    ]);
    $log->created_at = now()->subHour();
    $log->save();

    $order = Order::create([
        'user_id' => $user->id,
        'zone_id' => $zone->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 1000,
        'discount' => 0,
    ]);
    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $packed->id,
        'quantity' => 2,
        'price' => 1000,
        'discount' => 0,
        'package_quantity' => 6,
        'is_bonification' => false,
    ]);
    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $bundle->id,
        'quantity' => 3,
        'price' => 1000,
        'discount' => 0,
        'package_quantity' => 6,
        'is_bonification' => false,
    ]);

    $earlier = Order::create([
        'user_id' => $user->id,
        'zone_id' => $zone->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 1000,
        'discount' => 0,
    ]);
    $earlier->created_at = now()->subHours(3);
    $earlier->save();
    $earlierLine = OrderProduct::create([
        'order_id' => $earlier->id,
        'product_id' => $beforeSync->id,
        'quantity' => 2,
        'price' => 1000,
        'discount' => 0,
        'package_quantity' => 6,
        'is_bonification' => false,
    ]);
    $earlierLine->created_at = now()->subHours(3);
    $earlierLine->save();

    $removed = app(InventoryDaySalesAdjuster::class)->applyUncountedPackageUnits();

    expect($removed)->toBe(10)
        ->and((int) ProductInventory::where('product_id', $packed->id)->value('available'))->toBe(88)
        ->and((int) ProductInventory::where('product_id', $bundle->id)->value('available'))->toBe(98)
        ->and((int) ProductInventory::where('product_id', $beforeSync->id)->value('available'))->toBe(98)
        ->and(app(InventoryDaySalesAdjuster::class)->applyUncountedPackageUnits())->toBe(0);
});

it('reduces bodega stock by dynamics sales units when an order is placed', function () {
    Queue::fake();
    Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '1', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'global_minimum_inventory'],
        ['name' => 'Global minimum inventory', 'value' => '5', 'show' => false]
    );
    Cache::forget('setting_inventory_enabled');
    Cache::forget('setting_global_minimum_inventory');

    ZoneWarehouse::updateOrCreate(['zone_code' => '933'], ['bodega_code' => 'BOD-CHECKOUT']);
    $user = User::factory()->create(['document' => null]);
    $zone = Zone::create([
        'user_id' => $user->id,
        'route' => '1',
        'zone' => '933',
        'day' => 'Monday',
        'address' => 'Addr',
        'code' => 'Z-CHECKOUT',
    ]);
    $product = salesInventoryProduct(true, 10);
    ProductInventory::create([
        'product_id' => $product->id,
        'bodega_code' => 'BOD-CHECKOUT',
        'available' => 200,
        'physical' => 200,
        'reserved' => 0,
    ]);

    session()->put('cart', [
        ['product_id' => $product->id, 'quantity' => 3, 'variation_id' => null],
    ]);

    actingAs($user)->post(route('cart.process'), [
        'zone_id' => $zone->id,
        'observations' => 'Package units',
    ])->assertRedirect();

    expect(Order::query()->count())->toBe(1)
        ->and((int) ProductInventory::where('product_id', $product->id)->value('available'))->toBe(170);
});

it('blocks an order whose package units would pass the old package-count check', function () {
    Queue::fake();
    Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '1', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'global_minimum_inventory'],
        ['name' => 'Global minimum inventory', 'value' => '5', 'show' => false]
    );
    Cache::forget('setting_inventory_enabled');
    Cache::forget('setting_global_minimum_inventory');

    ZoneWarehouse::updateOrCreate(['zone_code' => '933'], ['bodega_code' => 'BOD-BLOCK']);
    $user = User::factory()->create(['document' => null]);
    $zone = Zone::create([
        'user_id' => $user->id,
        'route' => '1',
        'zone' => '933',
        'day' => 'Monday',
        'address' => 'Addr',
        'code' => 'Z-BLOCK',
    ]);
    $product = salesInventoryProduct(true, 10);
    ProductInventory::create([
        'product_id' => $product->id,
        'bodega_code' => 'BOD-BLOCK',
        'available' => 40,
        'physical' => 40,
        'reserved' => 0,
    ]);

    session()->put('cart', [
        ['product_id' => $product->id, 'quantity' => 4, 'variation_id' => null],
    ]);

    actingAs($user)->post(route('cart.process'), [
        'zone_id' => $zone->id,
        'observations' => 'Too many units',
    ])->assertSessionHas('error');

    expect(Order::query()->count())->toBe(0)
        ->and((int) ProductInventory::where('product_id', $product->id)->value('available'))->toBe(40);
});
