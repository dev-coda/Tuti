<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Repositories\OrderRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseOrderXml extends Command
{
    protected $signature = 'diagnose:order-xml {order_id}';
    protected $description = 'Diagnose SOAP XML pricing for an order with bonifications and package quantities';

    public function handle()
    {
        $orderId = $this->argument('order_id');
        
        $order = Order::with(['products', 'zone', 'user'])->find($orderId);
        
        if (!$order) {
            $this->error("Order #{$orderId} not found");
            return 1;
        }

        $this->info("╔════════════════════════════════════════════════════════════════════════════════╗");
        $this->info("║  SOAP XML PRICING DIAGNOSTIC FOR ORDER #{$orderId}");
        $this->info("╚════════════════════════════════════════════════════════════════════════════════╝");
        $this->newLine();

        $this->info("Order Details:");
        $this->line("  Created: {$order->created_at}");
        $this->line("  Total: $" . number_format($order->total, 2));
        $this->line("  User: {$order->user->name} ({$order->user->document})");
        $this->newLine();

        // Get order products from pivot table
        $orderProducts = DB::table('order_products')
            ->where('order_id', $orderId)
            ->get();

        $this->info("Products in Order:");
        $this->info("─────────────────────────────────────────────────────────────────────────────────");
        
        foreach ($orderProducts as $index => $orderProduct) {
            $product = \App\Models\Product::find($orderProduct->product_id);
            
            if (!$product) {
                $this->warn("Product ID {$orderProduct->product_id} not found");
                continue;
            }

            $this->newLine();
            $this->info("PRODUCT " . ($index + 1) . ": {$product->name}");
            $this->line("  Product ID: {$product->id}");
            $this->line("  SKU: {$product->sku}");
            $this->newLine();

            // Stored in order_products
            $this->line("├─ <fg=cyan>STORED IN ORDER_PRODUCTS</>");
            $this->line("│  ├─ Quantity: {$orderProduct->quantity}");
            $this->line("│  ├─ Price (per package): $" . number_format($orderProduct->price, 2));
            $this->line("│  ├─ Package Quantity: {$orderProduct->package_quantity}");
            $this->line("│  ├─ Discount %: {$orderProduct->percentage}%");
            $this->line("│  └─ Line Total: $" . number_format($orderProduct->price * $orderProduct->quantity, 2));
            $this->newLine();

            // Product settings
            $this->line("├─ <fg=yellow>PRODUCT SETTINGS</>");
            $this->line("│  ├─ Current price: $" . number_format($product->price, 2));
            $this->line("│  ├─ Package quantity: " . ($product->package_quantity ?? 1));
            $this->line("│  ├─ Calculate package price: " . ($product->calculate_package_price ? 'YES' : 'NO'));
            $this->line("│  └─ Has bonifications: " . ($product->bonifications->count() > 0 ? 'YES (' . $product->bonifications->count() . ')' : 'NO'));
            $this->newLine();

            // Check for bonification products
            $bonifications = DB::table('order_product_bonifications')
                ->where('order_id', $orderId)
                ->where('order_product_id', $orderProduct->product_id)
                ->get();

            if ($bonifications->count() > 0) {
                $this->line("├─ <fg=magenta>BONIFICATIONS TRIGGERED</>");
                foreach ($bonifications as $bonif) {
                    $bonifProduct = \App\Models\Product::find($bonif->product_id);
                    $this->line("│  ├─ Bonification: " . ($bonifProduct->name ?? "Product {$bonif->product_id}"));
                    $this->line("│  ├─ Quantity: {$bonif->quantity}");
                    $this->line("│  └─ Variation: " . ($bonif->variation_item_id ?? 'None'));
                }
                $this->newLine();
            }

            // SOAP XML Calculation Simulation
            $this->line("├─ <fg=green>SOAP XML CALCULATION (simulated)</>");
            
            // Determine if this is a bonification product (price should be 0)
            $isBonificationProduct = false; // This product triggers bonifications, but isn't itself a bonification
            
            // Check if this product appears in order_product_bonifications as the bonification product
            $isBonificationProduct = DB::table('order_product_bonifications')
                ->where('order_id', $orderId)
                ->where('product_id', $orderProduct->product_id)
                ->exists();

            if ($isBonificationProduct) {
                $this->line("│  ├─ Type: BONIFICATION PRODUCT");
                $this->line("│  ├─ Unit Price: $0.00 (bonifications always free)");
                $this->line("│  ├─ Quantity: {$orderProduct->quantity}");
                $this->line("│  └─ Total: $0.00");
            } else {
                // Regular product logic
                $effectivePackageQuantity = $product->calculate_package_price ? $orderProduct->package_quantity : 1;
                $baseUnitPrice = $effectivePackageQuantity ? 
                    round($orderProduct->price / $effectivePackageQuantity, 2) : 
                    round($orderProduct->price, 2);

                // Calculate quantity with package multiplication
                $soapQuantity = $effectivePackageQuantity ? 
                    $orderProduct->quantity * $effectivePackageQuantity : 
                    $orderProduct->quantity;

                $this->line("│  ├─ Type: REGULAR PRODUCT");
                $this->line("│  ├─ Effective Package Qty: {$effectivePackageQuantity}");
                $this->line("│  ├─ Base Unit Price: $" . number_format($baseUnitPrice, 2));
                $this->line("│  │  └─ Calculation: \${$orderProduct->price} ÷ {$effectivePackageQuantity} = \${$baseUnitPrice}");
                $this->line("│  ├─ SOAP Quantity: {$soapQuantity}");
                $this->line("│  │  └─ Calculation: {$orderProduct->quantity} × {$effectivePackageQuantity} = {$soapQuantity}");
                $this->line("│  └─ SOAP Total: $" . number_format($baseUnitPrice * $soapQuantity, 2));
                
                // Verification
                $expectedTotal = $orderProduct->price * $orderProduct->quantity;
                $soapTotal = $baseUnitPrice * $soapQuantity;
                
                if (abs($expectedTotal - $soapTotal) > 0.10) {
                    $this->newLine();
                    $this->error("│  ⚠️  MISMATCH DETECTED!");
                    $this->error("│     Expected: $" . number_format($expectedTotal, 2));
                    $this->error("│     SOAP Sends: $" . number_format($soapTotal, 2));
                    $this->error("│     Difference: $" . number_format(abs($expectedTotal - $soapTotal), 2));
                }
            }
            
            $this->newLine();
        }

        return 0;
    }
}
