<?php

namespace App\Services;

use App\Models\Bonification;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\Setting;
use App\Models\Tax;
use App\Models\User;
use App\Models\Variation;
use App\Models\VariationItem;
use App\Models\Vendor;
use App\Models\Zone;
use App\Models\ZoneWarehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Reproduction harness for the bonification + variation matrix.
 *
 * Holds the 15 scenarios that exercise every meaningful combination of:
 *   - trigger product with/without variations,
 *   - gift product with/without variations,
 *   - presence or absence of a real parent SKU,
 *   - where the inventory actually lives,
 *   - and the awkward edges (max=0, package_quantity=0, missing bodega…).
 *
 * Both the artisan setup/run commands and the matrix feature test consume this
 * single source of truth so stage runs and CI cannot drift.
 *
 * Everything created by this service is prefixed with {@see self::PREFIX} so it
 * can be cleaned up with a single SKU/slug LIKE query.
 */
class BonificationReproService
{
    public const PREFIX = 'repro-bonif-';
    public const BODEGA = 'BOD-REPRO';

    /** @return array<int, array{key:string, title:string, expect:string}> */
    public function scenarios(): array
    {
        return [
            ['key' => 's01_no_var_no_var', 'title' => 'No variations on either side', 'expect' => 'planned, parent SKU on gift line'],
            ['key' => 's02_trigger_var_no_gift_var', 'title' => 'Trigger has variations (one cart row), gift simple', 'expect' => 'planned, gift on parent SKU'],
            ['key' => 's03_multi_row_trigger_var', 'title' => 'Trigger has variations across multiple cart rows', 'expect' => 'planned, aggregated across rows'],
            ['key' => 's04_gift_var_empty_parent_sku', 'title' => 'Gift has variations and empty parent SKU', 'expect' => 'gift line uses variation SKU'],
            ['key' => 's05_gift_var_parent_sku_parent_inv', 'title' => 'Gift has variations, parent SKU non-empty, parent stocked', 'expect' => 'gift on parent SKU'],
            ['key' => 's06_gift_var_parent_sku_variation_inv', 'title' => 'Gift has variations, parent SKU non-empty, only variation stocked', 'expect' => 'gift falls back to variation SKU (PRIMARY SUSPECT)'],
            ['key' => 's07_gift_var_parent_sku_no_stock', 'title' => 'Gift has variations, parent SKU non-empty, nothing stocked', 'expect' => 'order rolls back with descriptive error'],
            ['key' => 's08_both_var_empty_parent_sku', 'title' => 'Both trigger and gift have variations, empty parent SKU', 'expect' => 'planned with variation SKU'],
            ['key' => 's09_both_var_parent_sku_both_stocked', 'title' => 'Both have variations, parent SKU non-empty, parent + variation stocked', 'expect' => 'parent picked first'],
            ['key' => 's10_both_var_gift_in_cart', 'title' => 'Both have variations, gift already in cart consuming same variation', 'expect' => 'combined demand check should roll back when stock cannot cover both'],
            ['key' => 's11_two_bonifications_same_gift', 'title' => 'Two bonifications targeting the same gift product', 'expect' => 'single stock plan with summed demand'],
            ['key' => 's12_max_zero', 'title' => 'Bonification.max = 0', 'expect' => 'should NOT create a zero-quantity row (currently a bug)'],
            ['key' => 's13_package_zero', 'title' => 'Trigger product has package_quantity = 0', 'expect' => 'should NOT silently skip (currently a bug)'],
            ['key' => 's14_no_bodega', 'title' => 'inventory_enabled = 1 but zone has no bodega mapping', 'expect' => 'TBD by policy (see Phase 3-G)'],
            ['key' => 's15_variation_pivot_sku_empty', 'title' => 'Gift variation pivot SKU empty for selected variation, non-empty for another', 'expect' => 'gift falls back to the variation with a non-empty pivot SKU'],
        ];
    }

