<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Variation;
use App\Models\VariationItem;
use Database\Factories\BrandFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patchJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $user->assignRole('admin');
    actingAs($user);
});

function productWithVariations(): array
{
    $variation = Variation::create(['name' => 'Color']);
    $red = VariationItem::create(['name' => 'Rojo', 'variation_id' => $variation->id]);
    $blue = VariationItem::create(['name' => 'Azul', 'variation_id' => $variation->id]);

    $product = Product::factory()
        ->for(BrandFactory::new())
        ->state(['variation_id' => $variation->id])
        ->create();

    $product->items()->sync([
        $red->id => ['price' => 1000, 'enabled' => 1, 'sku' => 'RED'],
        $blue->id => ['price' => 1000, 'enabled' => 1, 'sku' => 'BLUE'],
    ]);

    $image = $product->images()->create([
        'path' => 'products/test.jpg',
        'position' => 1,
        'variation_item_id' => null,
    ]);

    return compact('product', 'red', 'blue', 'image');
}

it('updates image variation assignment for a product image', function () {
    ['product' => $product, 'red' => $red, 'image' => $image] = productWithVariations();

    patchJson(route('products.images_variation', [$product, $image]), [
        'variation_item_id' => $red->id,
    ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'variation_item_id' => $red->id,
        ]);

    expect($image->fresh()->variation_item_id)->toBe($red->id);
});

it('clears image variation assignment', function () {
    ['product' => $product, 'red' => $red, 'image' => $image] = productWithVariations();
    $image->update(['variation_item_id' => $red->id]);

    patchJson(route('products.images_variation', [$product, $image]), [
        'variation_item_id' => null,
    ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'variation_item_id' => null,
        ]);

    expect($image->fresh()->variation_item_id)->toBeNull();
});

it('rejects variation assignment for items not linked to the product', function () {
    ['product' => $product, 'image' => $image] = productWithVariations();

    $otherVariation = Variation::create(['name' => 'Talla']);
    $otherItem = VariationItem::create(['name' => 'XL', 'variation_id' => $otherVariation->id]);

    patchJson(route('products.images_variation', [$product, $image]), [
        'variation_item_id' => $otherItem->id,
    ])->assertUnprocessable();
});

it('rejects updating an image that belongs to another product', function () {
    ['product' => $product, 'red' => $red] = productWithVariations();

    $otherProduct = Product::factory()
        ->for(BrandFactory::new())
        ->create();

    $foreignImage = $otherProduct->images()->create([
        'path' => 'products/other.jpg',
        'position' => 1,
    ]);

    patchJson(route('products.images_variation', [$product, $foreignImage]), [
        'variation_item_id' => $red->id,
    ])->assertNotFound();
});
