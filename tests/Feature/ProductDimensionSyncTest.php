<?php

use App\Jobs\SyncProductDimensions;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductDimensionSyncLog;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeDimProduct(string $sku, array $overrides = []): Product
{
    $tax = Tax::firstOrCreate(['name' => 'IVA 0'], ['tax' => 0]);
    $vendor = Vendor::firstOrCreate(
        ['slug' => 'vendor-dim'],
        ['name' => 'Vendor Dim', 'vendor_type' => 'V', 'minimum_purchase' => 0, 'active' => 1]
    );
    $brand = Brand::firstOrCreate(
        ['slug' => 'brand-dim'],
        ['name' => 'Brand Dim', 'vendor_id' => $vendor->id]
    );

    return Product::create(array_merge([
        'name' => 'Producto ' . $sku,
        'slug' => 'producto-' . strtolower($sku),
        'description' => '',
        'short_description' => '',
        'sku' => $sku,
        'active' => 1,
        'price' => 1000,
        'delivery_days' => 1,
        'discount' => 0,
        'discount_type' => 'percentage',
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
        'calculate_package_price' => false,
    ], $overrides));
}

function obtenerArticulosResponse(array $items): string
{
    $itemsXml = '';
    foreach ($items as $item) {
        $itemsXml .= '<a:ListItem>
            <a:ItemId>' . $item['sku'] . '</a:ItemId>
            <a:ItemName>Articulo</a:ItemName>
            <a:Unit>UND</a:Unit>
            <a:TaxPercent>19,00</a:TaxPercent>
            <a:depth>' . $item['depth'] . '</a:depth>
            <a:height>' . $item['height'] . '</a:height>
            <a:weight>' . $item['weight'] . '</a:weight>
            <a:width>' . $item['width'] . '</a:width>
        </a:ListItem>';
    }

    return '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/">
        <s:Body>
            <ObtenerArticulosResponse xmlns="http://tempuri.org">
                <result xmlns:a="http://schemas.datacontract.org/2004/07/Dynamics.AX.Application" xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
                    ' . $itemsXml . '
                </result>
            </ObtenerArticulosResponse>
        </s:Body>
    </s:Envelope>';
}

beforeEach(function () {
    config(['microsoft.resource' => 'https://dynamics.test']);
    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft Token', 'value' => 'test-token', 'show' => false]
    );
});

it('syncs product dimensions from dynamics and skips zero values', function () {
    $withDims = makeDimProduct('SKU-DIM-001');
    $zeroDims = makeDimProduct('SKU-DIM-ZERO', [
        'slug' => 'producto-zero',
        'coordinadora_weight_kg' => 9.9,
    ]);

    Http::fake([
        'https://dynamics.test/*' => Http::response(obtenerArticulosResponse([
            ['sku' => 'SKU-DIM-001', 'weight' => '2.500', 'width' => '10.00', 'height' => '20.00', 'depth' => '30.00'],
            ['sku' => 'SKU-DIM-ZERO', 'weight' => '0.00', 'width' => '0.00', 'height' => '0.00', 'depth' => '0.00'],
            ['sku' => 'SKU-UNKNOWN', 'weight' => '1.00', 'width' => '1.00', 'height' => '1.00', 'depth' => '1.00'],
        ]), 200),
    ]);

    (new SyncProductDimensions())->handle();

    $withDims->refresh();
    expect((float) $withDims->coordinadora_weight_kg)->toBe(2.5);
    expect((float) $withDims->coordinadora_width_cm)->toBe(10.0);
    expect((float) $withDims->coordinadora_height_cm)->toBe(20.0);
    expect((float) $withDims->coordinadora_length_cm)->toBe(30.0);

    // All-zero article must not clobber stored data
    $zeroDims->refresh();
    expect((float) $zeroDims->coordinadora_weight_kg)->toBe(9.9);

    $log = ProductDimensionSyncLog::latest('id')->first();
    expect($log->status)->toBe('success');
    expect($log->items_received)->toBe(3);
    expect($log->items_with_dimensions)->toBe(2);
    expect($log->products_updated)->toBe(1);
    expect($log->unmatched_skus)->toBe(['SKU-UNKNOWN']);

    expect(Setting::getByKey('product_dimensions_last_synced_at'))->not->toBeNull();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'DYNPRODWSSalesForceGroup')
            && ($request->header('SOAPAction')[0] ?? '') === 'http://tempuri.org/DWSSalesForce/ObtenerArticulos'
            && str_contains($request->body(), '<tem:ObtenerArticulos/>');
    });
});

it('preserves existing values when dynamics sends partial dimensions', function () {
    $product = makeDimProduct('SKU-DIM-PARTIAL', [
        'coordinadora_weight_kg' => 5,
        'coordinadora_height_cm' => 15,
    ]);

    Http::fake([
        'https://dynamics.test/*' => Http::response(obtenerArticulosResponse([
            ['sku' => 'SKU-DIM-PARTIAL', 'weight' => '0.00', 'width' => '12.00', 'height' => '0.00', 'depth' => '18.00'],
        ]), 200),
    ]);

    (new SyncProductDimensions())->handle();

    $product->refresh();
    expect((float) $product->coordinadora_weight_kg)->toBe(5.0);
    expect((float) $product->coordinadora_height_cm)->toBe(15.0);
    expect((float) $product->coordinadora_width_cm)->toBe(12.0);
    expect((float) $product->coordinadora_length_cm)->toBe(18.0);
});

it('sends the item filter when syncing a single sku', function () {
    makeDimProduct('SKU-DIM-ONE');

    Http::fake([
        'https://dynamics.test/*' => Http::response(obtenerArticulosResponse([
            ['sku' => 'SKU-DIM-ONE', 'weight' => '1.00', 'width' => '2.00', 'height' => '3.00', 'depth' => '4.00'],
        ]), 200),
    ]);

    (new SyncProductDimensions('SKU-DIM-ONE'))->handle();

    Http::assertSent(function ($request) {
        return str_contains($request->body(), '<tem:_itemId>SKU-DIM-ONE</tem:_itemId>');
    });

    // A filtered run must not update the full-sync timestamp
    expect(Setting::getByKey('product_dimensions_last_synced_at'))->toBeNull();
});

it('logs an error when the webservice fails', function () {
    Http::fake([
        'https://dynamics.test/*' => Http::response('boom', 500),
    ]);

    expect(fn () => (new SyncProductDimensions())->handle())->toThrow(Exception::class);

    $log = ProductDimensionSyncLog::latest('id')->first();
    expect($log->status)->toBe('error');
    expect($log->error_message)->toContain('HTTP 500');
});

it('skips the sync when disabled by setting', function () {
    Setting::updateOrCreate(
        ['key' => 'dimension_sync_enabled'],
        ['name' => 'Dimension sync', 'value' => '0', 'show' => false]
    );

    Http::fake();

    (new SyncProductDimensions())->handle();

    Http::assertNothingSent();
    expect(ProductDimensionSyncLog::count())->toBe(0);
});
