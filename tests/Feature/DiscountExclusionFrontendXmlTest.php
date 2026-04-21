<?php

use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Zone;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/*
|--------------------------------------------------------------------------
| Discount Exclusion – Frontend Views, API & XML Payload Tests
|--------------------------------------------------------------------------
| Verifies that exclude_from_brand_discount and exclude_from_vendor_discount
| flags correctly suppress discounts in:
|   1. The finalPrice accessor (used by all Blade views & components)
|   2. The getActiveTags() auto descuento tag (product/brand only; not vendor-only)
|   3. API JSON responses (ProductsApi, PreciosApi, ProductosApi)
|   4. OrderProduct line creation (percentage field)
|   5. SOAP XML payload (dyn:discount element)
*/

// ──────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────

function buildProduct(array $overrides = []): Product
{
    $defaults = [
        'vendor_discount' => 15,
        'brand_discount'  => 10,
        'product_discount' => 0,
        'product_price'   => 10000,
        'tax_rate'        => 0,
        'exclude_brand'   => false,
        'exclude_vendor'  => false,
    ];
    $c = array_merge($defaults, $overrides);

    $tax = Tax::create(['name' => 'Tax ' . $c['tax_rate'], 'tax' => $c['tax_rate']]);

    $vendor = Vendor::create([
        'name'  => 'V-' . uniqid(),
        'slug'  => 'v-' . uniqid(),
        'minimum_purchase' => 0,
        'minimum_discount_amount' => 0,
        'active' => true,
        'discount' => $c['vendor_discount'],
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
        'vendor_type' => 'Nacional',
    ]);

    $brand = Brand::create([
        'name'  => 'B-' . uniqid(),
        'slug'  => 'b-' . uniqid(),
        'active' => true,
        'discount' => $c['brand_discount'],
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
        'vendor_id' => $vendor->id,
    ]);

    return Product::create([
        'name'  => 'P-' . uniqid(),
        'slug'  => 'p-' . uniqid(),
        'sku'   => 'SKU-' . uniqid(),
        'price' => $c['product_price'],
        'discount' => $c['product_discount'],
        'discount_type' => 'percentage',
        'first_purchase_only' => false,
        'delivery_days' => 5,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'package_quantity' => 1,
        'active' => true,
        'tax_id'   => $tax->id,
        'brand_id' => $brand->id,
        'exclude_from_brand_discount'  => $c['exclude_brand'],
        'exclude_from_vendor_discount' => $c['exclude_vendor'],
        'safety_stock' => 0,
    ]);
}

function freshLoadProduct(int $id): Product
{
    return Product::with(['brand.vendor', 'tax', 'items', 'bonifications'])->find($id);
}

// ══════════════════════════════════════════════════════════════════════════
// 1. finalPrice accessor (drives every Blade view & component)
// ══════════════════════════════════════════════════════════════════════════

it('finalPrice accessor returns no discount when both exclusions set', function () {
    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 15,
        'product_discount' => 0,
        'exclude_brand' => true,
        'exclude_vendor' => true,
    ]);

    $loaded = freshLoadProduct($product->id);
    $fp = $loaded->finalPrice;

    expect($fp['has_discount'])->toBeFalse();
    expect($fp['discount'])->toBe(0);
    expect((float) $fp['price'])->toBe((float) $fp['old']);
});

it('finalPrice accessor returns brand discount when only vendor excluded', function () {
    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 15,
        'exclude_vendor' => true,
    ]);

    $loaded = freshLoadProduct($product->id);
    $fp = $loaded->finalPrice;

    expect($fp['has_discount'])->toBeTrue();
    expect($fp['discount'])->toBe(15);
    expect($fp['discount_on'])->toBe('Marca');
});

it('finalPrice accessor returns vendor discount when only brand excluded', function () {
    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 15,
        'exclude_brand' => true,
    ]);

    $loaded = freshLoadProduct($product->id);
    $fp = $loaded->finalPrice;

    expect($fp['has_discount'])->toBeTrue();
    expect($fp['discount'])->toBe(20);
    expect($fp['discount_on'])->toBe('Vendor');
});