    /**
     * Sets up DB state for one scenario. Idempotent: rerunning the same key resets
     * inventory and product state so the next run sees a clean slate.
     *
     * @return array{user:User, zone:Zone, cart:array<int, array{product_id:int, variation_id:?int, quantity:int}>, observations:string, scenario:array<string,mixed>}
     */
    public function setup(string $key): array
    {
        $scenario = $this->findScenario($key);
        $this->ensureGlobals($key);
        [$user, $zone] = $this->ensureUserAndZone();
        [$tax, $vendor, $brand] = $this->ensureCatalogBase();
        $this->ensureBodegaMapping($zone, $key !== 's14_no_bodega');
        $this->resetScenarioState($key);

        $method = 'setup_'.$key;
        if (! method_exists($this, $method)) {
            throw new \RuntimeException("Unknown scenario {$key}.");
        }
        /** @var array{cart:array<int, array<string,mixed>>, observations?:string} $result */
        $result = $this->{$method}($user, $zone, $tax, $vendor, $brand);

        return [
            'user' => $user,
            'zone' => $zone,
            'cart' => $result['cart'] ?? [],
            'observations' => $result['observations'] ?? 'repro '.$key,
            'scenario' => $scenario,
        ];
    }

    /**
     * Removes every record created by this service. Safe to run before or after
     * a scenario; matches by the {@see self::PREFIX} prefix on slug/sku/name.
     */
    public function teardown(): void
    {
        DB::transaction(function (): void {
            $products = Product::query()->where('slug', 'like', self::PREFIX.'%')->get();
            foreach ($products as $product) {
                ProductInventory::query()->where('product_id', $product->id)->delete();
                $product->items()->detach();
                $product->bonifications()->detach();
                DB::table('bonification_product')->where('product_id', $product->id)->delete();
                Bonification::query()->where('product_id', $product->id)->delete();
                $product->delete();
            }
            VariationItem::query()->where('name', 'like', self::PREFIX.'%')->delete();
            Variation::query()->where('name', 'like', self::PREFIX.'%')->delete();
            Brand::query()->where('slug', 'like', self::PREFIX.'%')->delete();
            Vendor::query()->where('slug', 'like', self::PREFIX.'%')->delete();
            Tax::query()->where('name', 'like', self::PREFIX.'%')->delete();
            ZoneWarehouse::query()->where('zone_code', 'like', self::PREFIX.'%')->delete();
            Zone::query()->where('code', 'like', self::PREFIX.'%')->delete();
            User::query()->where('email', 'like', self::PREFIX.'%@%')->delete();
        });
    }

    /**
     * Reset inventory rows and detach pivots for a single scenario before re-setup,
     * preserving the shared user/zone/bodega/tax/vendor/brand.
     */
    public function resetScenarioState(string $key): void
    {
        $like = self::PREFIX.$key.'-%';
        $products = Product::query()->where('slug', 'like', $like)->get();
        foreach ($products as $product) {
            ProductInventory::query()->where('product_id', $product->id)->delete();
            $product->items()->detach();
            $product->bonifications()->detach();
            DB::table('bonification_product')->where('product_id', $product->id)->delete();
            Bonification::query()->where('product_id', $product->id)->delete();
            $product->delete();
        }
        VariationItem::query()->where('name', 'like', self::PREFIX.$key.'-%')->delete();
        Variation::query()->where('name', 'like', self::PREFIX.$key.'-%')->delete();
    }

    private function findScenario(string $key): array
    {
        foreach ($this->scenarios() as $s) {
            if ($s['key'] === $key) {
                return $s;
            }
        }
        throw new \RuntimeException("Unknown scenario {$key}.");
    }

