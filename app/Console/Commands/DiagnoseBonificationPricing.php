<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderProductBonification;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseBonificationPricing extends Command
{
    protected $signature = 'diagnose:bonification-pricing {order_ids* : Order IDs to diagnose (space-separated)}';
    protected $description = 'Diagnose bonification pricing issues for specific orders';

    public function handle()
    {
        $orderIds = $this->argument('order_ids');

        foreach ($orderIds as $orderId) {
            $this->diagnoseOrder($orderId);
            $this->newLine();
            $this->line(str_repeat('═', 100));
            $this->newLine();
        }

        return 0;
    }

    protected function diagnoseOrder($orderId)
    {
        $order = Order::with(['products', 'bonifications', 'zone', 'user'])->find($orderId);

        if (!$order) {
            $this->error("Order {$orderId} not found");
            return;
        }

        $this->info("╔════════════════════════════════════════════════════════════════════════════════╗");
        $this->info("║  BONIFICATION PRICING DIAGNOSTIC FOR ORDER #{$orderId}");
        $this->info("╚════════════════════════════════════════════════════════════════════════════════╝");
        $this->newLine();

        $this->info("📋 ORDER INFO:");
        $this->table(
            ['Field', 'Value'],
            [
                ['Order ID', $order->id],
                ['Created', $order->created_at->format('Y-m-d H:i:s')],
                ['Status', $this->getStatusName($order->status_id)],
                ['User', $order->user->name ?? 'N/A'],
                ['Total', '$' . number_format($order->total, 2)],
            ]
        );

        // Regular products in order
        $this->newLine();
        $this->info("📦 REGULAR PRODUCTS IN ORDER (order_products table):");
        
        if ($order->products->isEmpty()) {
            $this->warn("  No regular products found");
        } else {
            $productRows = [];
            foreach ($order->products as $orderProduct) {
                $product = Product::find($orderProduct->product_id);
                $productRows[] = [
                    $orderProduct->product_id,
                    $product->name ?? 'N/A',
                    $product->sku ?? 'N/A',
                    $orderProduct->quantity,
                    '$' . number_format($orderProduct->price, 2),
                    $orderProduct->package_quantity,
                    $product->calculate_package_price ? 'YES' : 'NO',
                    '$' . number_format($product->price, 2),
                ];
            }
            $this->table(
                ['Product ID', 'Name', 'SKU', 'Qty', 'Order Price', 'Pkg Qty', 'Calc Pkg Price', 'Current DB Price'],
                $productRows
            );
        }

        // Bonifications
        $this->newLine();
        $this->info("🎁 BONIFICATIONS (order_product_bonifications table):");
        
        $bonifications = OrderProductBonification::where('order_id', $orderId)->get();
        
        if ($bonifications->isEmpty()) {
            $this->warn("  No bonifications found for this order");
        } else {
            foreach ($bonifications as $bonif) {
                $product = Product::find($bonif->product_id);
                
                $this->newLine();
                $this->line("  ┌─────────────────────────────────────────────────────────────────────────");
                $this->line("  │ BONIFICATION ID: {$bonif->id}");
                $this->line("  ├─────────────────────────────────────────────────────────────────────────");
                
                $this->table(
                    ['Field', 'Stored Value', 'Product DB Value', 'Notes'],
                    [
                        ['product_id', $bonif->product_id, $product->id ?? 'N/A', ''],
                        ['Product Name', '-', $product->name ?? 'N/A', ''],
                        ['Product SKU', '-', $product->sku ?? 'N/A', 'This SKU will be sent to SOAP'],
                        ['quantity', $bonif->quantity, '-', 'Bonification qty stored'],
                        ['variation_item_id', $bonif->variation_item_id ?? 'NULL', '-', ''],
                        ['bonification_id', $bonif->bonification_id, '-', 'Links to bonifications table'],
                    ]
                );
                
                $this->newLine();
                $this->warn("  ⚡ SOAP CALCULATION SIMULATION:");
                
                // Simulate what OrderRepository does
                $effectivePackageQuantity = 1; // Forced to 1 for bonifications
                $unitPrice = 0; // Forced to 0 for bonifications
                $discountPercentage = 0;
                $qty = $bonif->quantity; // No multiplication for bonifications
                
                // But let's also show what WOULD happen if bonification flag wasn't set
                $productPackageQty = $product->package_quantity ?? 1;
                $productCalcPkgPrice = $product->calculate_package_price ?? false;
                
                $wrongEffectivePkgQty = $productCalcPkgPrice ? $productPackageQty : 1;
                $wrongUnitPrice = $wrongEffectivePkgQty > 0 ? ($product->price / $wrongEffectivePkgQty) : $product->price;
                $wrongQty = $bonif->quantity * $wrongEffectivePkgQty;
                
                $this->table(
                    ['Calculation', 'With bonification=1 (CORRECT)', 'If bonification=0 (WRONG)'],
                    [
                        ['effectivePackageQuantity', '1 (forced)', $wrongEffectivePkgQty],
                        ['unitPrice', '$0.00 (forced)', '$' . number_format($wrongUnitPrice, 2)],
                        ['qty sent to SOAP', $qty, $wrongQty],
                        ['SOAP Total', '$0.00', '$' . number_format($wrongUnitPrice * $wrongQty, 2)],
                    ]
                );
                
                $this->newLine();
                $this->info("  📊 PRODUCT DETAILS FOR BONIFICATION:");
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Product ID', $product->id ?? 'N/A'],
                        ['Name', $product->name ?? 'N/A'],
                        ['SKU', $product->sku ?? 'N/A'],
                        ['Price (DB)', '$' . number_format($product->price ?? 0, 2)],
                        ['package_quantity', $productPackageQty],
                        ['calculate_package_price', $productCalcPkgPrice ? 'TRUE' : 'FALSE'],
                    ]
                );
                
                // Check if bonification is being called with bonification=1 flag
                $this->newLine();
                $this->info("  🔍 CHECKING ORDER RESPONSE/REQUEST FOR CLUES:");
                
                if ($order->response) {
                    $this->line("  Response (first 500 chars): " . substr($order->response, 0, 500));
                } else {
                    $this->warn("  No response stored");
                }
            }
        }

        // Check if there's a bonification order created
        $this->newLine();
        $this->info("🔗 CHECKING FOR BONIFICATION ORDER:");
        
        $bonificationOrder = Order::where('user_id', $order->user_id)
            ->where('zone_id', $order->zone_id)
            ->where('observations', 'Bonificaciones')
            ->where('created_at', '>=', $order->created_at->subMinutes(5))
            ->where('created_at', '<=', $order->created_at->addMinutes(5))
            ->first();
        
        if ($bonificationOrder) {
            $this->info("  Found bonification order: #{$bonificationOrder->id}");
            $this->table(
                ['Field', 'Value'],
                [
                    ['Bonification Order ID', $bonificationOrder->id],
                    ['Created', $bonificationOrder->created_at->format('Y-m-d H:i:s')],
                    ['Status', $this->getStatusName($bonificationOrder->status_id)],
                    ['Total', '$' . number_format($bonificationOrder->total, 2)],
                    ['Response', substr($bonificationOrder->response ?? 'NULL', 0, 200)],
                ]
            );
        } else {
            $this->warn("  No separate bonification order found");
        }

        // Check SOAP logs if available
        $this->newLine();
        $this->info("📝 TO CHECK SOAP LOGS, run on server:");
        $this->line("  grep -A 50 'order_id.*{$orderId}' storage/logs/soap*.log | head -200");
    }

    protected function getStatusName($statusId): string
    {
        return match ($statusId) {
            Order::STATUS_WAITING => 'Esperando Transmisión',
            Order::STATUS_PENDING => 'Pendiente',
            Order::STATUS_PROCESSED => 'Procesado',
            Order::STATUS_ERROR_WEBSERVICE => 'Error WebService',
            default => "Desconocido ({$statusId})",
        };
    }
}