it('finalPrice accessor keeps product discount when brand/vendor excluded', function () {
    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 15,
        'product_discount' => 5,
        'exclude_brand' => true,
        'exclude_vendor' => true,
    ]);

    $loaded = freshLoadProduct($product->id);
    $fp = $loaded->finalPrice;

    expect($fp['has_discount'])->toBeTrue();
    expect($fp['discount'])->toBe(5);
    expect($fp['discount_on'])->toBe('Producto');
});

it('finalPrice computes correct numeric values with exclusion', function () {
    $product = buildProduct([
        'product_price' => 20000,
        'vendor_discount' => 25,
        'brand_discount'  => 10,
        'product_discount' => 0,
        'tax_rate' => 0,
        'exclude_brand' => true,
        'exclude_vendor' => true,
    ]);

    $loaded = freshLoadProduct($product->id);
    $fp = $loaded->finalPrice;

    expect((float) $fp['price'])->toBe(20000.0);
    expect((float) $fp['old'])->toBe(20000.0);
    expect((float) $fp['totalDiscount'])->toBe(0.0);
});

// ══════════════════════════════════════════════════════════════════════════
// 2. getActiveTags – DESCUENTO tag
// ══════════════════════════════════════════════════════════════════════════

it('getActiveTags omits DESCUENTO when brand+vendor excluded and no product discount', function () {
    Setting::updateOrCreate(
        ['key' => 'auto_tag_descuento_enabled'],
        ['name' => 'Auto tag descuento', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_auto_tag_descuento_enabled');

    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 10,
        'product_discount' => 0,
        'exclude_brand' => true,
        'exclude_vendor' => true,
    ]);

    $loaded = freshLoadProduct($product->id);
    $tags = $loaded->getActiveTags();
    $tagTypes = array_column($tags, 'type');

    expect($tagTypes)->not->toContain('auto_descuento');
});

it('getActiveTags includes DESCUENTO when brand+vendor excluded but product has discount', function () {
    Setting::updateOrCreate(
        ['key' => 'auto_tag_descuento_enabled'],
        ['name' => 'Auto tag descuento', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_auto_tag_descuento_enabled');

    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 10,
        'product_discount' => 5,
        'exclude_brand' => true,
        'exclude_vendor' => true,
    ]);

    $loaded = freshLoadProduct($product->id);
    $tags = $loaded->getActiveTags();
    $tagTypes = array_column($tags, 'type');

    expect($tagTypes)->toContain('auto_descuento');
});

it('getActiveTags omits DESCUENTO when only vendor has discount', function () {
    Setting::updateOrCreate(
        ['key' => 'auto_tag_descuento_enabled'],
        ['name' => 'Auto tag descuento', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_auto_tag_descuento_enabled');

    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 0,
        'product_discount' => 0,
        'exclude_brand' => false,
        'exclude_vendor' => false,
    ]);

    $loaded = freshLoadProduct($product->id);
    $tags = $loaded->getActiveTags();
    $tagTypes = array_column($tags, 'type');

    expect($tagTypes)->not->toContain('auto_descuento');
});

