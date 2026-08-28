<?php

use App\Models\Product;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Setting::updateOrCreate(['key' => 'auto_tag_nuevo_enabled'], ['value' => '1', 'name' => 'auto_tag_nuevo_enabled', 'show' => false]);
    Setting::updateOrCreate(['key' => 'auto_tag_descuento_enabled'], ['value' => '1', 'name' => 'auto_tag_descuento_enabled', 'show' => false]);
    \Illuminate\Support\Facades\Cache::flush();
});

it('keeps product criteria when updating a tag from the edit form', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $productA = Product::factory()->create(['sku' => 'LYESPODOUSX24', 'name' => 'Esponja']);
    $productB = Product::factory()->create(['sku' => 'LYOTHER', 'name' => 'Otro']);

    $tag = Tag::create([
        'content' => 'Ultimas Unidades',
        'priority' => 10,
        'enabled' => true,
    ]);
    $tag->products()->sync([$productA->id]);

    actingAs($admin);

    put(route('tags.update', $tag), [
        'content' => 'Ultimas Unidades',
        'priority' => 10,
        'enabled' => 1,
        'product_ids' => [$productA->id, $productB->id],
    ])->assertRedirect(route('tags.index'));

    $tag->refresh()->load('products');

    expect($tag->products->pluck('id')->sort()->values()->all())
        ->toBe(collect([$productA->id, $productB->id])->sort()->values()->all());
});

it('always surfaces a manual tag even when auto discount and nuevo also apply', function () {
    $product = Product::factory()->create([
        'sku' => 'LYESPODOUSX24',
        'discount' => 5,
        'created_at' => now()->subDays(2),
    ]);

    $tag = Tag::create([
        'content' => 'Ultimas Unidades',
        'priority' => 999,
        'enabled' => true,
    ]);
    $tag->products()->sync([$product->id]);

    $product->load(['categories', 'brand', 'bonifications']);
    $active = collect($product->getActiveTags());

    expect($active->pluck('type')->all())->toContain('manual')
        ->and($active->firstWhere('type', 'manual')['content'])->toBe('Ultimas Unidades')
        ->and($active)->toHaveCount(2);
});

it('does not show disabled manual tags', function () {
    $product = Product::factory()->create(['sku' => 'LYDETE1000ML']);

    $tag = Tag::create([
        'content' => 'Ultimas Unidades',
        'priority' => 10,
        'enabled' => false,
    ]);
    $tag->products()->sync([$product->id]);

    $product->load(['categories', 'brand', 'bonifications']);

    expect(collect($product->getActiveTags())->firstWhere('type', 'manual'))->toBeNull();
});
