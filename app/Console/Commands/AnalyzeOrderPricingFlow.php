<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Console\Command;

class AnalyzeOrderPricingFlow extends Command
{
    protected $signature = 'orders:analyze-pricing-flow {order_id}';
    protected $description = 'Analyze the complete pricing flow from product to SOAP for an order';

    public function handle()
    {
        $orderId = $this->argument('order_id');
        $order = Order::with(['products.product'])->find($orderId);

        if (!$order) {
            $this->error("Order {$orderId} not found");
            return 1;
        }

        $this->info("Analyzing Order #{$order->id} - Complete Pricing Flow");
        $this->line('');

        foreach ($order->products as $index => $orderProduct) {
            $product = $orderProduct->product;
            
            if (!$product) {
                $this->warn("Product #{$orderProduct->product_id} not found - skipping");
                continue;
            }

            $this->info("Product #{$product->id}: {$product->name}");
            $this->line(str_repeat('=', 80));

            // Step 1: Product configuration
            $this->comment('STEP 1: Product Configuration');
            $productTable = [
                ['DB price (products.price)', '$' . number_format($product->price, 2)],
                ['package_quantity', $product->package_quantity ?? 1],
                ['calculate_package_price', $product->calculate_package_price ? 'TRUE' : 'FALSE'],
            ];
            $this->table(['Field', 'Value'], $productTable);

            // Step 2: What Product model returns
            $this->comment('STEP 2: Product->getFinalPriceForUser() Returns');
            $finalPrice = $product->getFinalPriceForUser(false);
            $priceTable = [
                ['originalPrice', '$' . number_format($finalPrice['originalPrice'] ?? 0, 2)],
                ['price (with package)', '$' . number_format($finalPrice['price'] ?? 0, 2)],
                ['discount', ($finalPrice['discount'] ?? 0) . '%'],
            ];
            $this->table(['Field', 'Value'], $priceTable);

            // Step 3: What's stored in order_products
            $this->comment('STEP 3: Stored in order_products Table');
            $storedTable = [
                ['price (order_products.price)', '$' . number_format($orderProduct->price, 2)],
                ['quantity', $orderProduct->quantity],
                ['package_quantity', $orderProduct->package_quantity ?? 1],
                ['percentage discount', $orderProduct->percentage . '%'],
            ];
            $this->table(['Field', 'Value'], $storedTable);

            // Step 4: SOAP calculation (current logic)
            $this->comment('STEP 4: SOAP Generation (Current Logic)');
            $effectivePackageQty = $product->calculate_package_price ? $orderProduct->package_quantity : 1;
            $soapUnitPrice = $effectivePackageQty ? $orderProduct->price / $effectivePackageQty : $orderProduct->price;
            $soapQty = $orderProduct->quantity * $effectivePackageQty;
            
            $soapTable = [
                ['effectivePackageQty', $effectivePackageQty],
                ['SOAP unitPrice', '$' . number_format($soapUnitPrice, 2)],
                ['SOAP qty', $soapQty],
                ['SOAP line total', '$' . number_format($soapUnitPrice * $soapQty, 2)],
            ];
            $this->table(['Field', 'Value'], $soapTable);

            // Step 5: Expected values based on user examples
            $this->comment('STEP 5: Expected SOAP Values (Based on Examples)');
            $expectedUnitPrice = $product->price;  // Should be DB price per unit
            $expectedQty = $orderProduct->quantity * ($product->package_quantity ?? 1);
            
            $expectedTable = [
                ['Expected unitPrice', '$' . number_format($expectedUnitPrice, 2)],
                ['Expected qty', $expectedQty],
                ['Expected line total', '$' . number_format($expectedUnitPrice * $expectedQty, 2)],
            ];
            $this->table(['Field', 'Value'], $expectedTable);

            // Analysis
            $this->comment('ANALYSIS:');
            if (abs($soapUnitPrice - $expectedUnitPrice) > 0.01) {
                $this->error("❌ SOAP unitPrice ($soapUnitPrice) != Expected ($expectedUnitPrice)");
            } else {
                $this->info("✓ SOAP unitPrice matches expected");
            }

            if ($soapQty != $expectedQty) {
                $this->error("❌ SOAP qty ($soapQty) != Expected ($expectedQty)");
            } else {
                $this->info("✓ SOAP qty matches expected");
            }

            $this->line('');
            $this->line('');
        }

        return 0;
    }
}
