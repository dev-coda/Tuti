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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        ['name' => 'Microsoft token', 'value' => 'sync-token', 'show' => false]
    );
    ZoneWarehouse::updateOrCreate(
        ['zone_code' => '933'],
        ['bodega_code' => $bodega]
    );
}

function fakeInventorySyncResponses(array $items): void
{
    Http::fake([
        'https://dynamics.test/soap/services/DIITDWSSalesForceGroup' => Http::response(inventorySyncSoapResponse($items), 200),
    ]);
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

it('syncs direct products and parent variation inventory while dummy parents stay zero', function () {
    configureInventorySyncJob();
    Log::spy();

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
    $pivotOnlyVariationItem = VariationItem::create(['name' => 'Bolsa', 'variation_id' => $variation->id]);
    $legacyVariationItem = VariationItem::create(['name' => 'Legacy', 'variation_id' => $variation->id]);
    $parent = inventorySyncProduct('DUMMY-PARENT-SYNC', ['variation_id' => $variation->id]);
    $parent->items()->sync([
        $variationItem->id => ['price' => 1, 'enabled' => 1, 'sku' => 'VARIATION-SKU'],
        $pivotOnlyVariationItem->id => ['price' => 1, 'enabled' => 1, 'sku' => 'PIVOT-ONLY-VARIATION-SKU'],
        $legacyVariationItem->id => ['price' => 1, 'enabled' => 1, 'sku' => ''],
    ]);
    Setting::updateOrCreate(
        ['key' => 'inventory_sync_debug_skus'],
        ['name' => 'Inventory sync debug SKUs', 'value' => 'DUMMY-PARENT-SYNC, VARIATION-SKU, PIVOT-ONLY-VARIATION-SKU, LEGACY-VARIATION-SKU, MISSING-VARIATION-SKU', 'show' => false]
    );
    DB::table('product_variations')->insert([
        'product_id' => $parent->id,
        'variation_items_id' => $legacyVariationItem->id,
        'sku' => 'LEGACY-VARIATION-SKU',
        'price' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    ProductInventory::create([
        'product_id' => $parent->id,
        'bodega_code' => 'BOD-SYNC',
        'available' => 13,
        'physical' => 13,
        'reserved' => 0,
    ]);

    fakeInventorySyncResponses([
        ['sku' => 'SIMPLE-SKU', 'available' => 7, 'physical' => 9, 'reserved' => 2],
        ['sku' => 'DUP-SKU', 'available' => 3, 'physical' => 3, 'reserved' => 0],
        ['sku' => 'DUP-SKU', 'available' => 4, 'physical' => 5, 'reserved' => 1],
        ['sku' => 'DUMMY-PARENT-SYNC', 'available' => 999, 'physical' => 999, 'reserved' => 0],
        ['sku' => 'VARIATION-SKU', 'available' => 11, 'physical' => 12, 'reserved' => 1],
        ['sku' => 'PIVOT-ONLY-VARIATION-SKU', 'available' => 18, 'physical' => 20, 'reserved' => 2],
        ['sku' => 'LEGACY-VARIATION-SKU', 'available' => 21, 'physical' => 25, 'reserved' => 4],
        ['sku' => 'IGNORED-WMS-SKU', 'available' => 99, 'location' => 'EMPAQUE'],
    ]);

    (new SyncProductInventory())->handle();

    expect((int) ProductInventory::where('product_id', $simple->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(7)
        ->and((int) ProductInventory::where('product_id', $simple->id)->where('bodega_code', 'BOD-SYNC')->value('physical'))->toBe(9)
        ->and((int) ProductInventory::where('product_id', $simple->id)->where('bodega_code', 'BOD-SYNC')->value('reserved'))->toBe(2)
        ->and((int) ProductInventory::where('product_id', $duplicateA->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(7)
        ->and((int) ProductInventory::where('product_id', $duplicateB->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(7)
        ->and((int) ProductInventory::where('product_id', $parent->id)->where('variation_item_id', $variationItem->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(11)
        ->and((int) ProductInventory::where('product_id', $parent->id)->where('variation_item_id', $pivotOnlyVariationItem->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(18)
        ->and((int) ProductInventory::where('product_id', $parent->id)->where('variation_item_id', $legacyVariationItem->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(21)
        ->and(ProductInventory::where('product_id', $parent->id)->whereNull('variation_item_id')->where('bodega_code', 'BOD-SYNC')->exists())->toBeFalse()
        ->and((int) ProductInventory::where('product_id', $absentManaged->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(0)
        ->and((int) ProductInventory::where('product_id', $optedOut->id)->where('bodega_code', 'BOD-SYNC')->value('available'))->toBe(40)
        ->and(ProductInventory::where('bodega_code', 'BOD-SYNC')->whereHas('product', fn ($query) => $query->where('sku', 'IGNORED-WMS-SKU'))->exists())->toBeFalse();

    $log = InventorySyncLog::latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('success')
        ->and($log->skus_received)->toBe(6)
        ->and($log->products_updated)->toBe(6);

    $progress = json_decode((string) Setting::getByKey('inventory_sync_progress'), true);
    expect($progress)->toBeArray()
        ->and($progress['status'])->toBe('completed')
        ->and($progress['percentage'])->toBe(100)
        ->and($progress['processed_bodegas'])->toBe(1)
        ->and($progress['total_bodegas'])->toBe(1);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context = []) {
            return $message === 'Inventory variation sync diagnostics for bodega BOD-SYNC'
                && ($context['debug_skus']['DUMMY-PARENT-SYNC']['matched_product_sku'] ?? true) === false
                && ($context['debug_skus']['DUMMY-PARENT-SYNC']['skipped_variable_parent_product_ids'] ?? []) !== []
                && ($context['debug_skus']['VARIATION-SKU']['in_soap_response'] ?? false) === true
                && ($context['debug_skus']['VARIATION-SKU']['matched_variation_sku'] ?? false) === true
                && ($context['debug_skus']['PIVOT-ONLY-VARIATION-SKU']['matched_variation_sku'] ?? false) === true
                && ($context['debug_skus']['LEGACY-VARIATION-SKU']['matched_variation_sku'] ?? false) === true
                && ($context['debug_skus']['MISSING-VARIATION-SKU']['in_soap_response'] ?? true) === false;
        })
        ->once();
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

it('uses the stored microsoft token from settings during inventory sync', function () {
    configureInventorySyncJob();

    fakeInventorySyncResponses([
        ['sku' => 'STORED-SKU', 'available' => 3],
    ]);

    inventorySyncProduct('STORED-SKU');

    (new SyncProductInventory())->handle();

    Http::assertSentCount(1);
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'dynamics.test')
            && $request->header('Authorization')[0] === 'Bearer sync-token';
    });
});

it('logs an error when the stored microsoft token is missing', function () {
    config(['microsoft.resource' => 'https://dynamics.test']);
    Setting::updateOrCreate(
        ['key' => 'inventory_enabled'],
        ['name' => 'Inventory enabled', 'value' => '1', 'show' => false]
    );
    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft token', 'value' => '', 'show' => false]
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
        ->and($log->error_message)->toContain('No hay token de Microsoft almacenado');
});
