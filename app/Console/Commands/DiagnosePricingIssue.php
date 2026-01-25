<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class DiagnosePricingIssue extends Command
{
    protected $signature = 'orders:diagnose-pricing {order_id}';
    protected $description = 'Diagnose pricing calculation issues for an order';

    public function handle()
    {
        $orderId = $this->argument('order_id');
        $order = Order::with(['products.product'])->find($orderId);

        if (!$order) {
            $this->error("Order {$orderId} not found");
            return 1;
        }

        $this->info("Diagnosing Order #{$order->id}");
        $this->line('');

        $table = [];
        
        foreach ($order->products as $orderProduct) {
            $product = $orderProduct->product;
            
            if (!$product) {
                continue;
            }

            $packageQty = $orderProduct->package_quantity ?? 1;
            $storedPrice = $orderProduct->price;
            $calculatePackagePrice = $product->calculate_package_price ?? false;
            
            // Current (buggy) calculation in OrderRepository
            $effectivePackageQty = $calculatePackagePrice ? $packageQty : 1;
            $currentSoapPrice = $effectivePackageQty ? $storedPrice / $effectivePackageQty : $storedPrice;
            
            // What it SHOULD be (stored price is already correct for SOAP)
            $correctSoapPrice = $storedPrice;
            
            $isSuspicious = ($currentSoapPrice > 0 && $currentSoapPrice < 500);
            
            $table[] = [
                $product->name,
                $product->sku,
                $packageQty,
                $calculatePackagePrice ? 'YES' : 'NO',
                '$' . number_format($storedPrice, 2),
                '$' . number_format($currentSoapPrice, 2) . ($isSuspicious ? ' ⚠️' : ''),
                '$' . number_format($correctSoapPrice, 2),
            ];
        }

        $this->table(
            [
                'Product',
                'SKU',
                'Pkg Qty',
                'Calc Pkg Price',
                'Stored Price (DB)',
                'Current SOAP Price',
                'CORRECT SOAP Price',
            ],
            $table
        );

        $this->line('');
        $this->warn('ISSUE: Stored price is being divided by package_quantity in SOAP generation');
        $this->warn('This causes double division for package products!');
        $this->line('');
        $this->info('FIX: The stored price is already correct - use it directly in SOAP');

        return 0;
    }
}
