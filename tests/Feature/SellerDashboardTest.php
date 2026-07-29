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
});

it('includes a Tronex brand sales bucket on the seller dashboard', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-29 15:00:00', 'America/Bogota'));

    $seller = User::factory()->create();
    $seller->assignRole('seller');
    $client = User::factory()->create();

    $tax = Tax::create(['name' => 'IVA dash', 'tax' => 0]);
    $vendor = Vendor::create([
        'name' => 'Tronex Vendor',
        'slug' => 'tronex-vendor-dash',
        'minimum_purchase' => 0,
        'active' => 1,
    ]);
    $tronexBrand = Brand::create([
        'name' => 'Tronex',
        'slug' => 'tronex-brand-dash',
        'vendor_id' => $vendor->id,
    ]);
    $gpBrand = Brand::create([
        'name' => 'Gp',
        'slug' => 'gp-brand-dash',
        'vendor_id' => $vendor->id,
    ]);

    // Outside Alcalina/Manganeso/Bombillos — previously untallied for brand Tronex.
    $pilasMoneda = Category::create(['name' => 'Pilas Moneda', 'slug' => 'pilas-moneda-dash']);

    $tronexProduct = Product::create([
        'name' => 'Pila Moneda Tronex',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'TRONEX-COIN-1',
        'slug' => 'tronex-coin-dash',
        'active' => 1,
        'price' => 1000,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $tronexBrand->id,
        'package_quantity' => 1,
    ]);
    $tronexProduct->categories()->attach($pilasMoneda->id);

    $gpProduct = Product::create([
        'name' => 'Pila Moneda GP',
        'description' => 'd',
        'short_description' => 'd',
        'sku' => 'GP-COIN-1',
        'slug' => 'gp-coin-dash',
        'active' => 1,
        'price' => 500,
        'delivery_days' => 1,
        'discount' => 0,
        'quantity_min' => 1,
        'quantity_max' => 100,
        'step' => 1,
        'tax_id' => $tax->id,
        'brand_id' => $gpBrand->id,
        'package_quantity' => 1,
    ]);
    $gpProduct->categories()->attach($pilasMoneda->id);

    $order = Order::create([
        'user_id' => $client->id,
        'seller_id' => $seller->id,
        'status_id' => Order::STATUS_PENDING,
        'total' => 3500,
        'discount' => 0,
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $tronexProduct->id,
        'quantity' => 2,
        'price' => 1000,
        'discount' => 0,
        'package_quantity' => 1,
    ]);
    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $gpProduct->id,
        'quantity' => 3,
        'price' => 500,
        'discount' => 0,
        'package_quantity' => 1,
    ]);

    actingAs($seller);

    $response = getJson(route('api.seller.dashboard', [
        'from_date' => '2026-07-29',
        'to_date' => '2026-07-29',
    ]));

    $response->assertOk();

    $buckets = collect($response->json('sales_buckets'));
    expect($buckets->pluck('label')->all())->toBe([
        'Alcalina',
        'Manganeso',
        'Encendedores',
        'Bombillos',
        'Tronex',
        'Otros',
        'Terceros',
    ]);

    $tronex = $buckets->firstWhere('label', 'Tronex');
    $otros = $buckets->firstWhere('label', 'Otros');

    expect((float) $tronex['total'])->toBe(2000.0)
        ->and((int) $tronex['quantity'])->toBe(2)
        ->and((float) $otros['total'])->toBe(1500.0)
        ->and((int) $otros['quantity'])->toBe(3);

    Carbon::setTestNow();
});
