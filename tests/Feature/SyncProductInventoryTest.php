<?php

use App\Jobs\SyncProductInventory;
use App\Models\InventorySyncLog;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Setting;
use App\Models\Variation;
use App\Models\VariationItem;
use App\Models\ZoneWarehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function configureInventorySyncJob(string $bodega = 'BOD-SYNC'): void
{
    config(['microsoft.resource' => 'https://dynamics.test']);

    Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '1', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft token', 'value' => 'token', 'show' => false]
    );
    ZoneWarehouse::updateOrCreate(
        ['zone_code' => '933'],
        ['bodega_code' => $bodega]
    );
}

function inventorySyncProduct(string $sku, array $state = []): Product
{
    return Product::factory()
        ->state(array_merge([
            'sku' => $sku,
            'safety_stock' => 0,
            'inventory_opt_out' => false,
        ], $state))
        ->create();
}

function inventorySyncSoapResponse(array $items): string
{
    $rows = collect($items)->map(function (array $item): string {
        $location = $item['location'] ?? 'DISPONIBLE';

        return '<a:ListItemExists>'
            .'<a:ItemId>'.htmlspecialchars($item['sku']).'</a:ItemId>'
            .'<a:AvailPhysical>'.(int) $item['available'].'</a:AvailPhysical>'
            .'<a:PhysicalInvent>'.(int) ($item['physical'] ?? $item['available']).'</a:PhysicalInvent>'
            .'<a:ReservPhysical>'.(int) ($item['reserved'] ?? 0).'</a:ReservPhysical>'
            .'<a:WMSLocation>'.htmlspecialchars($location).'</a:WMSLocation>'
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

it('syncs direct products and variation child products while dummy parents stay zero', function () {
    configureInventorySyncJob();

    $simple = inventorySyncProduct('SIMPLE-SKU');
    $duplicateA = inventorySyncProduct('DUP-SKU');
    $duplicateB = inventorySyncProduct('DUP-SKU');
    $absentManaged = inventorySyncProduct('ABSENT-SKU');
    $optedOut = inventorySyncProduct('OPT-OUT-SKU', ['inventory_opt_out' => true]);

    ProductInventory::create([
        'product_id' => $absentManaged->id,
        'bodega_code' => 'BOD-SYNC',
        'available' => 25,
        'physical' => 25,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $optedOut->id,
        'bodega_code' => 'BOD-SYNC',
        'available' => 40,
        'physical' => 40,
        'reserved' => 0,
    ]);

    $variation = Variation::create(['name' => 'Presentacion sync']);
    $variationItem = VariationItem::create(['name' => 'Caja', 'variation_id' => $variation->id]);
    $parent = inventorySyncProduct('DUMMY-PARENT-SYNC', ['variation_id' => $variation->id]);
    $child = inventorySyncProduct('CHILD-VARIATION-SKU');
    $parent->items()->sync([
        $variationItem->id => ['price' => 1, 'enabled' => 1, 'sku' => $child->sku],
    ]);
    ProductInventory::create([
        'product_id' => $parent->id,
        'bodega_code' => 'BOD-SYNC',
        'available' => 13,
        'physical' => 13,
        'reserved' => 0,
    ]);

    Http::fake([
        '*' => Http::response(inventorySyncSoapResponse([
            ['sku' => 'SIMPLE-SKU', 'available' => 7, 'physical' => 9, 'reserved' => 2],
            ['sku' => 'DUP-SKU', 'available' => 3, 'physical' => 3, 'reserved' => 0],
            ['sku' => 'DUP-SKU', 'available' => 4, 'physical' => 5, 'reserved' => 1],
            ['sku' => 'CHILD-VARIATION-SKU', 'available' => 11, 'physical' => 12, 'reserved' => 1],
            ['sku' => 'IGNORED-WMS-SKU', 'available' => 99, 'location' => 'EMPAQUE'],
        ]), 200),
    ]);

    (new SyncProductInventory())->handle();

    expect((int) ProductInventory::where('product_id', $simple->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(7)
        ->and((int) ProductInventory::where('product_id', $simple->id)->where('bodega_code', 'BOD-SYNC')->value('physical'))->toBe(9)
        ->and((int) ProductInventory::where('product_id', $simple->id)->where('bodega_code', 'BOD-SYNC')->value('reserved'))->toBe(2)
        ->and((int) ProductInventory::where('product_id', $duplicateA->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(7)
        ->and((int) ProductInventory::where('product_id', $duplicateB->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(7)
        ->and((int) ProductInventory::where('product_id', $child->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(11)
        ->and((int) ProductInventory::where('product_id', $parent->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(0)
        ->and((int) ProductInventory::where('product_id', $absentManaged->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(0)
        ->and((int) ProductInventory::where('product_id', $optedOut->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(40)
        ->and(ProductInventory::where('bodega_code', 'BOD-SYNC')->whereHas('product', fn ($query) => $query->where('sku', 'IGNORED-WMS-SKU'))->exists())->toBeFalse();

    $log = InventorySyncLog::latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('success')
        ->and($log->skus_received)->toBe(3)
        ->and($log->products_updated)->toBe(4);
});

it('keeps automatic sync disabled when inventory is off', function () {
    config(['microsoft.resource' => 'https://dynamics.test']);
    Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '0', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft token', 'value' => 'token', 'show' => false]
    );
    ZoneWarehouse::updateOrCreate(
        ['zone_code' => '933'],
        ['bodega_code' => 'BOD-SYNC']
    );

    $product = inventorySyncProduct('OFF-SKU');
    ProductInventory::create([
        'product_id' => $product->id,
        'bodega_code' => 'BOD-SYNC',
        'available' => 17,
        'physical' => 17,
        'reserved' => 0,
    ]);

    Http::fake();

    (new SyncProductInventory())->handle();

    Http::assertNothingSent();
    expect((int) ProductInventory::where('product_id', $product->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(17)
        ->and(InventorySyncLog::count())->toBe(0);
});

it('logs an error and avoids inventory changes when the microsoft token is missing', function () {
    config(['microsoft.resource' => 'https://dynamics.test']);
    Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '1', 'show' => false]
    );
    ZoneWarehouse::updateOrCreate(
        ['zone_code' => '933'],
        ['bodega_code' => 'BOD-SYNC']
    );

    $product = inventorySyncProduct('NO-TOKEN-SKU');
    ProductInventory::create([
        'product_id' => $product->id,
        'bodega_code' => 'BOD-SYNC',
        'available' => 19,
        'physical' => 19,
        'reserved' => 0,
    ]);

    Http::fake();

    (new SyncProductInventory())->handle();

    Http::assertNothingSent();
    expect((int) ProductInventory::where('product_id', $product->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(19);

    $log = InventorySyncLog::latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('error')
        ->and($log->error_message)->toBe('Missing microsoft_token setting');
});
