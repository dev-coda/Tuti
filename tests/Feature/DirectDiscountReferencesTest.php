<?php

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\VolumeDiscount;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function directDiscountAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

it('searches product references by sku for a direct discount', function () {
    $admin = directDiscountAdmin();
    $match = Product::factory()->create(['sku' => 'REF-DIRECT-1', 'name' => 'Jabon especial']);
    Product::factory()->create(['sku' => 'OTHER-9', 'name' => 'Otro producto']);

    $response = actingAs($admin)->getJson(route('promociones.elements', [
        'type' => 'products',
        'search' => 'REF-DIRECT-1',
    ]));

    $response->assertOk();
    $ids = collect($response->json())->pluck('id');
    expect($ids)->toContain($match->id);
    expect($response->json('0.description'))->toBe('Referencia: REF-DIRECT-1');
});

it('applies a direct discount to selected references and shows the discount label', function () {
    Setting::updateOrCreate(
        ['key' => 'auto_tag_descuento_enabled'],
        ['name' => 'Auto tag descuento', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_auto_tag_descuento_enabled');

    $admin = directDiscountAdmin();
    $selected = Product::factory()->create(['sku' => 'REF-APPLY', 'discount' => 0, 'price' => 10000]);
    $untouched = Product::factory()->create(['sku' => 'REF-SKIP', 'discount' => 0, 'price' => 10000]);

    actingAs($admin)->post(route('promociones.descuento-directo.store'), [
        'discount_type' => 'percentage',
        'discount' => 12,
        'product_ids' => [$selected->id],
    ])->assertRedirect();

    expect((float) $selected->fresh()->discount)->toBe(12.0);
    expect((float) $untouched->fresh()->discount)->toBe(0.0);

    $loaded = Product::with(['brand.vendor', 'tax', 'items', 'bonifications'])->find($selected->id);
    $tag = collect($loaded->getActiveTags())->firstWhere('type', 'auto_descuento');

    expect($tag)->not->toBeNull();
    expect($tag['content'])->toBe('-12%');
});

it('stores volume discount product references sent as a json string', function () {
    $admin = directDiscountAdmin();
    $product = Product::factory()->create();

    actingAs($admin)->post(route('volume-discounts.store'), [
        'name' => 'Volumen referencias',
        'discount_type' => 'percentage',
        'discount_value' => 8,
        'min_quantity' => 2,
        'applies_to' => 'products',
        'applies_to_ids' => json_encode([$product->id]),
        'valid_from' => now()->subDay()->format('Y-m-d H:i:s'),
        'valid_to' => now()->addMonth()->format('Y-m-d H:i:s'),
        'active' => 1,
    ])->assertRedirect(route('promociones.descuento-volumen'));

    $discount = VolumeDiscount::where('name', 'Volumen referencias')->first();
    expect($discount)->not->toBeNull();
    expect($discount->applies_to_ids)->toBe([$product->id]);
    expect($discount->products()->pluck('products.id')->all())->toBe([$product->id]);
});