it('auto_descuento tag content shows the effective percent discount from product/brand', function () {
    Setting::updateOrCreate(
        ['key' => 'auto_tag_descuento_enabled'],
        ['name' => 'Auto tag descuento', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_auto_tag_descuento_enabled');

    $product = buildProduct([
        'vendor_discount' => 0,
        'brand_discount'  => 18,
        'product_discount' => 0,
    ]);

    $loaded = freshLoadProduct($product->id);
    $tags = $loaded->getActiveTags();
    $descTag = collect($tags)->firstWhere('type', 'auto_descuento');

    expect($descTag)->not->toBeNull();
    expect($descTag['content'])->toBe('-18%');
});

it('getActiveTags omits DESCUENTO when only brand excluded and no other discount source', function () {
    Setting::updateOrCreate(
        ['key' => 'auto_tag_descuento_enabled'],
        ['name' => 'Auto tag descuento', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_auto_tag_descuento_enabled');

    $product = buildProduct([
        'vendor_discount' => 0,
        'brand_discount'  => 15,
        'product_discount' => 0,
        'exclude_brand' => true,
        'exclude_vendor' => false,
    ]);

    $loaded = freshLoadProduct($product->id);
    $tags = $loaded->getActiveTags();
    $tagTypes = array_column($tags, 'type');

    expect($tagTypes)->not->toContain('auto_descuento');
});

it('getActiveTags omits DESCUENTO when only vendor excluded and no other discount source', function () {
    Setting::updateOrCreate(
        ['key' => 'auto_tag_descuento_enabled'],
        ['name' => 'Auto tag descuento', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_auto_tag_descuento_enabled');

    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 0,
        'product_discount' => 0,
        'exclude_brand' => false,
        'exclude_vendor' => true,
    ]);

    $loaded = freshLoadProduct($product->id);
    $tags = $loaded->getActiveTags();
    $tagTypes = array_column($tags, 'type');

    expect($tagTypes)->not->toContain('auto_descuento');
});

it('getActiveTags shows DESCUENTO when vendor excluded but brand still applies', function () {
    Setting::updateOrCreate(
        ['key' => 'auto_tag_descuento_enabled'],
        ['name' => 'Auto tag descuento', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_auto_tag_descuento_enabled');

    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 10,
        'product_discount' => 0,
        'exclude_brand' => false,
        'exclude_vendor' => true,
    ]);

    $loaded = freshLoadProduct($product->id);
    $tags = $loaded->getActiveTags();
    $tagTypes = array_column($tags, 'type');

    expect($tagTypes)->toContain('auto_descuento');
});

it('getActiveTags omits DESCUENTO when brand excluded and only vendor discount applies', function () {
    Setting::updateOrCreate(
        ['key' => 'auto_tag_descuento_enabled'],
        ['name' => 'Auto tag descuento', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_auto_tag_descuento_enabled');

    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 10,
        'product_discount' => 0,
        'exclude_brand' => true,
        'exclude_vendor' => false,
    ]);

    $loaded = freshLoadProduct($product->id);
    $tags = $loaded->getActiveTags();
    $tagTypes = array_column($tags, 'type');

    expect($tagTypes)->not->toContain('auto_descuento');
});

it('auto_descuento tag can diverge from finalPrice when only vendor discount applies', function () {
    Setting::updateOrCreate(
        ['key' => 'auto_tag_descuento_enabled'],
        ['name' => 'Auto tag descuento', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_auto_tag_descuento_enabled');

    $product = buildProduct([
        'vendor_discount' => 15,
        'brand_discount'  => 0,
        'product_discount' => 0,
        'exclude_brand' => false,
        'exclude_vendor' => false,
    ]);

    $loaded = freshLoadProduct($product->id);
    $tags = $loaded->getActiveTags();
    $tagTypes = array_column($tags, 'type');

    expect($loaded->finalPrice['has_discount'])->toBeTrue();
    expect($tagTypes)->not->toContain('auto_descuento');
});

it('auto_descuento tag aligns with finalPrice when product or brand discount exists', function () {
    Setting::updateOrCreate(
        ['key' => 'auto_tag_descuento_enabled'],
        ['name' => 'Auto tag descuento', 'value' => '1', 'show' => false]
    );
    Cache::forget('setting_auto_tag_descuento_enabled');

    $combos = [
        ['exclude_brand' => false, 'exclude_vendor' => false],
        ['exclude_brand' => true,  'exclude_vendor' => false],
        ['exclude_brand' => false, 'exclude_vendor' => true],
        ['exclude_brand' => true,  'exclude_vendor' => true],
    ];

    foreach ($combos as $combo) {
        $product = buildProduct(array_merge([
            'vendor_discount' => 15,
            'brand_discount'  => 10,
            'product_discount' => 5,
        ], $combo));

        $loaded = freshLoadProduct($product->id);
        $tags = $loaded->getActiveTags();
        $tagTypes = array_column($tags, 'type');
        $hasDescuentoTag = in_array('auto_descuento', $tagTypes);
        $hasDiscountInPrice = $loaded->finalPrice['has_discount'];

        expect($hasDescuentoTag)->toBe(
            $hasDiscountInPrice,
            "Mismatch: exclude_brand={$combo['exclude_brand']}, exclude_vendor={$combo['exclude_vendor']} — tag={$hasDescuentoTag}, price={$hasDiscountInPrice}"
        );
    }
});

// ══════════════════════════════════════════════════════════════════════════
// 3. API responses – ProductsApiController
// ══════════════════════════════════════════════════════════════════════════

it('ProductsApi /api/products/latest returns no discount for excluded product', function () {
    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 10,
        'product_discount' => 0,
        'exclude_brand' => true,
        'exclude_vendor' => true,
    ]);

    $product->images()->create(['path' => 'test.jpg']);
    \DB::table('featured_products')->insert([
        'product_id' => $product->id,
        'position' => 1,
    ]);

    $response = getJson('/api/products/latest');
    $response->assertOk();

    $products = $response->json('products');
    $match = collect($products)->firstWhere('id', $product->id);

    expect($match)->not->toBeNull();
    expect($match['final_price']['has_discount'])->toBeFalse();
    expect($match['final_price']['discount'])->toBe(0);
});

// ══════════════════════════════════════════════════════════════════════════
// 4. API responses – PreciosApiController
// ══════════════════════════════════════════════════════════════════════════

it('PreciosApi returns correct final_price for excluded product', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 10,
        'product_discount' => 0,
        'product_price' => 10000,
        'tax_rate' => 0,
        'exclude_brand' => true,
        'exclude_vendor' => true,
    ]);

    $response = getJson('/api/precios?product_ids=' . $product->id);
    $response->assertOk();

    $data = $response->json('data');
    $match = collect($data)->firstWhere('product_id', $product->id);

    expect($match)->not->toBeNull();
    expect($match['final_price']['has_discount'])->toBeFalse();
    expect($match['final_price']['discount'])->toBe(0);
    expect((float) $match['final_price']['price'])->toBe(10000.0);
});

it('PreciosApi returns discount for non-excluded product', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 10,
        'product_discount' => 0,
        'product_price' => 10000,
        'tax_rate' => 0,
        'exclude_brand' => false,
        'exclude_vendor' => false,
    ]);

    $response = getJson('/api/precios?product_ids=' . $product->id);
    $response->assertOk();

    $data = $response->json('data');
    $match = collect($data)->firstWhere('product_id', $product->id);

    expect($match)->not->toBeNull();
    expect($match['final_price']['has_discount'])->toBeTrue();
    expect($match['final_price']['discount'])->toBe(20);
    expect((float) $match['final_price']['price'])->toBe(8000.0);
});

