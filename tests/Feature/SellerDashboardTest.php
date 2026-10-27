<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'supernumerario', 'guard_name' => 'web']);
});

function sellerDashVendor(string $name, string $slug): Vendor
{
    return Vendor::create([
        'name' => $name,
        'slug' => $slug,
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
}

function sellerDashBrand(Vendor $vendor, string $name, string $slug): Brand
{
    return Brand::create([
        'name' => $name,
        'slug' => $slug,
        'vendor_id' => $vendor->id,
    ]);
}

function sellerDashCategory(string $name, string $slug, ?int $parentId = null): Category
{
    return Category::create([
        'name' => $name,
        'slug' => $slug,
        'parent_id' => $parentId,
    ]);
}

function sellerDashProduct(Brand $brand, Tax $tax, string $name, string $sku, ?Category $category = null): Product
{
    $product = Product::create([
        'name' => $name,
        'description' => 'd',
        'short_description' => 'd',
        'sku' => $sku,
        'slug' => $sku,
        'active' => 1,
        'price' => 1000,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $brand->id,
        'package_quantity' => 1,
    ]);

    if ($category) {
        $product->categories()->attach($category->id);
    }

    return $product;
}

function sellerDashLine(Order $order, Product $product, int $quantity, float $price): OrderProduct
{
    return OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'price' => $price,
        'discount' => 0,
        'package_quantity' => 1,
    ]);
}

function sellerDashStaff(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('includes a TRONEX Otros brand sales bucket on the seller dashboard', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-29 15:00:00', 'America/Bogota'));

    $seller = sellerDashStaff('seller');
    $client = User::factory()->create();

    $tax = Tax::create(['name' => 'IVA dash', 'tax' => 0]);
    $vendor = sellerDashVendor('Tronex Vendor', 'tronex-vendor-dash');
    $tronexBrand = sellerDashBrand($vendor, 'Tronex', 'tronex-brand-dash');
    $gpBrand = sellerDashBrand($vendor, 'Gp', 'gp-brand-dash');

    // Outside Alcalina/Manganeso — other TRONEX lines belong in TRONEX Otros.
    $pilasMoneda = sellerDashCategory('Pilas Moneda', 'pilas-moneda-dash');

    $tronexProduct = sellerDashProduct($tronexBrand, $tax, 'Pila Moneda Tronex', 'TRONEX-COIN-1', $pilasMoneda);
    $gpProduct = sellerDashProduct($gpBrand, $tax, 'Pila Moneda GP', 'GP-COIN-1', $pilasMoneda);

    $order = Order::create([
        'user_id' => $client->id,
        'seller_id' => $seller->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 3500,
        'discount' => 0,
    ]);

    sellerDashLine($order, $tronexProduct, 2, 1000);
    sellerDashLine($order, $gpProduct, 3, 500);

    actingAs($seller);

    $response = getJson(route('api.seller.dashboard', [
        'from_date' => '2026-07-29',
        'to_date' => '2026-07-29',
    ]));

    $response->assertOk();

    $buckets = collect($response->json('sales_buckets'));
    expect($buckets->pluck('label')->all())->toBe([
        'Alkalina',
        'Manganeso',
        'Encendedores',
        'Bombillos',
        'TRONEX Otros',
        'Otros',
        'TERCEROS',
    ]);

    $tronex = $buckets->firstWhere('label', 'TRONEX Otros');
    $otros = $buckets->firstWhere('label', 'Otros');

    expect((float) $tronex['total'])->toBe(2000.0)
        ->and((int) $tronex['quantity'])->toBe(2)
        ->and((float) $otros['total'])->toBe(1500.0)
        ->and((int) $otros['quantity'])->toBe(3);

    Carbon::setTestNow();
});

