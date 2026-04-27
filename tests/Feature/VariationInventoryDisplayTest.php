<?php

use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Setting;
use App\Models\User;
use App\Models\Variation;
use App\Models\VariationItem;
use App\Models\Zone;
use App\Models\ZoneWarehouse;
use Database\Factories\BrandFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function configureVariationInventoryDisplay(User $user): void
{
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
        ['bodega_code' => 'BOD-VAR']
    );
    Zone::create([
        'user_id' => $user->id,
        'route' => 'R1',
        'zone' => '933',
        'day' => 'Monday',
        'address' => 'Selected customer zone',
        'code' => 'Z-VAR',
    ]);
}

function variableProductWithVariationStock(): array
{
    $variation = Variation::create(['name' => 'Presentacion']);
    $empty = VariationItem::create(['name' => 'Unidad', 'variation_id' => $variation->id]);
    $stocked = VariationItem::create(['name' => 'Caja', 'variation_id' => $variation->id]);

    $parent = Product::factory()
        ->for(BrandFactory::new())
        ->state([
            'name' => 'Variable Parent Dummy',
            'slug' => 'variable-parent-dummy',
            'sku' => 'DUMMY-PARENT-STOCK-ZERO',
            'variation_id' => $variation->id,
            'safety_stock' => 0,
            'inventory_opt_out' => false,
        ])
        ->create();

    $parent->items()->sync([
        $empty->id => ['price' => 1000, 'enabled' => 1, 'sku' => ''],
        $stocked->id => ['price' => 1000, 'enabled' => 1, 'sku' => 'REAL-VARIATION-STOCK-SKU'],
    ]);

    ProductInventory::create([
        'product_id' => $parent->id,
        'bodega_code' => 'BOD-VAR',
        'available' => 0,
        'physical' => 0,
        'reserved' => 0,
    ]);
    ProductInventory::create([
        'product_id' => $parent->id,
        'variation_item_id' => $stocked->id,
        'source_sku' => 'REAL-VARIATION-STOCK-SKU',
        'bodega_code' => 'BOD-VAR',
        'available' => 12,
        'physical' => 12,
        'reserved' => 0,
    ]);

    return compact('parent', 'empty', 'stocked');
}

it('shows product page availability from synced variation stock on the parent product', function () {
    $user = User::factory()->create();
    configureVariationInventoryDisplay($user);
    $data = variableProductWithVariationStock();

    actingAs($user)
        ->get(route('product', $data['parent']->slug))
        ->assertOk()
        ->assertSee('7 unidades')
        ->assertSee('id="pdp-stock-available" class="text-sm text-gray-600 "', false)
        ->assertSee('id="pdp-stock-unavailable" class="text-sm text-orange-500 hidden"', false);

    expect($data['parent']->fresh(['items'])->preferredVariationItemIdForBodega('BOD-VAR'))->toBe($data['stocked']->id)
        ->and($data['parent']->fresh(['items'])->getOrderableStockForBodega('BOD-VAR'))->toBe(7)
        ->and($data['parent']->fresh(['items'])->getOrderableStockForBodega('BOD-VAR', $data['empty']->id))->toBe(0)
        ->and($data['parent']->fresh(['items'])->getOrderableStockForBodega('BOD-VAR', $data['stocked']->id))->toBe(7);
});

it('renders product cards as available when any enabled variation has stock', function () {
    $user = User::factory()->create();
    configureVariationInventoryDisplay($user);
    $data = variableProductWithVariationStock();

    $this
        ->actingAs($user)
        ->blade('<x-product :product="$product" bodega-code="BOD-VAR" />', [
            'product' => $data['parent']->fresh(['items', 'images', 'brand.vendor', 'bonifications', 'categories']),
        ])
        ->assertSee('Inventario: 7')
        ->assertDontSee('Producto no disponible para tu ubicación')
        ->assertSee('value="'.$data['stocked']->id.'"', false);
});

it('shows variation stock inside the admin product variations table', function () {
    $data = variableProductWithVariationStock();

    $this
        ->blade('@include("products.variations", ["product" => $product])', [
            'product' => $data['parent']->fresh(['variation', 'items']),
        ])
        ->assertSee('Inventario')
        ->assertSee('REAL-VARIATION-STOCK-SKU')
        ->assertSee('BOD-VAR')
        ->assertSee('Disp: 12')
        ->assertSee('Fis: 12')
        ->assertSee('Sin SKU para sincronizar');
});