it('PreciosApi price_with_tax is not double-taxed vs final_price when IVA applies', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $product = buildProduct([
        'vendor_discount' => 0,
        'brand_discount' => 0,
        'product_discount' => 0,
        'product_price' => 10000,
        'tax_rate' => 19,
        'exclude_brand' => true,
        'exclude_vendor' => true,
    ]);

    $response = getJson('/api/precios?product_ids=' . $product->id);
    $response->assertOk();

    $data = $response->json('data');
    $match = collect($data)->firstWhere('product_id', $product->id);

    expect($match)->not->toBeNull();
    $final = (float) $match['final_price']['price'];
    expect((float) $match['price_with_tax'])->toBe($final);
    expect($final)->toBe(11900.0);
});

// ══════════════════════════════════════════════════════════════════════════
// 5. API responses – ProductosApiController
// ══════════════════════════════════════════════════════════════════════════

it('ProductosApi returns correct final_price for excluded product', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 10,
        'product_discount' => 0,
        'product_price' => 10000,
        'tax_rate' => 0,
        'exclude_brand' => true,
        'exclude_vendor' => true,
    ]);

    $response = getJson('/api/productos?search=' . $product->sku);
    $response->assertOk();

    $data = $response->json('data');
    $match = collect($data)->firstWhere('id', $product->id);

    expect($match)->not->toBeNull();
    expect($match['final_price']['has_discount'])->toBeFalse();
    expect($match['final_price']['discount'])->toBe(0);
    expect((float) $match['final_price']['price'])->toBe(10000.0);
    expect((float) $match['price'])->toBe((float) $match['final_price']['price']);
    expect((float) $match['base_price'])->toBe(10000.0);
});

