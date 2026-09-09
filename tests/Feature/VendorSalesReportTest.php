<?php

use App\Exports\VendorSalesExport;
use App\Models\Bonification;
use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderProductBonification;
use App\Models\Product;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->withoutVite();
});

function vendorSalesAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function vendorSalesOrder(User $client, int $status, Carbon $createdAt): Order
{
    $order = Order::create([
        'user_id' => $client->id,
        'status_id' => $status,
        'total' => 0,
        'discount' => 0,
    ]);
    $order->created_at = $createdAt;
    $order->updated_at = $createdAt;
    $order->save();

    return $order;
}

function vendorSalesWorkbook(string $dateFrom, string $dateTo, array $vendorIds): array
{
    $binary = Excel::raw(
        new VendorSalesExport($dateFrom, $dateTo, $vendorIds),
        \Maatwebsite\Excel\Excel::XLSX
    );

    $path = tempnam(sys_get_temp_dir(), 'vendor-sales-');
    file_put_contents($path, $binary);
    $spreadsheet = IOFactory::load($path);
    @unlink($path);

    $sheets = [];
    foreach ($spreadsheet->getAllSheets() as $sheet) {
        $sheets[$sheet->getTitle()] = $sheet->toArray(null, true, true, false);
    }

    return $sheets;
}

it('defaults the vendor sales report to last month in Colombia and only exact ETERNA', function () {
    actingAs(vendorSalesAdmin());

    $eterna = Vendor::factory()->create(['name' => 'ETERNA']);
    $lookalike = Vendor::factory()->create(['name' => 'ETERNA PLUS']);

    $lastMonth = now(VendorSalesExport::reportTimezone())->subMonthNoOverflow();

    $html = get(route('admin.reports.vendor-sales'))
        ->assertOk()
        ->assertSee('Ventas por proveedor')
        ->getContent();

    expect($html)
        ->toContain('value="'.$lastMonth->copy()->startOfMonth()->format('Y-m-d').'"')
        ->toContain('value="'.$lastMonth->copy()->endOfMonth()->format('Y-m-d').'"')
        ->toContain('value="'.$eterna->id.'"')
        ->toContain('value="'.$lookalike->id.'"');

    expect($html)->toMatch('/value="'.$eterna->id.'"[^>]*checked/');
    expect($html)->not->toMatch('/value="'.$lookalike->id.'"[^>]*checked/');
});

it('rejects guests and non-admins and requires a vendor', function () {
    get(route('admin.reports.vendor-sales'))->assertRedirect();

    $seller = User::factory()->create();
    Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
    $seller->assignRole('seller');

    actingAs($seller);
    $denied = get(route('admin.reports.vendor-sales'));
    expect($denied->status())->not->toBe(200);
    expect($denied->getContent())->not->toContain('Descargar Excel');

    actingAs(vendorSalesAdmin());
    get(route('admin.reports.vendor-sales.export', [
        'date_from' => '2026-08-01',
        'date_to' => '2026-08-31',
    ]))->assertSessionHasErrors('vendor_ids');
});

