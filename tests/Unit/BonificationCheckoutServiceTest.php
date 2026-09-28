<?php

use App\Models\Bonification;
use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderProductBonification;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Variation;
use App\Models\VariationItem;
use App\Models\Zone;
use App\Repositories\OrderRepository;
use App\Services\BonificationCheckoutService;
use Database\Factories\BrandFactory;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('resolves last cart key per product', function () {
    $m = BonificationCheckoutService::lastCartKeyByProductId([
        'a' => ['product_id' => 10, 'quantity' => 1],
        'b' => ['product_id' => 20, 'quantity' => 1],
        'c' => ['product_id' => 10, 'quantity' => 2],
    ]);
    expect($m[10])->toBe('c')
        ->and($m[20])->toBe('b');
});

it('marks products discount-blocked only when a no-discount bonification is qualified', function () {
    $trigger = Product::factory()->for(BrandFactory::new())->create(['package_quantity' => 1]);
    $gift = Product::factory()->for(BrandFactory::new())->create(['price' => 0]);
    $bonification = Bonification::create([
        'name' => 'Buy 2 get 1 blocks discounts',
        'buy' => 2,
        'get' => 1,
        'product_id' => $gift->id,
        'max' => 10,
        'allow_discounts' => false,
    ]);
    $trigger->bonifications()->attach($bonification->id);

    $notQualified = BonificationCheckoutService::discountBlockedProductIds([
        ['product_id' => $trigger->id, 'quantity' => 1],
    ]);
    expect($notQualified)->toBe([]);

    $qualified = BonificationCheckoutService::discountBlockedProductIds([
        ['product_id' => $trigger->id, 'quantity' => 2],
    ]);
    expect($qualified)->toHaveKey($trigger->id);
});