// ══════════════════════════════════════════════════════════════════════════
// 6. OrderProduct creation – percentage field
// ══════════════════════════════════════════════════════════════════════════

it('OrderProduct stores percentage=0 when product excluded from brand+vendor discounts', function () {
    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 10,
        'product_discount' => 0,
        'product_price' => 10000,
        'exclude_brand' => true,
        'exclude_vendor' => true,
    ]);

    $loaded = freshLoadProduct($product->id);
    $lineFinal = $loaded->getFinalPriceForUser(false, 100000);
    $lineDiscountPercent = max(0, min(100, (int) ($lineFinal['discount'] ?? 0)));

    $user = User::factory()->create();
    $zone = Zone::create([
        'route' => 'R01',
        'zone' => '001',
        'day' => '1-Lunes',
        'address' => 'Test',
        'code' => 'C001',
        'user_id' => $user->id,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'total' => 10000,
        'discount' => 0,
        'status_id' => Order::STATUS_PENDING,
        'zone_id' => $zone->id,
        'delivery_date' => now()->addDays(3)->format('Y-m-d'),
    ]);

    $orderProduct = OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => $product->price,
        'discount' => 0,
        'percentage' => $lineDiscountPercent,
        'package_quantity' => 1,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
    ]);

    expect($orderProduct->percentage)->toBe(0);
    expect($lineFinal['has_discount'])->toBeFalse();
});

it('OrderProduct stores vendor percentage when product NOT excluded', function () {
    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 10,
        'product_discount' => 0,
        'product_price' => 10000,
        'exclude_brand' => false,
        'exclude_vendor' => false,
    ]);

    $loaded = freshLoadProduct($product->id);
    $lineFinal = $loaded->getFinalPriceForUser(false, 100000);
    $lineDiscountPercent = max(0, min(100, (int) ($lineFinal['discount'] ?? 0)));

    expect($lineDiscountPercent)->toBe(20);
    expect($lineFinal['discount_on'])->toBe('Vendor');
});

// ══════════════════════════════════════════════════════════════════════════
// 7. SOAP XML payload – dyn:discount element
// ══════════════════════════════════════════════════════════════════════════

it('XML payload has discount=0 when product excluded from brand+vendor discounts', function () {
    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 10,
        'product_discount' => 0,
        'product_price' => 10000,
        'exclude_brand' => true,
        'exclude_vendor' => true,
    ]);

    $user = User::factory()->create();
    $zone = Zone::create([
        'route' => 'R01',
        'zone' => '001',
        'day' => '1-Lunes',
        'address' => 'Test',
        'code' => 'C001',
        'user_id' => $user->id,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'total' => 10000,
        'discount' => 0,
        'status_id' => Order::STATUS_PENDING,
        'zone_id' => $zone->id,
        'delivery_date' => now()->addDays(3)->format('Y-m-d'),
    ]);

    $orderProduct = OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => $product->price,
        'discount' => 0,
        'percentage' => 0,
        'package_quantity' => 1,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
    ]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false);

    expect($xml)->not->toBeNull();
    expect($xml)->toContain('<dyn:discount>0</dyn:discount>');
    expect($xml)->not->toMatch('/<dyn:discount>[1-9]\d*<\/dyn:discount>/');
});

it('XML payload has vendor discount when product NOT excluded', function () {
    $product = buildProduct([
        'vendor_discount' => 20,
        'brand_discount'  => 10,
        'product_discount' => 0,
        'product_price' => 10000,
        'exclude_brand' => false,
        'exclude_vendor' => false,
    ]);

    $loaded = freshLoadProduct($product->id);
    $lineFinal = $loaded->getFinalPriceForUser(false, 100000);

    $user = User::factory()->create();
    $zone = Zone::create([
        'route' => 'R01',
        'zone' => '001',
        'day' => '1-Lunes',
        'address' => 'Test',
        'code' => 'C001',
        'user_id' => $user->id,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'total' => 8000,
        'discount' => 2000,
        'status_id' => Order::STATUS_PENDING,
        'zone_id' => $zone->id,
        'delivery_date' => now()->addDays(3)->format('Y-m-d'),
    ]);

    $orderProduct = OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => $product->price,
        'discount' => 0,
        'percentage' => (int) $lineFinal['discount'],
        'package_quantity' => 1,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
    ]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false);

    expect($xml)->not->toBeNull();
    expect($xml)->toContain('<dyn:discount>20</dyn:discount>');
});