it('counts shipped units, tax, bonifications, vendors, statuses, and Colombia date bounds', function () {
    $eterna = Vendor::factory()->create(['name' => 'ETERNA']);
    $other = Vendor::factory()->create(['name' => 'ACME']);

    $eternaBrand = Brand::factory()->create(['vendor_id' => $eterna->id, 'name' => 'Marca Eterna']);
    $otherBrand = Brand::factory()->create(['vendor_id' => $other->id, 'name' => 'Marca Otro']);

    $tax = Tax::factory()->create(['tax' => 19]);

    $unitProduct = Product::factory()->create([
        'brand_id' => $eternaBrand->id,
        'name' => 'Producto unidad',
        'sku' => 'ET-UNIT',
        'package_quantity' => 12,
        'calculate_package_price' => false,
        'tax_id' => $tax->id,
    ]);
    $packProduct = Product::factory()->create([
        'brand_id' => $eternaBrand->id,
        'name' => 'Producto empaque',
        'sku' => 'ET-PACK',
        'package_quantity' => 6,
        'calculate_package_price' => true,
        'tax_id' => $tax->id,
    ]);
    $gift = Product::factory()->create([
        'brand_id' => $eternaBrand->id,
        'name' => 'Producto regalado',
        'sku' => 'ET-GIFT',
        'tax_id' => $tax->id,
    ]);
    $otherProduct = Product::factory()->create([
        'brand_id' => $otherBrand->id,
        'name' => 'Producto ajeno',
        'sku' => 'OT-1',
        'tax_id' => $tax->id,
    ]);
    $otherGift = Product::factory()->create([
        'brand_id' => $otherBrand->id,
        'name' => 'Regalo ajeno',
        'sku' => 'OT-GIFT',
        'tax_id' => $tax->id,
    ]);

    $client = User::factory()->create(['name' => 'Cliente Uno', 'document' => '900']);
    $tz = VendorSalesExport::reportTimezone();

    // Inside August in Colombia, even if the UTC date is already September.
    $inside = Carbon::parse('2026-09-01 04:30:00', 'UTC'); // 2026-08-31 23:30 Bogota
    $before = Carbon::parse('2026-08-01 04:30:00', 'UTC'); // 2026-07-31 23:30 Bogota
    $after = Carbon::parse('2026-09-01 05:30:00', 'UTC'); // 2026-09-01 00:30 Bogota

    $order = vendorSalesOrder($client, Order::STATUS_SHIPPED, $inside);

    $unitLine = OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $unitProduct->id,
        'quantity' => 2,
        'price' => 1000,
        'discount' => 0,
        'percentage' => 10,
        'package_quantity' => 12,
        'discount_type' => 'percentage',
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $packProduct->id,
        'quantity' => 3,
        'price' => 6000,
        'discount' => 0,
        'percentage' => 0,
        'package_quantity' => 6,
        'discount_type' => 'fixed_amount',
        'flat_discount_amount' => 100,
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $otherProduct->id,
        'quantity' => 4,
        'price' => 500,
        'discount' => 0,
        'package_quantity' => 1,
    ]);

    $bonification = Bonification::create([
        'name' => 'Compra 10 lleva 1',
        'buy' => 10,
        'get' => 1,
    ]);

    OrderProductBonification::create([
        'order_product_id' => $unitLine->id,
        'bonification_id' => $bonification->id,
        'product_id' => $gift->id,
        'quantity' => 2,
        'order_id' => $order->id,
    ]);

    OrderProductBonification::create([
        'order_product_id' => $unitLine->id,
        'bonification_id' => $bonification->id,
        'product_id' => $otherGift->id,
        'quantity' => 9,
        'order_id' => $order->id,
    ]);

    foreach ([$before, $after] as $stamp) {
        $outside = vendorSalesOrder($client, Order::STATUS_DELIVERED, $stamp);
        OrderProduct::create([
            'order_id' => $outside->id,
            'product_id' => $unitProduct->id,
            'quantity' => 99,
            'price' => 1000,
            'discount' => 0,
            'package_quantity' => 1,
        ]);
        OrderProductBonification::create([
            'order_product_id' => $unitLine->id,
            'bonification_id' => $bonification->id,
            'product_id' => $gift->id,
            'quantity' => 7,
            'order_id' => $outside->id,
        ]);
    }

    foreach ([
        Order::STATUS_PENDING,
        Order::STATUS_CANCELLED,
        Order::STATUS_ERROR,
        Order::STATUS_ERROR_WEBSERVICE,
        Order::STATUS_WAITING,
        Order::STATUS_DRAFT,
    ] as $status) {
        $excluded = vendorSalesOrder($client, $status, $inside);
        OrderProduct::create([
            'order_id' => $excluded->id,
            'product_id' => $unitProduct->id,
            'quantity' => 50,
            'price' => 1000,
            'discount' => 0,
            'package_quantity' => 1,
        ]);
    }

    $delivered = vendorSalesOrder($client, Order::STATUS_DELIVERED, Carbon::parse('2026-08-15 15:00:00', 'UTC'));
    OrderProduct::create([
        'order_id' => $delivered->id,
        'product_id' => $unitProduct->id,
        'quantity' => 1,
        'price' => 1000,
        'discount' => 0,
        'percentage' => 0,
        'package_quantity' => 12,
        'discount_type' => 'percentage',
    ]);

    $dateFrom = '2026-08-01';
    $dateTo = '2026-08-31';

    $export = new VendorSalesExport($dateFrom, $dateTo, [$eterna->id]);
    $sales = $export->salesQuery()->get();

    expect($sales)->toHaveCount(3);

    $unitRows = $sales->where('product_id', $unitProduct->id);
    expect($unitRows)->toHaveCount(2)
        ->and(VendorSalesExport::units($unitLine->fresh(['product'])))->toBe(2.0)
        ->and(VendorSalesExport::lineNet($unitLine->fresh(['product'])))->toBe(21600.0);

    $packLine = $sales->firstWhere('product_id', $packProduct->id);
    expect(VendorSalesExport::units($packLine))->toBe(18.0)
        ->and(VendorSalesExport::lineNet($packLine))->toBe(16200.0)
        ->and(VendorSalesExport::lineNetWithTax($packLine))->toBe(19278.0);

    $bonuses = $export->bonificationsQuery()->get();
    expect($bonuses)->toHaveCount(1)
        ->and($bonuses->first()->product_id)->toBe($gift->id)
        ->and((float) $bonuses->first()->quantity)->toBe(2.0);

    $otherOnly = (new VendorSalesExport($dateFrom, $dateTo, [$other->id]))->salesQuery()->get();
    expect($otherOnly)->toHaveCount(1)
        ->and($otherOnly->first()->product_id)->toBe($otherProduct->id);

    $both = (new VendorSalesExport($dateFrom, $dateTo, [$eterna->id, $other->id]))->salesQuery()->get();
    expect($both)->toHaveCount(4);

    $summary = $export->summary();
    $unitSummary = $summary->first(fn ($row) => $row[2] === 'ET-UNIT');
    $packSummary = $summary->first(fn ($row) => $row[2] === 'ET-PACK');
    $giftSummary = $summary->first(fn ($row) => $row[2] === 'ET-GIFT');

    expect($unitSummary[4])->toBe(3.0)
        ->and($unitSummary[5])->toBe(33600.0)
        ->and($unitSummary[7])->toBe(0.0)
        ->and($packSummary[4])->toBe(18.0)
        ->and($packSummary[5])->toBe(16200.0)
        ->and($packSummary[6])->toBe(19278.0)
        ->and($giftSummary[7])->toBe(2.0);

    $sheets = vendorSalesWorkbook($dateFrom, $dateTo, [$eterna->id]);

    expect(array_keys($sheets))->toBe(['Ventas', 'Bonificaciones', 'Pedidos completos', 'Resumen']);

    $salesSheet = $sheets['Ventas'];
    expect($salesSheet[0][18])->toBe('Valor neto antes de IVA')
        ->and($salesSheet[0][20])->toBe('Valor con IVA')
        ->and(collect($salesSheet)->pluck(10))->toContain('ET-UNIT')
        ->and(collect($salesSheet)->pluck(10))->toContain('ET-PACK')
        ->and(collect($salesSheet)->pluck(10))->not->toContain('OT-1');

    $unitExcel = collect($salesSheet)->first(fn ($row) => $row[10] === 'ET-UNIT' && (float) $row[14] === 2.0);
    expect($unitExcel[0])->toBe(
        Carbon::parse('2026-09-01 04:30:00', 'UTC')->timezone($tz)->format('Y-m-d H:i:s')
    );
    expect((float) $unitExcel[15])->toBe(2.0)
        ->and((float) $unitExcel[18])->toBe(21600.0)
        ->and((float) $unitExcel[20])->toBe(25704.0);

    $bonusSheet = $sheets['Bonificaciones'];
    expect(collect($bonusSheet)->pluck(11))->toContain('Producto regalado')
        ->and(collect($bonusSheet)->pluck(11))->not->toContain('Regalo ajeno');
    expect((float) collect($bonusSheet)->first(fn ($row) => $row[11] === 'Producto regalado')[13])->toBe(2.0);

    $fullSheet = $sheets['Pedidos completos'];
    $fullSkus = collect($fullSheet)->pluck(10);
    expect($fullSkus)->toContain('ET-UNIT')
        ->and($fullSkus)->toContain('ET-PACK')
        ->and($fullSkus)->toContain('OT-1')
        ->and($fullSkus)->toContain('ET-GIFT')
        ->and($fullSkus)->toContain('OT-GIFT');

    $mixedOrderId = $order->id;
    $mixedRows = collect($fullSheet)->filter(fn ($row) => (int) $row[1] === $mixedOrderId);
    expect($mixedRows)->toHaveCount(5);

    $otherVendorLine = $mixedRows->first(fn ($row) => $row[10] === 'OT-1');
    expect($otherVendorLine[13])->toBe('Venta')
        ->and($otherVendorLine[14])->toBe('No');

    $selectedLine = $mixedRows->first(fn ($row) => $row[10] === 'ET-UNIT');
    expect($selectedLine[14])->toBe('Sí');

    $otherGiftLine = $mixedRows->first(fn ($row) => $row[10] === 'OT-GIFT');
    expect($otherGiftLine[13])->toBe('Bonificación')
        ->and($otherGiftLine[14])->toBe('No');
});