it('maps seller dashboard buckets by TRONEX categories and intended brands without double-counting', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-29 15:00:00', 'America/Bogota'));

    $seller = sellerDashStaff('seller');
    $client = User::factory()->create();
    $tax = Tax::create(['name' => 'IVA buckets', 'tax' => 0]);

    $tronexVendor = sellerDashVendor('Tronex', 'vendor-tronex-buckets');
    $otrosVendor = sellerDashVendor('Otros Vendor', 'vendor-otros-buckets');
    $eternaVendor = sellerDashVendor('Eterna', 'vendor-eterna-buckets');
    $prebelVendor = sellerDashVendor('PREBEL', 'vendor-prebel-buckets');

    $tronexBrand = sellerDashBrand($tronexVendor, 'TRONEX', 'brand-tronex-buckets');
    $gpBrand = sellerDashBrand($otrosVendor, 'GP', 'brand-gp-buckets');
    $geBrand = sellerDashBrand($otrosVendor, 'General Electric', 'brand-ge-buckets');
    $rocketBrand = sellerDashBrand($otrosVendor, 'Rocket', 'brand-rocket-buckets');
    $mtekBrand = sellerDashBrand($otrosVendor, 'Mtek', 'brand-mtek-buckets');
    $swissliteBrand = sellerDashBrand($otrosVendor, 'SWISSLITE', 'brand-swisslite-buckets');
    $clipperBrand = sellerDashBrand($otrosVendor, 'Clipper', 'brand-clipper-buckets');
    $santaBlancaBrand = sellerDashBrand($otrosVendor, 'Santa Blanca', 'brand-santa-blanca-buckets');
    $fegiBrand = sellerDashBrand($prebelVendor, 'FEGI', 'brand-fegi-buckets');
    $mcmBrand = sellerDashBrand($prebelVendor, 'MCM', 'brand-mcm-buckets');
    $ingreoBrand = sellerDashBrand($prebelVendor, 'INGREO', 'brand-ingreo-buckets');
    $eternaHouseBrand = sellerDashBrand($eternaVendor, 'Linea Casa', 'brand-eterna-house-buckets');

    $alcalinasTronex = sellerDashCategory('Alcalinas Tronex', 'cat-alcalinas-tronex');
    $alkalinaChild = sellerDashCategory('AA Alkalina', 'cat-aa-alkalina', $alcalinasTronex->id);
    $manganeso = sellerDashCategory('Manganeso Tronex', 'cat-manganeso-tronex');
    $encendedoresCat = sellerDashCategory('Encendedores', 'cat-encendedores');
    $bombillosCat = sellerDashCategory('Bombillos', 'cat-bombillos');
    $pilasMoneda = sellerDashCategory('Pilas Moneda', 'cat-pilas-moneda');

    $tronexAlcalina = sellerDashProduct($tronexBrand, $tax, 'Tronex Alcalina', 'SKU-TRX-ALC', $alcalinasTronex);
    $tronexAlkalinaChild = sellerDashProduct($tronexBrand, $tax, 'Tronex Alkalina child', 'SKU-TRX-ALK-CHILD', $alkalinaChild);
    $tronexBothCats = sellerDashProduct($tronexBrand, $tax, 'Tronex both cats', 'SKU-TRX-BOTH');
    $tronexBothCats->categories()->attach([$alcalinasTronex->id, $manganeso->id]);
    $tronexManganeso = sellerDashProduct($tronexBrand, $tax, 'Tronex Manganeso', 'SKU-TRX-MANG', $manganeso);
    $tronexOther = sellerDashProduct($tronexBrand, $tax, 'Tronex linterna', 'SKU-TRX-OTHER', $pilasMoneda);
    $tronexInEncendedoresCat = sellerDashProduct($tronexBrand, $tax, 'Tronex encendedor', 'SKU-TRX-ENC', $encendedoresCat);

    $gpInAlcalina = sellerDashProduct($gpBrand, $tax, 'GP Alcalina', 'SKU-GP-ALC', $alcalinasTronex);
    $geProduct = sellerDashProduct($geBrand, $tax, 'GE bombillo', 'SKU-GE-1', $bombillosCat);
    $rocketProduct = sellerDashProduct($rocketBrand, $tax, 'Rocket pila', 'SKU-ROCKET-1', $pilasMoneda);
    $mtekProduct = sellerDashProduct($mtekBrand, $tax, 'Mtek leftover', 'SKU-MTEK-1', $pilasMoneda);

    $swisslite = sellerDashProduct($swissliteBrand, $tax, 'Swisslite', 'SKU-SWISS-1', $encendedoresCat);
    $clipper = sellerDashProduct($clipperBrand, $tax, 'Clipper', 'SKU-CLIP-1');
    $santaBlanca = sellerDashProduct($santaBlancaBrand, $tax, 'Santa Blanca', 'SKU-SB-1');

    $fegi = sellerDashProduct($fegiBrand, $tax, 'Fegi', 'SKU-FEGI-1');
    $mcm = sellerDashProduct($mcmBrand, $tax, 'MCM', 'SKU-MCM-1');
    $ingreo = sellerDashProduct($ingreoBrand, $tax, 'Ingreo', 'SKU-INGREO-1');
    $eternaHouse = sellerDashProduct($eternaHouseBrand, $tax, 'Eterna house', 'SKU-ETERNA-1');

    $order = Order::create([
        'user_id' => $client->id,
        'seller_id' => $seller->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 14350,
        'discount' => 0,
    ]);

    // qty * price (tax 0) — one quantity each so totals equal prices.
    sellerDashLine($order, $tronexAlcalina, 1, 100);
    sellerDashLine($order, $tronexAlkalinaChild, 1, 200);
    sellerDashLine($order, $tronexBothCats, 1, 300);
    sellerDashLine($order, $tronexManganeso, 1, 400);
    sellerDashLine($order, $tronexOther, 1, 500);
    sellerDashLine($order, $tronexInEncendedoresCat, 1, 600);
    sellerDashLine($order, $gpInAlcalina, 1, 700);
    sellerDashLine($order, $geProduct, 1, 800);
    sellerDashLine($order, $rocketProduct, 1, 900);
    sellerDashLine($order, $mtekProduct, 1, 50);
    sellerDashLine($order, $swisslite, 1, 1100);
    sellerDashLine($order, $clipper, 1, 1200);
    sellerDashLine($order, $santaBlanca, 1, 1300);
    sellerDashLine($order, $fegi, 1, 1400);
    sellerDashLine($order, $mcm, 1, 1500);
    sellerDashLine($order, $ingreo, 1, 1600);
    sellerDashLine($order, $eternaHouse, 1, 1700);

    actingAs($seller);

    $response = getJson(route('api.seller.dashboard', [
        'from_date' => '2026-07-29',
        'to_date' => '2026-07-29',
    ]));

    $response->assertOk()
        ->assertJsonPath('total_pedidos', 1)
        ->assertJsonPath('from_date', '2026-07-29')
        ->assertJsonPath('to_date', '2026-07-29');

    expect((float) $response->json('ventas_totales'))->toBe(14350.0)
        ->and((float) $response->json('ticket_promedio'))->toBe(14350.0);

    $buckets = collect($response->json('sales_buckets'))->keyBy('label');

    expect((float) $buckets['Alkalina']['total'])->toBe(600.0)
        ->and((int) $buckets['Alkalina']['quantity'])->toBe(3)
        ->and((float) $buckets['Manganeso']['total'])->toBe(400.0)
        ->and((int) $buckets['Manganeso']['quantity'])->toBe(1)
        ->and((float) $buckets['Encendedores']['total'])->toBe(2300.0)
        ->and((int) $buckets['Encendedores']['quantity'])->toBe(2)
        ->and((float) $buckets['Bombillos']['total'])->toBe(1300.0)
        ->and((int) $buckets['Bombillos']['quantity'])->toBe(1)
        ->and((float) $buckets['TRONEX Otros']['total'])->toBe(1100.0)
        ->and((int) $buckets['TRONEX Otros']['quantity'])->toBe(2)
        ->and((float) $buckets['Otros']['total'])->toBe(2400.0)
        ->and((int) $buckets['Otros']['quantity'])->toBe(3)
        ->and((float) $buckets['TERCEROS']['total'])->toBe(6200.0)
        ->and((int) $buckets['TERCEROS']['quantity'])->toBe(4);

    $bucketTotal = $buckets->sum(fn ($bucket) => (float) $bucket['total']);
    expect($bucketTotal)->toBe(14300.0)
        ->and($bucketTotal)->toBeLessThan((float) $response->json('ventas_totales'));

    Carbon::setTestNow();
});

it('keeps seller dashboard access for supervisor and supernumerario staff', function (string $role) {
    Carbon::setTestNow(Carbon::parse('2026-07-29 15:00:00', 'America/Bogota'));

    $staff = sellerDashStaff($role);

    actingAs($staff);

    $response = getJson(route('api.seller.dashboard', [
        'from_date' => '2026-07-29',
        'to_date' => '2026-07-29',
    ]));

    $response->assertOk()
        ->assertJsonPath('total_pedidos', 0)
        ->assertJsonPath('from_date', '2026-07-29')
        ->assertJsonPath('to_date', '2026-07-29');

    expect(collect($response->json('sales_buckets'))->pluck('label')->all())->toBe([
        'Alkalina',
        'Manganeso',
        'Encendedores',
        'Bombillos',
        'TRONEX Otros',
        'Otros',
        'TERCEROS',
    ]);

    Carbon::setTestNow();
})->with(['seller', 'supervisor', 'supernumerario']);

it('rejects the seller dashboard for non-staff users', function () {
    $client = User::factory()->create();

    actingAs($client);

    getJson(route('api.seller.dashboard'))->assertForbidden();
});