// ══════════════════════════════════════════════════════════════════════════
// 8. End-to-end: toggle flags → re-check accessor + XML
// ══════════════════════════════════════════════════════════════════════════

it('toggling exclusion flags immediately removes discount from finalPrice and XML', function () {
    $product = buildProduct([
        'vendor_discount' => 25,
        'brand_discount'  => 15,
        'product_discount' => 0,
        'product_price' => 10000,
        'tax_rate' => 0,
        'exclude_brand' => false,
        'exclude_vendor' => false,
    ]);

    $loaded = freshLoadProduct($product->id);
    expect($loaded->finalPrice['has_discount'])->toBeTrue();
    expect($loaded->finalPrice['discount'])->toBe(25);

    $loaded->update([
        'exclude_from_brand_discount' => true,
        'exclude_from_vendor_discount' => true,
    ]);

    $reloaded = freshLoadProduct($product->id);
    expect($reloaded->finalPrice['has_discount'])->toBeFalse();
    expect($reloaded->finalPrice['discount'])->toBe(0);
    expect((float) $reloaded->finalPrice['price'])->toBe(10000.0);

    $user = User::factory()->create();
    $zone = Zone::create([
        'route' => 'R01',
        'zone' => '001',
        'day' => '1-Lunes',
        'address' => 'Test',
        'code' => 'C001',
        'user_id' => $user->id,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'total' => 10000,
        'discount' => 0,
        'status_id' => Order::STATUS_PENDING,
        'zone_id' => $zone->id,
        'delivery_date' => now()->addDays(3)->format('Y-m-d'),
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => $product->price,
        'discount' => 0,
        'percentage' => 0,
        'package_quantity' => 1,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
    ]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false);
    expect($xml)->toContain('<dyn:discount>0</dyn:discount>');
    expect($xml)->not->toMatch('/<dyn:discount>[1-9]\d*<\/dyn:discount>/');
});

it('re-enabling discounts after exclusion restores discount in finalPrice and XML', function () {
    $product = buildProduct([
        'vendor_discount' => 25,
        'brand_discount'  => 15,
        'product_discount' => 0,
        'product_price' => 10000,
        'tax_rate' => 0,
        'exclude_brand' => true,
        'exclude_vendor' => true,
    ]);

    $loaded = freshLoadProduct($product->id);
    expect($loaded->finalPrice['has_discount'])->toBeFalse();

    $loaded->update([
        'exclude_from_brand_discount' => false,
        'exclude_from_vendor_discount' => false,
    ]);

    $reloaded = freshLoadProduct($product->id);
    expect($reloaded->finalPrice['has_discount'])->toBeTrue();
    expect($reloaded->finalPrice['discount'])->toBe(25);
    expect($reloaded->finalPrice['discount_on'])->toBe('Vendor');

    $user = User::factory()->create();
    $zone = Zone::create([
        'route' => 'R01',
        'zone' => '001',
        'day' => '1-Lunes',
        'address' => 'Test',
        'code' => 'C001',
        'user_id' => $user->id,
    ]);

    $order = Order::create([
        'user_id' => $user->id,
        'total' => 7500,
        'discount' => 2500,
        'status_id' => Order::STATUS_PENDING,
        'zone_id' => $zone->id,
        'delivery_date' => now()->addDays(3)->format('Y-m-d'),
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'price' => $product->price,
        'discount' => 0,
        'percentage' => 25,
        'package_quantity' => 1,
        'discount_type' => 'percentage',
        'flat_discount_amount' => 0,
    ]);

    $xml = OrderRepository::buildOrderXmlForDiagnostic($order, false);
    expect($xml)->toContain('<dyn:discount>25</dyn:discount>');
});
