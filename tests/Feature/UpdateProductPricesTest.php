<?php

use App\Jobs\UpdateProductPrices;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Variation;
use App\Models\VariationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function priceSyncSoapResponse(array $rows): string
{
    $items = collect($rows)->map(function (array $row): string {
        return '<ListPriceDisc>'
            .'<GroupId>'.htmlspecialchars((string) $row['group']).'</GroupId>'
            .'<ItemId>'.htmlspecialchars((string) $row['sku']).'</ItemId>'
            .'<Amount>'.htmlspecialchars((string) $row['amount']).'</Amount>'
            .'</ListPriceDisc>';
    })->implode('');

    return '<Envelope>'
        .'<Body>'
        .'<getPriceAndDiscountResponse>'
        .'<result>'
        .'<getPriceAndDiscountResult>'
        .$items
        .'</getPriceAndDiscountResult>'
        .'</result>'
        .'</getPriceAndDiscountResponse>'
        .'</Body>'
        .'</Envelope>';
}

it('updates all products sharing the same sku during automatic price sync', function () {
    config(['microsoft.resource' => 'https://dynamics.test']);

    Setting::updateOrCreate(
        ['key' => 'microsoft_token'],
        ['name' => 'Microsoft token', 'value' => 'token', 'show' => false]
    );

    $variation = Variation::create(['name' => 'Presentacion']);
    $variationItem = VariationItem::create(['name' => 'Caja', 'variation_id' => $variation->id]);

    $productA = Product::factory()->create([
        'sku' => 'dup-01',
        'price' => 10,
        'calculate_package_price' => true,
    ]);

    $productB = Product::factory()->create([
        'sku' => ' DUP-01 ',
        'price' => 10,
        'calculate_package_price' => false,
        'package_quantity' => 4,
    ]);

    $productC = Product::factory()->create([
        'sku' => 'Dup-01',
        'price' => 10,
        'calculate_package_price' => true,
        'variation_id' => $variation->id,
        'sync_variations_with_dynamics' => true,
    ]);
    $productC->items()->sync([
        $variationItem->id => ['price' => 10, 'enabled' => 1, 'sku' => 'VAR-DUP-01'],
    ]);

    Http::fake([
        '*' => Http::response(priceSyncSoapResponse([
            ['group' => 'TATNAC', 'sku' => 'DUP-01', 'amount' => '80,00'],
            ['group' => 'OTHER', 'sku' => 'IGNORED', 'amount' => '999,00'],
        ]), 200),
    ]);

    (new UpdateProductPrices())->handle();

    expect((float) $productA->fresh()->price)->toBe(80.0)
        ->and((float) $productB->fresh()->price)->toBe(20.0)
        ->and((float) $productC->fresh()->price)->toBe(80.0)
        ->and((float) DB::table('product_item_variation')
            ->where('product_id', $productC->id)
            ->where('variation_item_id', $variationItem->id)
            ->value('price'))->toBe(80.0);
});