describe('inventory and obsequio line resolution', function () {
    beforeEach(function () {
        Cache::flush();
        Setting::query()->updateOrCreate(
            ['key' => 'inventory_enabled'],
            ['name' => 'Inventario activo', 'value' => '1', 'show' => false]
        );
        Setting::query()->updateOrCreate(
            ['key' => 'global_minimum_inventory'],
            ['name' => 'Mín. inventario', 'value' => '5', 'show' => false]
        );
    });

    it('caps gift quantity using safety stock as floor', function () {
        $p = Product::factory()
            ->for(BrandFactory::new())
            ->state([
                'safety_stock' => 3,
                'inventory_opt_out' => false,
            ])
            ->create();
        $p->load('categories');
        $p->setRelation('categories', collect());
        $q = BonificationCheckoutService::minRequestedUnitsGivenAvailable(10, $p, 10);
        expect($q)->toBe(7);
    });

    it('uses global minimum when product safety is zero', function () {
        $p = Product::factory()
            ->for(BrandFactory::new())
            ->state(['safety_stock' => 0, 'inventory_opt_out' => false])
            ->create();
        $p->load('categories');
        $p->setRelation('categories', collect([]));
        $q = BonificationCheckoutService::minRequestedUnitsGivenAvailable(12, $p, 100);
        expect($q)->toBe(7);
    });

    it('returns full requested when inventory is not managed', function () {
        $p = Product::factory()
            ->for(BrandFactory::new())
            ->state(['safety_stock' => 0, 'inventory_opt_out' => 1])
            ->create();
        $q = BonificationCheckoutService::minRequestedUnitsGivenAvailable(0, $p, 3);
        expect($q)->toBe(3);
    });

    it('requires enough stock for the full bonification quantity', function () {
        $p = Product::factory()
            ->for(BrandFactory::new())
            ->state(['safety_stock' => 0, 'inventory_opt_out' => false])
            ->create();
        $p->setRelation('categories', collect([]));

        $enough = BonificationCheckoutService::hasEnoughStockForRequestedUnits(12, $p, 7); // 12 - global floor(5) = 7
        $notEnough = BonificationCheckoutService::hasEnoughStockForRequestedUnits(12, $p, 8);

        expect($enough)->toBeTrue()
            ->and($notEnough)->toBeFalse();
    });

    it('explains when the gift fails because of the global inventory floor', function () {
        $p = Product::factory()
            ->for(BrandFactory::new())
            ->state(['name' => 'Obsequio Piso', 'safety_stock' => 0, 'inventory_opt_out' => false])
            ->create();
        $p->setRelation('categories', collect([]));

        $message = BonificationCheckoutService::insufficientGiftStockMessage($p, 11, 7);

        expect($message)
            ->toContain('No se puede aplicar la bonificación')
            ->toContain('Obsequio Piso')
            ->toContain('requiere 7')
            ->toContain('hay 11 disponible')
            ->toContain('mínimo global')
            ->toContain('deja solo 6');
    });

    it('explains when the gift has no inventory in the zone bodega', function () {
        $p = Product::factory()
            ->for(BrandFactory::new())
            ->state(['name' => 'Obsequio Vacío', 'safety_stock' => 0, 'inventory_opt_out' => false])
            ->create();

        $message = BonificationCheckoutService::insufficientGiftStockMessage($p, 0, 3, 'Caja');

        expect($message)
            ->toContain('Obsequio Vacío')
            ->toContain('variación: Caja')
            ->toContain('no tiene inventario disponible')
            ->toContain('producto de regalo');
    });

    it('explains paid-line shortages with available and floor numbers', function () {
        $p = Product::factory()
            ->for(BrandFactory::new())
            ->state(['name' => 'Producto Pago', 'safety_stock' => 0, 'inventory_opt_out' => false])
            ->create();
        $p->setRelation('categories', collect([]));

        $message = BonificationCheckoutService::insufficientPaidLineStockMessage($p, 8, 4);

        expect($message)
            ->toContain('Producto Pago')
            ->toContain('solicitas 4')
            ->toContain('hay 8 disponible')
            ->toContain('mínimo global');
    });

    it('pre-validates combined gift demand against zone stock after cart demand', function () {
        $trigger = Product::factory()
            ->for(BrandFactory::new())
            ->state(['package_quantity' => 1, 'inventory_opt_out' => false, 'safety_stock' => 0])
            ->create();
        $gift = Product::factory()
            ->for(BrandFactory::new())
            ->state([
                'name' => 'Gift Precheck',
                'package_quantity' => 1,
                'inventory_opt_out' => false,
                'safety_stock' => 0,
            ])
            ->create();
        $bonification = Bonification::create([
            'name' => 'Buy 1 get 3',
            'buy' => 1,
            'get' => 3,
            'product_id' => $gift->id,
            'max' => 100,
        ]);
        $trigger->bonifications()->attach($bonification->id);

        \App\Models\ProductInventory::query()->create([
            'product_id' => $gift->id,
            'bodega_code' => 'BOD-PRE',
            'available' => 11,
            'physical' => 11,
            'reserved' => 0,
        ]);

        // available 11, floor 5 => max givable 6; requesting 3+4 via two rules would fail,
        // here single rule buy1 get3 with qty 1 => request 3, should pass.
        $ok = BonificationCheckoutService::validateCartGiftStock([
            ['product_id' => $trigger->id, 'quantity' => 1, 'variation_id' => null],
        ], 'BOD-PRE');
        expect($ok)->toBeNull();

        // Request 7 gift units (buy 1 get 7) against max givable 6.
        $bonification->update(['get' => 7]);
        $trigger->unsetRelation('bonifications');
        $fail = BonificationCheckoutService::validateCartGiftStock([
            ['product_id' => $trigger->id, 'quantity' => 1, 'variation_id' => null],
        ], 'BOD-PRE');
        expect($fail)
            ->toBeString()
            ->toContain('Gift Precheck')
            ->toContain('No se puede aplicar la bonificación');
    });

    it('pre-check uses the cart gift variation so it matches checkout OrderProduct lock-in', function () {
        $variation = Variation::query()->create(['name' => 'Presentacion precheck']);
        $viPaid = VariationItem::query()->create(['name' => 'Caja pagada', 'variation_id' => $variation->id]);
        $viOther = VariationItem::query()->create(['name' => 'Caja otra', 'variation_id' => $variation->id]);

        $trigger = Product::factory()
            ->for(BrandFactory::new())
            ->state(['package_quantity' => 1, 'inventory_opt_out' => false, 'safety_stock' => 0])
            ->create();
        $gift = Product::factory()
            ->for(BrandFactory::new())
            ->state([
                'name' => 'Gift In Cart',
                'sku' => 'GIFT-PARENT-PRE',
                'variation_id' => $variation->id,
                'package_quantity' => 1,
                'inventory_opt_out' => false,
                'safety_stock' => 0,
            ])
            ->create();
        $gift->items()->sync([
            $viPaid->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-PAID-SKU'],
            $viOther->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-OTHER-SKU'],
        ]);

        $bonification = Bonification::create([
            'name' => 'Buy 1 get 2 same gift',
            'buy' => 1,
            'get' => 2,
            'product_id' => $gift->id,
            'max' => 100,
        ]);
        $trigger->bonifications()->attach($bonification->id);

        // Parent / other variation look stocked; paid variation only has enough for cart+gift after floor.
        \App\Models\ProductInventory::query()->create([
            'product_id' => $gift->id,
            'bodega_code' => 'BOD-VAR',
            'available' => 50,
            'physical' => 50,
            'reserved' => 0,
        ]);
        \App\Models\ProductInventory::query()->create([
            'product_id' => $gift->id,
            'variation_item_id' => $viOther->id,
            'source_sku' => 'GIFT-OTHER-SKU',
            'bodega_code' => 'BOD-VAR',
            'available' => 50,
            'physical' => 50,
            'reserved' => 0,
        ]);
        \App\Models\ProductInventory::query()->create([
            'product_id' => $gift->id,
            'variation_item_id' => $viPaid->id,
            'source_sku' => 'GIFT-PAID-SKU',
            'bodega_code' => 'BOD-VAR',
            'available' => 8, // floor 5 => max givable from remaining after buying 1 = 2
            'physical' => 8,
            'reserved' => 0,
        ]);

        $cart = [
            ['product_id' => $trigger->id, 'quantity' => 1, 'variation_id' => null],
            ['product_id' => $gift->id, 'quantity' => 1, 'variation_id' => $viPaid->id],
        ];

        expect(BonificationCheckoutService::firstCartVariationItemIdForProduct($cart, (int) $gift->id))
            ->toBe($viPaid->id);

        // remaining after cart on paid variation: 8-1=7, gift needs 2, floor 5 => max givable 2 → OK
        expect(BonificationCheckoutService::validateCartGiftStock($cart, 'BOD-VAR'))->toBeNull();

        // If we ignored cart variation and checked parent (50), we'd wrongly pass even when paid var is exhausted.
        $bonification->update(['get' => 4]); // need 4 gift; remaining 7, floor 5 => max 2 → fail
        $trigger->unsetRelation('bonifications');
        $fail = BonificationCheckoutService::validateCartGiftStock($cart, 'BOD-VAR');
        expect($fail)
            ->toBeString()
            ->toContain('Gift In Cart')
            ->toContain('Caja pagada');
    });

    it('validates stock against combined bonification demand for the same gift product', function () {
        $gift = Product::factory()
            ->for(BrandFactory::new())
            ->state(['safety_stock' => 0, 'inventory_opt_out' => false])
            ->create();
        $gift->setRelation('categories', collect([]));

        $firstBonificationQty = 3;
        $secondBonificationQty = 4;
        $combinedDemand = $firstBonificationQty + $secondBonificationQty;

        // available 11 -> floor 5 => max gift units 6, so combined(7) must fail.
        $hasEnoughForAll = BonificationCheckoutService::hasEnoughStockForRequestedUnits(
            11,
            $gift,
            $combinedDemand
        );
        expect($hasEnoughForAll)->toBeFalse();
    });

    it('prefers enabled variation with non-empty pivot sku for gifts without parent sku', function () {
        $variation = Variation::query()->create(['name' => 'Talla']);
        $viA = VariationItem::query()->create(['name' => 'A', 'variation_id' => $variation->id]);
        $viB = VariationItem::query()->create(['name' => 'B', 'variation_id' => $variation->id]);
        $gift = Product::factory()
            ->for(BrandFactory::new())
            ->state(['sku' => '', 'variation_id' => $variation->id])
            ->create();
        $gift->items()->sync([
            $viA->id => ['price' => 1, 'enabled' => 1, 'sku' => ''],
            $viB->id => ['price' => 1, 'enabled' => 1, 'sku' => 'B-SKU'],
        ]);
        $id = BonificationCheckoutService::defaultVariationItemIdPreferringPivotSku(
            $gift->fresh(['items'])
        );
        expect($id)->toBe($viB->id);
    });

    it('keeps selected variation stock on the parent product', function () {
        $variation = Variation::query()->create(['name' => 'Formato']);
        $variationItem = VariationItem::query()->create(['name' => 'Caja', 'variation_id' => $variation->id]);
        $parent = Product::factory()
            ->for(BrandFactory::new())
            ->state(['sku' => 'DUMMY-PARENT', 'variation_id' => $variation->id])
            ->create();
        $parent->items()->sync([
            $variationItem->id => ['price' => 1, 'enabled' => 1, 'sku' => 'REAL-VARIATION-SKU'],
        ]);

        $resolved = BonificationCheckoutService::stockProductForSelectedVariation(
            $parent->fresh(),
            $variationItem->id
        );

        expect($resolved->id)->toBe($parent->id);
    });

    it('resolveGiftVariationId uses cart line when gift is already in the order', function () {
        $u = User::factory()->create();
        $z = Zone::query()->create([
            'user_id' => $u->id,
            'zone' => '933',
            'code' => 'Z1',
            'route' => 'R1',
        ]);
        $o = Order::query()->create([
            'user_id' => $u->id,
            'zone_id' => $z->id,
            'status_id' => Order::STATUS_PENDING,
            'total' => 0,
            'discount' => 0,
        ]);
        $variation = Variation::query()->create(['name' => 'C']);
        $vi1 = VariationItem::query()->create(['name' => '1', 'variation_id' => $variation->id]);
        $vi2 = VariationItem::query()->create(['name' => '2', 'variation_id' => $variation->id]);
        $gift = Product::factory()
            ->for(BrandFactory::new())
            ->state(['sku' => '', 'variation_id' => $variation->id])
            ->create();
        $gift->items()->sync([
            $vi1->id => ['price' => 1, 'enabled' => 1, 'sku' => 'G1'],
            $vi2->id => ['price' => 1, 'enabled' => 1, 'sku' => 'G2'],
        ]);
        OrderProduct::query()->create([
            'order_id' => $o->id,
            'product_id' => $gift->id,
            'quantity' => 1,
            'price' => 0,
            'discount' => 0,
            'percentage' => 0,
            'package_quantity' => 1,
            'variation_item_id' => $vi1->id,
        ]);
        $r = BonificationCheckoutService::resolveGiftVariationItemId(
            $gift->fresh(),
            (int) $o->id
        );
        expect($r)->toBe($vi1->id);
    });

    it('resolveGiftVariationId keeps parent SKU when variable gift has own SKU', function () {
        $variation = Variation::query()->create(['name' => 'Color']);
        $vi1 = VariationItem::query()->create(['name' => 'Rojo', 'variation_id' => $variation->id]);
        $vi2 = VariationItem::query()->create(['name' => 'Azul', 'variation_id' => $variation->id]);
        $gift = Product::factory()
            ->for(BrandFactory::new())
            ->state(['sku' => 'PARENT-SKU', 'variation_id' => $variation->id])
            ->create();
        $gift->items()->sync([
            $vi1->id => ['price' => 1, 'enabled' => 1, 'sku' => 'G1'],
            $vi2->id => ['price' => 1, 'enabled' => 1, 'sku' => 'G2'],
        ]);

        $resolved = BonificationCheckoutService::resolveGiftVariationItemId(
            $gift->fresh(),
            9999
        );

        expect($resolved)->toBeNull();
    });

    it('resolveGiftVariationId falls back to a stocked variation when parent SKU has no usable inventory', function () {
        $variation = Variation::query()->create(['name' => 'Empaque']);
        $vi1 = VariationItem::query()->create(['name' => 'Caja 6', 'variation_id' => $variation->id]);
        $vi2 = VariationItem::query()->create(['name' => 'Caja 12', 'variation_id' => $variation->id]);
        $gift = Product::factory()
            ->for(BrandFactory::new())
            ->state([
                'sku' => 'GIFT-PARENT-LEGACY',
                'variation_id' => $variation->id,
                'safety_stock' => 0,
                'inventory_opt_out' => false,
            ])
            ->create();
        $gift->items()->sync([
            $vi1->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-V1-SKU'],
            $vi2->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-V2-SKU'],
        ]);

        \App\Models\ProductInventory::query()->create([
            'product_id' => $gift->id,
            'bodega_code' => 'BOD-1',
            'available' => 0,
            'physical' => 0,
            'reserved' => 0,
        ]);
        \App\Models\ProductInventory::query()->create([
            'product_id' => $gift->id,
            'variation_item_id' => $vi2->id,
            'source_sku' => 'GIFT-V2-SKU',
            'bodega_code' => 'BOD-1',
            'available' => 30,
            'physical' => 30,
            'reserved' => 0,
        ]);

        $resolved = BonificationCheckoutService::resolveGiftVariationItemId(
            $gift->fresh(['items']),
            9999,
            'BOD-1',
            3
        );

        expect($resolved)->toBe($vi2->id);
    });

    it('resolveGiftVariationId still uses the parent SKU when parent inventory is enough', function () {
        $variation = Variation::query()->create(['name' => 'Empaque ok']);
        $vi1 = VariationItem::query()->create(['name' => 'Caja', 'variation_id' => $variation->id]);
        $gift = Product::factory()
            ->for(BrandFactory::new())
            ->state([
                'sku' => 'GIFT-PARENT-OK',
                'variation_id' => $variation->id,
                'safety_stock' => 0,
                'inventory_opt_out' => false,
            ])
            ->create();
        $gift->items()->sync([
            $vi1->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-V1-OK'],
        ]);

        \App\Models\ProductInventory::query()->create([
            'product_id' => $gift->id,
            'bodega_code' => 'BOD-1',
            'available' => 50,
            'physical' => 50,
            'reserved' => 0,
        ]);

        $resolved = BonificationCheckoutService::resolveGiftVariationItemId(
            $gift->fresh(['items']),
            9999,
            'BOD-1',
            3
        );

        expect($resolved)->toBeNull();
    });

    it('buildOrderXmlForDiagnostic includes variation sku for bonification line when parent sku is empty', function () {
        $u = User::factory()->create();
        $z = Zone::query()->create([
            'user_id' => $u->id,
            'zone' => '933',
            'code' => 'Z-XML',
            'route' => 'R1',
        ]);
        $variation = Variation::query()->create(['name' => 'P']);
        $vi1 = VariationItem::query()->create(['name' => '1', 'variation_id' => $variation->id]);
        $vi2 = VariationItem::query()->create(['name' => '2', 'variation_id' => $variation->id]);
        $trigger = Product::factory()
            ->for(BrandFactory::new())
            ->state(['name' => 'T', 'sku' => 'TRG'])
            ->create();
        $gift = Product::factory()
            ->for(BrandFactory::new())
            ->state(['name' => 'G', 'sku' => '', 'variation_id' => $variation->id])
            ->create();
        $gift->items()->sync([
            $vi1->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-LINE-SKU'],
            $vi2->id => ['price' => 0, 'enabled' => 1, 'sku' => 'OTHER'],
        ]);
        $b = Bonification::query()->create([
            'name' => 'B1',
            'buy' => 1,
            'get' => 1,
            'product_id' => $gift->id,
            'max' => 100,
            'allow_discounts' => true,
        ]);
        $o = Order::query()->create([
            'user_id' => $u->id,
            'zone_id' => $z->id,
            'status_id' => Order::STATUS_PENDING,
            'total' => 0,
            'discount' => 0,
        ]);
        $op = OrderProduct::query()->create([
            'order_id' => $o->id,
            'product_id' => $trigger->id,
            'quantity' => 1,
            'price' => 1000,
            'discount' => 0,
            'percentage' => 0,
            'package_quantity' => 1,
        ]);
        OrderProductBonification::query()->create([
            'order_id' => $o->id,
            'order_product_id' => $op->id,
            'bonification_id' => $b->id,
            'product_id' => $gift->id,
            'variation_item_id' => $vi1->id,
            'quantity' => 1,
        ]);
        $order = Order::query()
            ->with(['zone', 'user', 'products', 'bonifications'])
            ->find($o->id);
        $xml = OrderRepository::buildOrderXmlForDiagnostic($order, true);
        expect($xml)->toContain('GIFT-LINE-SKU');
    });

    it('buildOrderXmlForDiagnostic includes several bonifications in the same order', function () {
        $u = User::factory()->create();
        $z = Zone::query()->create([
            'user_id' => $u->id,
            'zone' => '933',
            'code' => 'Z-XML-MULTI',
            'route' => 'R1',
        ]);

        $variation = Variation::query()->create(['name' => 'Presentacion']);
        $giftVarA = VariationItem::query()->create(['name' => 'A', 'variation_id' => $variation->id]);
        $giftVarB = VariationItem::query()->create(['name' => 'B', 'variation_id' => $variation->id]);

        $trigger1 = Product::factory()
            ->for(BrandFactory::new())
            ->state(['name' => 'Trigger 1', 'sku' => 'TRG-1'])
            ->create();
        $trigger2 = Product::factory()
            ->for(BrandFactory::new())
            ->state(['name' => 'Trigger 2', 'sku' => 'TRG-2'])
            ->create();

        $giftVariable = Product::factory()
            ->for(BrandFactory::new())
            ->state(['name' => 'Gift Var', 'sku' => '', 'variation_id' => $variation->id])
            ->create();
        $giftVariable->items()->sync([
            $giftVarA->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-VAR-SKU'],
            $giftVarB->id => ['price' => 0, 'enabled' => 1, 'sku' => 'GIFT-VAR-ALT'],
        ]);

        $giftSimple = Product::factory()
            ->for(BrandFactory::new())
            ->state(['name' => 'Gift Simple', 'sku' => 'GIFT-SIMPLE-SKU'])
            ->create();

        $bonificationA = Bonification::query()->create([
            'name' => 'B-A',
            'buy' => 1,
            'get' => 1,
            'product_id' => $giftVariable->id,
            'max' => 100,
            'allow_discounts' => true,
        ]);
        $bonificationB = Bonification::query()->create([
            'name' => 'B-B',
            'buy' => 1,
            'get' => 1,
            'product_id' => $giftSimple->id,
            'max' => 100,
            'allow_discounts' => true,
        ]);

        $o = Order::query()->create([
            'user_id' => $u->id,
            'zone_id' => $z->id,
            'status_id' => Order::STATUS_PENDING,
            'total' => 0,
            'discount' => 0,
        ]);
        $op1 = OrderProduct::query()->create([
            'order_id' => $o->id,
            'product_id' => $trigger1->id,
            'quantity' => 2,
            'price' => 1000,
            'discount' => 0,
            'percentage' => 0,
            'package_quantity' => 1,
        ]);
        $op2 = OrderProduct::query()->create([
            'order_id' => $o->id,
            'product_id' => $trigger2->id,
            'quantity' => 1,
            'price' => 2000,
            'discount' => 0,
            'percentage' => 0,
            'package_quantity' => 1,
        ]);

        OrderProductBonification::query()->create([
            'order_id' => $o->id,
            'order_product_id' => $op1->id,
            'bonification_id' => $bonificationA->id,
            'product_id' => $giftVariable->id,
            'variation_item_id' => $giftVarA->id,
            'quantity' => 2,
        ]);
        OrderProductBonification::query()->create([
            'order_id' => $o->id,
            'order_product_id' => $op2->id,
            'bonification_id' => $bonificationB->id,
            'product_id' => $giftSimple->id,
            'variation_item_id' => null,
            'quantity' => 1,
        ]);

        $order = Order::query()
            ->with(['zone', 'user', 'products', 'bonifications'])
            ->find($o->id);
        $xml = OrderRepository::buildOrderXmlForDiagnostic($order, true);

        expect($xml)->toContain('GIFT-VAR-SKU')
            ->and($xml)->toContain('GIFT-SIMPLE-SKU')
            ->and(substr_count($xml, '<dyn:itemId>GIFT-VAR-SKU</dyn:itemId>'))->toBe(1)
            ->and(substr_count($xml, '<dyn:itemId>GIFT-SIMPLE-SKU</dyn:itemId>'))->toBe(1);
    });
});
