<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DiagnoseOrderPricing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'diagnose:order-pricing {order_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose price discrepancies between product DB, order storage, and SOAP generation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orderId = $this->argument('order_id');
        
        // Load order with products
        $order = Order::with(['products', 'zone'])->find($orderId);
        
        if (!$order) {
            $this->error("Order #{$orderId} not found");
            return 1;
        }

        $this->info("╔════════════════════════════════════════════════════════════════════════════════╗");
        $this->info("║  PRICE DIAGNOSTIC FOR ORDER #{$orderId}                                        ");
        $this->info("╚════════════════════════════════════════════════════════════════════════════════╝");
        $this->newLine();
        
        $this->info("Order Created: {$order->created_at->format('Y-m-d H:i:s')}");
        $this->info("Order Status: " . $this->getStatusName($order->status_id));
        $zoneName = $order->zone ? $order->zone->zone : 'N/A';
        $this->info("Zone: {$zoneName} (ID: {$order->zone_id})");
        $this->newLine();

        // Cache diagnostics
        $this->displayCacheDiagnostics();

        // Analyze each product
        $orderProducts = DB::table('order_products')
            ->where('order_id', $orderId)
            ->get();

        $mismatchCount = 0;
        $totalProducts = $orderProducts->count();

        foreach ($orderProducts as $index => $orderProduct) {
            $this->newLine();
            $this->info("─────────────────────────────────────────────────────────────────────────────────");
            $this->info("PRODUCT " . ($index + 1) . " of {$totalProducts}");
            $this->info("─────────────────────────────────────────────────────────────────────────────────");
            
            $hasMismatch = $this->analyzeProduct($orderProduct, $order);
            if ($hasMismatch) {
                $mismatchCount++;
            }
        }

        // Summary
        $this->newLine();
        $this->info("╔════════════════════════════════════════════════════════════════════════════════╗");
        $this->info("║  SUMMARY                                                                       ║");
        $this->info("╚════════════════════════════════════════════════════════════════════════════════╝");
        $this->newLine();
        
        if ($mismatchCount > 0) {
            $this->warn("⚠️  Found {$mismatchCount} product(s) with price mismatches out of {$totalProducts} total");
            $this->warn("⚠️  Orders created AFTER price updates but stored OLD prices");
            $this->newLine();
            $this->error("LIKELY CAUSES:");
            $this->error("  1. OPcache not cleared after price updates");
            $this->error("  2. Eloquent model attribute caching");
            $this->error("  3. Query result caching in MySQL");
            $this->error("  4. Cart session containing stale price references");
        } else {
            $this->info("✓ No price mismatches detected - all prices are consistent");
        }

        return 0;
    }

    protected function analyzeProduct($orderProduct, $order)
    {
        $productId = $orderProduct->product_id;
        
        // A. Get current product state from DB (fresh query, no cache)
        $currentProduct = Product::whereId($productId)->first();
        
        if (!$currentProduct) {
            $this->error("Product ID {$productId} - NOT FOUND IN DATABASE");
            return false;
        }

        $this->info("Product: {$currentProduct->name} (ID: {$productId})");
        $this->info("SKU: {$currentProduct->sku}");
        $this->newLine();

        // Get variation price if applicable
        $variationPrice = null;
        $variationName = null;
        if ($orderProduct->variation_item_id) {
            $variationData = DB::table('product_item_variation')
                ->join('variation_items', 'product_item_variation.variation_item_id', '=', 'variation_items.id')
                ->where('product_item_variation.product_id', $productId)
                ->where('product_item_variation.variation_item_id', $orderProduct->variation_item_id)
                ->select('product_item_variation.price', 'variation_items.name')
                ->first();
            
            if ($variationData) {
                $variationPrice = $variationData->price;
                $variationName = $variationData->name;
            }
        }

        // B. Display current DB state
        $this->line("├─ <fg=cyan>CURRENT DB STATE</>");
        $this->line("│  ├─ products.price: $" . number_format($currentProduct->price, 2));
        $this->line("│  ├─ products.updated_at: " . $currentProduct->updated_at->format('Y-m-d H:i:s'));
        if ($variationPrice !== null) {
            $this->line("│  ├─ Variation: {$variationName} (ID: {$orderProduct->variation_item_id})");
            $this->line("│  └─ Variation price: $" . number_format($variationPrice, 2));
        } else {
            $this->line("│  └─ Variation: None");
        }
        $this->newLine();

        // C. Display order stored state
        $orderProductCreatedAt = DB::table('order_products')
            ->where('order_id', $order->id)
            ->where('product_id', $productId)
            ->value('created_at');

        $timeDelta = $order->created_at->diffInMinutes($currentProduct->updated_at, false);
        $timeDeltaFormatted = abs($timeDelta) . " minutes";
        $orderBeforeUpdate = $timeDelta > 0; // positive means product was updated before order

        $this->line("├─ <fg=yellow>ORDER STORED STATE</>");
        $this->line("│  ├─ order_products.price: $" . number_format($orderProduct->price, 2));
        $this->line("│  ├─ Order created: " . $order->created_at->format('Y-m-d H:i:s'));
        $this->line("│  ├─ Package quantity: " . ($orderProduct->package_quantity ?? 1));
        
        if ($orderBeforeUpdate) {
            $this->line("│  └─ Time delta: <fg=green>{$timeDeltaFormatted} BEFORE price update</> ✓");
        } else {
            $this->line("│  └─ Time delta: <fg=red>{$timeDeltaFormatted} AFTER price update</> ⚠️");
        }
        $this->newLine();

        // D. Simulate SOAP generation logic (from OrderRepository.php:189-191)
        $effectivePackageQuantity = $currentProduct->calculate_package_price ? ($orderProduct->package_quantity ?? 1) : 1;
        
        // This is what SOAP WOULD calculate based on order_products table
        $soapUnitPrice = $effectivePackageQuantity ? 
            round($orderProduct->price / $effectivePackageQuantity, 2) : 
            round($orderProduct->price, 2);

        $this->line("├─ <fg=magenta>SOAP GENERATION (simulated)</>");
        $this->line("│  ├─ Uses price from: order_products table");
        $this->line("│  ├─ Stored price: $" . number_format($orderProduct->price, 2));
        $this->line("│  ├─ Package qty: {$effectivePackageQuantity}");
        $this->line("│  ├─ Calculate package price: " . ($currentProduct->calculate_package_price ? 'Yes' : 'No'));
        $this->line("│  └─ Final unit price in SOAP: $" . number_format($soapUnitPrice, 2));
        $this->newLine();

        // E. Diagnosis
        $expectedPrice = $variationPrice ?? $currentProduct->price;
        $hasMismatch = abs($orderProduct->price - $expectedPrice) > 0.01;

        $this->line("└─ <fg=white;bg=blue> DIAGNOSIS </>");
        
        if ($hasMismatch && !$orderBeforeUpdate) {
            $this->error("   └─ ⚠️  PRICE MISMATCH DETECTED!");
            $this->error("      Order created AFTER price update but stored OLD price.");
            $this->error("      Expected: $" . number_format($expectedPrice, 2));
            $this->error("      Stored: $" . number_format($orderProduct->price, 2));
            $this->error("      Difference: $" . number_format(abs($orderProduct->price - $expectedPrice), 2));
            return true;
        } elseif ($hasMismatch && $orderBeforeUpdate) {
            $this->comment("   └─ ℹ️  Price differs from current DB but order was created BEFORE update");
            $this->comment("      This is expected behavior (historical pricing)");
            return false;
        } else {
            $this->info("   └─ ✓ No mismatch - price is consistent");
            return false;
        }
    }

    protected function displayCacheDiagnostics()
    {
        $this->info("─────────────────────────────────────────────────────────────────────────────────");
        $this->info("CACHE DIAGNOSTICS");
        $this->info("─────────────────────────────────────────────────────────────────────────────────");
        
        // Laravel cache driver
        $cacheDriver = config('cache.default');
        $this->line("Laravel Cache Driver: <fg=cyan>{$cacheDriver}</>");
        
        // Check if cache stores product data
        $testProductId = DB::table('products')->value('id');
        if ($testProductId) {
            $cacheKey = "product_{$testProductId}";
            $cached = Cache::has($cacheKey);
            $this->line("Product cache test (key: {$cacheKey}): " . ($cached ? '<fg=red>CACHED</>' : '<fg=green>Not cached</>'));
        }

        // OPcache status (if available)
        if (function_exists('opcache_get_status')) {
            $opcacheStatus = opcache_get_status(false);
            if ($opcacheStatus) {
                $this->line("OPcache: <fg=cyan>Enabled</>");
                $this->line("OPcache memory: " . round($opcacheStatus['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB");
            } else {
                $this->line("OPcache: <fg=yellow>Disabled or status unavailable</>");
            }
        } else {
            $this->line("OPcache: <fg=yellow>Status check not available</>");
        }

        // Query cache (MySQL)
        try {
            $queryCacheStatus = DB::select("SHOW STATUS LIKE 'Qcache%'");
            if (!empty($queryCacheStatus)) {
                $this->line("MySQL Query Cache: <fg=cyan>Available</>");
            }
        } catch (\Exception $e) {
            // Query cache might not be available or enabled
        }

        $this->newLine();
    }

    protected function getStatusName($statusId)
    {
        $statuses = [
            0 => 'Pending',
            1 => 'Processed',
            2 => 'Error',
            3 => 'Waiting',
        ];

        return $statuses[$statusId] ?? "Unknown ({$statusId})";
    }
}
