<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductInventory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixDuplicateSkuInventory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:fix-duplicate-skus {--dry-run : Show what would be fixed without making changes} {--sku= : Fix only specific SKU}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix inventory for products with duplicate SKUs by copying inventory from products that have it to those that don\'t';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $specificSku = $this->option('sku');

        $this->info('Finding products with duplicate SKUs...');

        // Find products with duplicate SKUs
        $duplicateSkusQuery = Product::select('sku')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->havingRaw('COUNT(*) > 1');

        if ($specificSku) {
            $duplicateSkusQuery->where('sku', $specificSku);
        }

        $duplicateSkus = $duplicateSkusQuery->pluck('sku');

        if ($duplicateSkus->isEmpty()) {
            $this->info('No duplicate SKUs found.');
            return;
        }

        $this->info("Found {$duplicateSkus->count()} SKUs with duplicates.");

        $fixed = 0;
        $totalProcessed = 0;

        foreach ($duplicateSkus as $sku) {
            $products = Product::where('sku', $sku)->get();
            $this->line("Processing SKU: {$sku} ({$products->count()} products)");

            // Find products with inventory and without inventory
            $productsWithInventory = [];
            $productsWithoutInventory = [];

            foreach ($products as $product) {
                $hasInventory = ProductInventory::where('product_id', $product->id)->exists();
                if ($hasInventory) {
                    $productsWithInventory[] = $product;
                } else {
                    $productsWithoutInventory[] = $product;
                }
            }

            if (empty($productsWithoutInventory)) {
                $this->line("  ✓ All products with SKU {$sku} already have inventory");
                continue;
            }

            if (empty($productsWithInventory)) {
                $this->warn("  ⚠ No products with SKU {$sku} have inventory to copy from");
                continue;
            }

            // Use the first product with inventory as the source
            $sourceProduct = $productsWithInventory[0];
            $sourceInventories = ProductInventory::where('product_id', $sourceProduct->id)->get();

            $this->line("  Source: Product ID {$sourceProduct->id} ({$sourceProduct->name})");
            $this->line("  Copying to " . count($productsWithoutInventory) . " products without inventory:");

            foreach ($productsWithoutInventory as $targetProduct) {
                $this->line("    → Product ID {$targetProduct->id} ({$targetProduct->name})");

                if (!$dryRun) {
                    // Copy inventory records from source to target
                    foreach ($sourceInventories as $sourceInventory) {
                        ProductInventory::updateOrCreate([
                            'product_id' => $targetProduct->id,
                            'bodega_code' => $sourceInventory->bodega_code,
                        ], [
                            'available' => $sourceInventory->available,
                            'physical' => $sourceInventory->physical,
                            'reserved' => $sourceInventory->reserved,
                        ]);
                    }
                }

                $fixed++;
            }

            $totalProcessed++;
        }

        if ($dryRun) {
            $this->info("\n🔍 DRY RUN COMPLETE");
            $this->info("Would fix inventory for {$fixed} products across {$totalProcessed} SKU groups.");
            $this->info("Run without --dry-run to apply changes.");
        } else {
            $this->info("\n✅ COMPLETED");
            $this->info("Fixed inventory for {$fixed} products across {$totalProcessed} SKU groups.");
        }
    }
}