    private function ensureGlobals(string $key): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'inventory_enabled'],
            ['name' => 'Inventory enabled', 'value' => '1', 'show' => false]
        );
        Setting::query()->updateOrCreate(
            ['key' => 'global_minimum_inventory'],
            ['name' => 'Global minimum inventory', 'value' => '5', 'show' => false]
        );
        Setting::query()->updateOrCreate(
            ['key' => 'min_amount'],
            ['name' => 'Min amount', 'value' => '0', 'show' => false]
        );
        \Illuminate\Support\Facades\Cache::flush();
    }

    /** @return array{0:User, 1:Zone} */
    private function ensureUserAndZone(): array
    {
        Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::query()->firstOrCreate(
            ['email' => self::PREFIX.'user@example.test'],
            [
                'name' => self::PREFIX.'user',
                'password' => Hash::make('repro-password'),
                'document' => self::PREFIX.'doc',
                'phone' => '0000',
                'status_id' => 1,
                'terms_accepted' => 1,
            ]
        );

        $zone = Zone::query()->firstOrCreate(
            ['code' => self::PREFIX.'zone'],
            [
                'user_id' => $user->id,
                'zone' => self::PREFIX.'zone',
                'route' => '1',
                'day' => 'Monday',
                'address' => 'Repro address',
            ]
        );

        return [$user, $zone];
    }

    /** @return array{0:Tax, 1:Vendor, 2:Brand} */
    private function ensureCatalogBase(): array
    {
        $tax = Tax::query()->firstOrCreate(
            ['name' => self::PREFIX.'tax'],
            ['tax' => 0]
        );
        $vendor = Vendor::query()->firstOrCreate(
            ['slug' => self::PREFIX.'vendor'],
            ['name' => self::PREFIX.'vendor', 'minimum_purchase' => 0, 'active' => 1]
        );
        $brand = Brand::query()->firstOrCreate(
            ['slug' => self::PREFIX.'brand'],
            ['name' => self::PREFIX.'brand', 'vendor_id' => $vendor->id]
        );

        return [$tax, $vendor, $brand];
    }

    private function ensureBodegaMapping(Zone $zone, bool $present): void
    {
        if ($present) {
            ZoneWarehouse::query()->updateOrCreate(
                ['zone_code' => $zone->zone],
                ['bodega_code' => self::BODEGA]
            );
        } else {
            ZoneWarehouse::query()->where('zone_code', $zone->zone)->delete();
        }
    }

    private function makeProduct(
        string $key,
        string $slugTag,
        Tax $tax,
        Brand $brand,
        string $sku = '',
        int $price = 100,
        ?int $variationId = null,
        int $packageQuantity = 1
    ): Product {
        $slug = self::PREFIX.$key.'-'.$slugTag;
        $sku = $sku === '' ? '' : self::PREFIX.$key.'-'.$sku;

        return Product::query()->create([
            'name' => $slug,
            'description' => $slug,
            'short_description' => $slug,
            'sku' => $sku,
            'slug' => $slug,
            'active' => 1,
            'price' => $price,
            'delivery_days' => 1,
            'discount' => 0,
            'quantity_min' => 1,
            'quantity_max' => 100,
            'step' => 1,
            'tax_id' => $tax->id,
            'brand_id' => $brand->id,
            'package_quantity' => $packageQuantity,
            'variation_id' => $variationId,
            'safety_stock' => 0,
            'inventory_opt_out' => 0,
        ]);
    }

    /** @return array{0:Variation, 1:array<int, VariationItem>} */
    private function makeVariation(string $key, string $name, array $items): array
    {
        $variation = Variation::query()->create(['name' => self::PREFIX.$key.'-'.$name]);
        $variationItems = [];
        foreach ($items as $itemName) {
            $variationItems[] = VariationItem::query()->create([
                'name' => self::PREFIX.$key.'-'.$itemName,
                'variation_id' => $variation->id,
            ]);
        }

        return [$variation, $variationItems];
    }

    private function setInventory(Product $product, ?int $variationItemId, int $available, ?string $sourceSku = null): void
    {
        ProductInventory::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'variation_item_id' => $variationItemId,
                'bodega_code' => self::BODEGA,
            ],
            [
                'source_sku' => $sourceSku,
                'available' => $available,
                'physical' => $available,
                'reserved' => 0,
            ]
        );
    }

    // ---------------------------------------------------------------------
    // Scenarios. Each method receives ($user, $zone, $tax, $vendor, $brand)
    // and returns ['cart' => ..., 'observations' => ...].
    // ---------------------------------------------------------------------

    private function setup_s01_no_var_no_var(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        $trigger = $this->makeProduct('s01_no_var_no_var', 'trigger', $t, $b, 'TRIG');
        $gift = $this->makeProduct('s01_no_var_no_var', 'gift', $t, $b, 'GIFT', 0);
        $bonif = Bonification::query()->create([
            'name' => 'Buy 10 get 1', 'buy' => 10, 'get' => 1, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach($bonif->id);
        $this->setInventory($trigger, null, 100);
        $this->setInventory($gift, null, 100);

        return ['cart' => [['product_id' => $trigger->id, 'variation_id' => null, 'quantity' => 10]]];
    }

    private function setup_s02_trigger_var_no_gift_var(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        [$variation, $items] = $this->makeVariation('s02_trigger_var_no_gift_var', 'pres', ['caja']);
        $trigger = $this->makeProduct('s02_trigger_var_no_gift_var', 'trigger', $t, $b, '', 100, $variation->id);
        $trigger->items()->sync([$items[0]->id => ['price' => 100, 'enabled' => 1, 'sku' => self::PREFIX.'s02_trigger_var_no_gift_var-TRIG-V1']]);
        $gift = $this->makeProduct('s02_trigger_var_no_gift_var', 'gift', $t, $b, 'GIFT', 0);

        $bonif = Bonification::query()->create([
            'name' => 'Buy 10 get 1', 'buy' => 10, 'get' => 1, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach($bonif->id);
        $this->setInventory($trigger, $items[0]->id, 50, self::PREFIX.'s02_trigger_var_no_gift_var-TRIG-V1');
        $this->setInventory($gift, null, 50);

        return ['cart' => [['product_id' => $trigger->id, 'variation_id' => $items[0]->id, 'quantity' => 10]]];
    }

    private function setup_s03_multi_row_trigger_var(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        [$variation, $items] = $this->makeVariation('s03_multi_row_trigger_var', 'pres', ['caja-a', 'caja-b']);
        $trigger = $this->makeProduct('s03_multi_row_trigger_var', 'trigger', $t, $b, '', 100, $variation->id);
        $trigger->items()->sync([
            $items[0]->id => ['price' => 100, 'enabled' => 1, 'sku' => self::PREFIX.'s03_multi_row_trigger_var-TRIG-V1'],
            $items[1]->id => ['price' => 100, 'enabled' => 1, 'sku' => self::PREFIX.'s03_multi_row_trigger_var-TRIG-V2'],
        ]);
        $gift = $this->makeProduct('s03_multi_row_trigger_var', 'gift', $t, $b, 'GIFT', 0);
        $bonif = Bonification::query()->create([
            'name' => 'Buy 10 get 1', 'buy' => 10, 'get' => 1, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach($bonif->id);
        $this->setInventory($trigger, $items[0]->id, 50, self::PREFIX.'s03_multi_row_trigger_var-TRIG-V1');
        $this->setInventory($trigger, $items[1]->id, 50, self::PREFIX.'s03_multi_row_trigger_var-TRIG-V2');
        $this->setInventory($gift, null, 50);

        return ['cart' => [
            ['product_id' => $trigger->id, 'variation_id' => $items[0]->id, 'quantity' => 5],
            ['product_id' => $trigger->id, 'variation_id' => $items[1]->id, 'quantity' => 5],
        ]];
    }

    private function setup_s04_gift_var_empty_parent_sku(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        [$variation, $items] = $this->makeVariation('s04_gift_var_empty_parent_sku', 'pres', ['caja']);
        $trigger = $this->makeProduct('s04_gift_var_empty_parent_sku', 'trigger', $t, $b, 'TRIG');
        $gift = $this->makeProduct('s04_gift_var_empty_parent_sku', 'gift', $t, $b, '', 0, $variation->id);
        $gift->items()->sync([$items[0]->id => ['price' => 0, 'enabled' => 1, 'sku' => self::PREFIX.'s04_gift_var_empty_parent_sku-GIFT-V1']]);
        $bonif = Bonification::query()->create([
            'name' => 'Buy 10 get 1', 'buy' => 10, 'get' => 1, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach($bonif->id);
        $this->setInventory($trigger, null, 100);
        $this->setInventory($gift, $items[0]->id, 50, self::PREFIX.'s04_gift_var_empty_parent_sku-GIFT-V1');

        return ['cart' => [['product_id' => $trigger->id, 'variation_id' => null, 'quantity' => 10]]];
    }

    private function setup_s05_gift_var_parent_sku_parent_inv(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        [$variation, $items] = $this->makeVariation('s05_gift_var_parent_sku_parent_inv', 'pres', ['caja']);
        $trigger = $this->makeProduct('s05_gift_var_parent_sku_parent_inv', 'trigger', $t, $b, 'TRIG');
        $gift = $this->makeProduct('s05_gift_var_parent_sku_parent_inv', 'gift', $t, $b, 'GIFT', 0, $variation->id);
        $gift->items()->sync([$items[0]->id => ['price' => 0, 'enabled' => 1, 'sku' => self::PREFIX.'s05_gift_var_parent_sku_parent_inv-GIFT-V1']]);
        $bonif = Bonification::query()->create([
            'name' => 'Buy 10 get 1', 'buy' => 10, 'get' => 1, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach($bonif->id);
        $this->setInventory($trigger, null, 100);
        $this->setInventory($gift, null, 50);
        $this->setInventory($gift, $items[0]->id, 0, self::PREFIX.'s05_gift_var_parent_sku_parent_inv-GIFT-V1');

        return ['cart' => [['product_id' => $trigger->id, 'variation_id' => null, 'quantity' => 10]]];
    }

    private function setup_s06_gift_var_parent_sku_variation_inv(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        [$variation, $items] = $this->makeVariation('s06_gift_var_parent_sku_variation_inv', 'pres', ['caja']);
        $trigger = $this->makeProduct('s06_gift_var_parent_sku_variation_inv', 'trigger', $t, $b, 'TRIG');
        $gift = $this->makeProduct('s06_gift_var_parent_sku_variation_inv', 'gift', $t, $b, 'GIFT', 0, $variation->id);
        $gift->items()->sync([$items[0]->id => ['price' => 0, 'enabled' => 1, 'sku' => self::PREFIX.'s06_gift_var_parent_sku_variation_inv-GIFT-V1']]);
        $bonif = Bonification::query()->create([
            'name' => 'Buy 10 get 1', 'buy' => 10, 'get' => 1, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach($bonif->id);
        $this->setInventory($trigger, null, 100);
        $this->setInventory($gift, null, 0);
        $this->setInventory($gift, $items[0]->id, 50, self::PREFIX.'s06_gift_var_parent_sku_variation_inv-GIFT-V1');

        return ['cart' => [['product_id' => $trigger->id, 'variation_id' => null, 'quantity' => 10]]];
    }

    private function setup_s07_gift_var_parent_sku_no_stock(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        [$variation, $items] = $this->makeVariation('s07_gift_var_parent_sku_no_stock', 'pres', ['caja']);
        $trigger = $this->makeProduct('s07_gift_var_parent_sku_no_stock', 'trigger', $t, $b, 'TRIG');
        $gift = $this->makeProduct('s07_gift_var_parent_sku_no_stock', 'gift', $t, $b, 'GIFT', 0, $variation->id);
        $gift->items()->sync([$items[0]->id => ['price' => 0, 'enabled' => 1, 'sku' => self::PREFIX.'s07_gift_var_parent_sku_no_stock-GIFT-V1']]);
        $bonif = Bonification::query()->create([
            'name' => 'Buy 10 get 1', 'buy' => 10, 'get' => 1, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach($bonif->id);
        $this->setInventory($trigger, null, 100);
        $this->setInventory($gift, null, 0);
        $this->setInventory($gift, $items[0]->id, 4, self::PREFIX.'s07_gift_var_parent_sku_no_stock-GIFT-V1');

        return ['cart' => [['product_id' => $trigger->id, 'variation_id' => null, 'quantity' => 10]]];
    }

    private function setup_s08_both_var_empty_parent_sku(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        [$tv, $tItems] = $this->makeVariation('s08_both_var_empty_parent_sku', 'trig-pres', ['caja12', 'caja24']);
        $trigger = $this->makeProduct('s08_both_var_empty_parent_sku', 'trigger', $t, $b, '', 100, $tv->id);
        $trigger->items()->sync([
            $tItems[0]->id => ['price' => 100, 'enabled' => 1, 'sku' => self::PREFIX.'s08_both_var_empty_parent_sku-TRIG-V1'],
            $tItems[1]->id => ['price' => 100, 'enabled' => 1, 'sku' => self::PREFIX.'s08_both_var_empty_parent_sku-TRIG-V2'],
        ]);
        [$gv, $gItems] = $this->makeVariation('s08_both_var_empty_parent_sku', 'gift-pres', ['unidad']);
        $gift = $this->makeProduct('s08_both_var_empty_parent_sku', 'gift', $t, $b, '', 0, $gv->id);
        $gift->items()->sync([$gItems[0]->id => ['price' => 0, 'enabled' => 1, 'sku' => self::PREFIX.'s08_both_var_empty_parent_sku-GIFT-V1']]);
        $bonif = Bonification::query()->create([
            'name' => 'Buy 10 get 1', 'buy' => 10, 'get' => 1, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach($bonif->id);
        $this->setInventory($trigger, $tItems[0]->id, 50, self::PREFIX.'s08_both_var_empty_parent_sku-TRIG-V1');
        $this->setInventory($trigger, $tItems[1]->id, 50, self::PREFIX.'s08_both_var_empty_parent_sku-TRIG-V2');
        $this->setInventory($gift, $gItems[0]->id, 30, self::PREFIX.'s08_both_var_empty_parent_sku-GIFT-V1');

        return ['cart' => [
            ['product_id' => $trigger->id, 'variation_id' => $tItems[0]->id, 'quantity' => 5],
            ['product_id' => $trigger->id, 'variation_id' => $tItems[1]->id, 'quantity' => 5],
        ]];
    }

    private function setup_s09_both_var_parent_sku_both_stocked(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        [$tv, $tItems] = $this->makeVariation('s09_both_var_parent_sku_both_stocked', 'trig-pres', ['caja']);
        $trigger = $this->makeProduct('s09_both_var_parent_sku_both_stocked', 'trigger', $t, $b, '', 100, $tv->id);
        $trigger->items()->sync([$tItems[0]->id => ['price' => 100, 'enabled' => 1, 'sku' => self::PREFIX.'s09_both_var_parent_sku_both_stocked-TRIG-V1']]);
        [$gv, $gItems] = $this->makeVariation('s09_both_var_parent_sku_both_stocked', 'gift-pres', ['unidad']);
        $gift = $this->makeProduct('s09_both_var_parent_sku_both_stocked', 'gift', $t, $b, 'GIFT', 0, $gv->id);
        $gift->items()->sync([$gItems[0]->id => ['price' => 0, 'enabled' => 1, 'sku' => self::PREFIX.'s09_both_var_parent_sku_both_stocked-GIFT-V1']]);
        $bonif = Bonification::query()->create([
            'name' => 'Buy 10 get 1', 'buy' => 10, 'get' => 1, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach($bonif->id);
        $this->setInventory($trigger, $tItems[0]->id, 50, self::PREFIX.'s09_both_var_parent_sku_both_stocked-TRIG-V1');
        $this->setInventory($gift, null, 50);
        $this->setInventory($gift, $gItems[0]->id, 30, self::PREFIX.'s09_both_var_parent_sku_both_stocked-GIFT-V1');

        return ['cart' => [['product_id' => $trigger->id, 'variation_id' => $tItems[0]->id, 'quantity' => 10]]];
    }

    private function setup_s10_both_var_gift_in_cart(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        [$tv, $tItems] = $this->makeVariation('s10_both_var_gift_in_cart', 'trig-pres', ['caja']);
        $trigger = $this->makeProduct('s10_both_var_gift_in_cart', 'trigger', $t, $b, '', 100, $tv->id);
        $trigger->items()->sync([$tItems[0]->id => ['price' => 100, 'enabled' => 1, 'sku' => self::PREFIX.'s10_both_var_gift_in_cart-TRIG-V1']]);
        [$gv, $gItems] = $this->makeVariation('s10_both_var_gift_in_cart', 'gift-pres', ['unidad']);
        $gift = $this->makeProduct('s10_both_var_gift_in_cart', 'gift', $t, $b, 'GIFT', 50, $gv->id);
        $gift->items()->sync([$gItems[0]->id => ['price' => 50, 'enabled' => 1, 'sku' => self::PREFIX.'s10_both_var_gift_in_cart-GIFT-V1']]);
        $bonif = Bonification::query()->create([
            'name' => 'Buy 10 get 5', 'buy' => 10, 'get' => 5, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach($bonif->id);
        $this->setInventory($trigger, $tItems[0]->id, 50, self::PREFIX.'s10_both_var_gift_in_cart-TRIG-V1');
        $this->setInventory($gift, null, 0);
        $this->setInventory($gift, $gItems[0]->id, 8, self::PREFIX.'s10_both_var_gift_in_cart-GIFT-V1');

        return ['cart' => [
            ['product_id' => $trigger->id, 'variation_id' => $tItems[0]->id, 'quantity' => 10],
            ['product_id' => $gift->id, 'variation_id' => $gItems[0]->id, 'quantity' => 1],
        ]];
    }

    private function setup_s11_two_bonifications_same_gift(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        $trigger = $this->makeProduct('s11_two_bonifications_same_gift', 'trigger', $t, $b, 'TRIG');
        $gift = $this->makeProduct('s11_two_bonifications_same_gift', 'gift', $t, $b, 'GIFT', 0);
        $b1 = Bonification::query()->create([
            'name' => 'Buy 10 get 1', 'buy' => 10, 'get' => 1, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $b2 = Bonification::query()->create([
            'name' => 'Buy 20 get 1', 'buy' => 20, 'get' => 1, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach([$b1->id, $b2->id]);
        $this->setInventory($trigger, null, 100);
        $this->setInventory($gift, null, 20);

        return ['cart' => [['product_id' => $trigger->id, 'variation_id' => null, 'quantity' => 20]]];
    }

    private function setup_s12_max_zero(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        $trigger = $this->makeProduct('s12_max_zero', 'trigger', $t, $b, 'TRIG');
        $gift = $this->makeProduct('s12_max_zero', 'gift', $t, $b, 'GIFT', 0);
        $bonif = Bonification::query()->create([
            'name' => 'Buy 10 get 1 max 0', 'buy' => 10, 'get' => 1, 'product_id' => $gift->id, 'max' => 0, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach($bonif->id);
        $this->setInventory($trigger, null, 100);
        $this->setInventory($gift, null, 50);

        return ['cart' => [['product_id' => $trigger->id, 'variation_id' => null, 'quantity' => 10]]];
    }

    private function setup_s13_package_zero(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        $trigger = $this->makeProduct('s13_package_zero', 'trigger', $t, $b, 'TRIG', 100, null, 0);
        $gift = $this->makeProduct('s13_package_zero', 'gift', $t, $b, 'GIFT', 0);
        $bonif = Bonification::query()->create([
            'name' => 'Buy 10 get 1', 'buy' => 10, 'get' => 1, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach($bonif->id);
        $this->setInventory($trigger, null, 100);
        $this->setInventory($gift, null, 50);

        return ['cart' => [['product_id' => $trigger->id, 'variation_id' => null, 'quantity' => 10]]];
    }

    private function setup_s14_no_bodega(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        [$variation, $items] = $this->makeVariation('s14_no_bodega', 'pres', ['caja']);
        $trigger = $this->makeProduct('s14_no_bodega', 'trigger', $t, $b, 'TRIG');
        $gift = $this->makeProduct('s14_no_bodega', 'gift', $t, $b, '', 0, $variation->id);
        $gift->items()->sync([$items[0]->id => ['price' => 0, 'enabled' => 1, 'sku' => self::PREFIX.'s14_no_bodega-GIFT-V1']]);
        $bonif = Bonification::query()->create([
            'name' => 'Buy 10 get 1', 'buy' => 10, 'get' => 1, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach($bonif->id);

        return ['cart' => [['product_id' => $trigger->id, 'variation_id' => null, 'quantity' => 10]]];
    }

    private function setup_s15_variation_pivot_sku_empty(User $u, Zone $z, Tax $t, Vendor $v, Brand $b): array
    {
        [$variation, $items] = $this->makeVariation('s15_variation_pivot_sku_empty', 'pres', ['no-sku', 'with-sku']);
        $trigger = $this->makeProduct('s15_variation_pivot_sku_empty', 'trigger', $t, $b, 'TRIG');
        $gift = $this->makeProduct('s15_variation_pivot_sku_empty', 'gift', $t, $b, '', 0, $variation->id);
        $gift->items()->sync([
            $items[0]->id => ['price' => 0, 'enabled' => 1, 'sku' => ''],
            $items[1]->id => ['price' => 0, 'enabled' => 1, 'sku' => self::PREFIX.'s15_variation_pivot_sku_empty-GIFT-V2'],
        ]);
        $bonif = Bonification::query()->create([
            'name' => 'Buy 10 get 1', 'buy' => 10, 'get' => 1, 'product_id' => $gift->id, 'max' => 10, 'allow_discounts' => true,
        ]);
        $trigger->bonifications()->attach($bonif->id);
        $this->setInventory($trigger, null, 100);
        $this->setInventory($gift, $items[1]->id, 30, self::PREFIX.'s15_variation_pivot_sku_empty-GIFT-V2');

        return ['cart' => [['product_id' => $trigger->id, 'variation_id' => null, 'quantity' => 10]]];
    }
}
