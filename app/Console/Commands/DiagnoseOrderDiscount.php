<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseOrderDiscount extends Command
{
    protected $signature = 'diagnose:order-discount {order_id}';
    protected $description = 'Diagnose discount discrepancies between order display and XML transmission';

    public function handle()
    {
        $orderId = $this->argument('order_id');
        
        $order = Order::with(['products.product', 'user', 'coupon'])->find($orderId);
        
        if (!$order) {
            $this->error("Order #{$orderId} not found");
            return 1;
        }

        $this->info("╔════════════════════════════════════════════════════════════════════════════════╗");
        $this->info("║  DISCOUNT DIAGNOSTIC FOR ORDER #{$orderId}");
        $this->info("╚════════════════════════════════════════════════════════════════════════════════╝");
        $this->newLine();

        // Order Level Discounts
        $this->info("ORDER LEVEL DISCOUNTS:");
        $this->info("─────────────────────────────────────────────────────────────────────────────────");
        $this->line("  Total Discount: $" . number_format($order->discount ?? 0, 2));
        $this->line("  Coupon Discount: $" . number_format($order->coupon_discount ?? 0, 2));
        $this->line("  Order Total: $" . number_format($order->total, 2));
        if ($order->coupon) {
            $this->line("  Coupon Code: {$order->coupon_code} ({$order->coupon->name})");
        }
        $this->newLine();

        // Get XML if available
        $xmlDiscounts = [];
        if ($order->request) {
            $this->info("XML ANALYSIS:");
            $this->info("─────────────────────────────────────────────────────────────────────────────────");
            try {
                $xml = simplexml_load_string($order->request);
                if ($xml) {
                    $namespaces = $xml->getNamespaces(true);
                    $xml->registerXPathNamespace('dyn', 'http://schemas.datacontract.org/2004/07/Dynamics.AX.Application');
                    
                    $xmlProducts = $xml->xpath('//dyn:listDetails');
                    if ($xmlProducts) {
                        foreach ($xmlProducts as $index => $xmlProduct) {
                            $sku = (string) $xmlProduct->itemId;
                            $xmlDiscount = (float) ($xmlProduct->discount ?? 0);
                            $xmlUnitPrice = (float) ($xmlProduct->unitPrice ?? 0);
                            $xmlQty = (int) ($xmlProduct->qty ?? 0);
                            
                            $xmlDiscounts[$sku] = [
                                'discount' => $xmlDiscount,
                                'unit_price' => $xmlUnitPrice,
                                'qty' => $xmlQty,
                            ];
                        }
                        $this->line("  Found " . count($xmlDiscounts) . " products in XML");
                    } else {
                        $this->warn("  No products found in XML");
                    }
                }
            } catch (\Exception $e) {
                $this->warn("  Could not parse XML: " . $e->getMessage());
            }
            $this->newLine();
        } else {
            $this->warn("  No XML request found for this order");
            $this->newLine();
        }

        // Product Level Analysis
        $this->info("PRODUCT LEVEL DISCOUNT ANALYSIS:");
        $this->info("─────────────────────────────────────────────────────────────────────────────────");
        
        $orderProducts = OrderProduct::where('order_id', $orderId)
            ->with('product')
            ->get();

        $hasDiscrepancy = false;
        $totalStoredDiscount = 0;
        $totalXmlDiscount = 0;
        $totalCalculatedDiscount = 0;

        // Get user's order history for discount calculation
        $user = $order->user;
        $hasOrders = $user ? \App\Models\Order::where('user_id', $user->id)->where('id', '!=', $orderId)->exists() : false;

        foreach ($orderProducts as $index => $orderProduct) {
            $product = $orderProduct->product;
            
            if (!$product) {
                $this->warn("Product ID {$orderProduct->product_id} not found");
                continue;
            }

            $this->newLine();
            $this->info("PRODUCT " . ($index + 1) . ": {$product->name}");
            $this->line("  SKU: {$product->sku}");
            $this->line("  Product ID: {$product->id}");
            $this->newLine();

            // Recalculate what discount should be based on brand/vendor
            $product->load('brand.vendor');
            $recalculatedPriceInfo = $product->getFinalPriceForUser($hasOrders);
            $expectedDiscountPercent = $recalculatedPriceInfo['discount'] ?? 0;
            $expectedDiscountSource = $recalculatedPriceInfo['discount_on'] ?? false;
            $expectedLineDiscount = $recalculatedPriceInfo['totalDiscount'] * $orderProduct->quantity;
            $expectedPrice = $recalculatedPriceInfo['price'];
            $originalPrice = $recalculatedPriceInfo['originalPrice'];

            // Stored Discount Information
            $this->line("├─ <fg=cyan>STORED IN ORDER_PRODUCTS TABLE</>");
            $this->line("│  ├─ Price: $" . number_format($orderProduct->price, 2));
            $this->line("│  ├─ Quantity: {$orderProduct->quantity}");
            $this->line("│  ├─ Discount Type: " . ($orderProduct->discount_type ?? 'percentage'));
            $this->line("│  ├─ Discount Percentage: " . ($orderProduct->percentage ?? 0) . "%");
            $this->line("│  ├─ Flat Discount Amount: $" . number_format($orderProduct->flat_discount_amount ?? 0, 2));
            
            // Calculate line discount from stored data
            $lineTotal = $orderProduct->price * $orderProduct->quantity;
            $storedDiscountPercent = (float) ($orderProduct->percentage ?? 0);
            $storedFlatDiscount = (float) ($orderProduct->flat_discount_amount ?? 0);
            $discountType = $orderProduct->discount_type ?? 'percentage';
            
            if ($discountType === 'fixed_amount' && $storedFlatDiscount > 0) {
                $lineDiscountAmount = $storedFlatDiscount * $orderProduct->quantity;
            } else {
                $lineDiscountAmount = ($lineTotal * $storedDiscountPercent) / 100;
            }
            
            $this->line("│  ├─ Line Total: $" . number_format($lineTotal, 2));
            $this->line("│  └─ Calculated Line Discount (from stored %): $" . number_format($lineDiscountAmount, 2));
            $totalStoredDiscount += $lineDiscountAmount;
            $this->newLine();

            // Show expected discount calculation
            $this->line("├─ <fg=blue>EXPECTED DISCOUNT CALCULATION (Brand/Vendor)</>");
            $this->line("│  ├─ Product Base Price: $" . number_format($originalPrice, 2));
            $this->line("│  ├─ Expected Discount: {$expectedDiscountPercent}%");
            if ($expectedDiscountSource) {
                $this->line("│  ├─ Discount Source: {$expectedDiscountSource}");
            } else {
                $this->line("│  ├─ Discount Source: None");
            }
            $this->line("│  ├─ Expected Price (with discount): $" . number_format($expectedPrice, 2));
            $this->line("│  ├─ Expected Line Discount: $" . number_format($expectedLineDiscount, 2));
            $this->line("│  └─ Stored Price: $" . number_format($orderProduct->price, 2));
            
            // Check if stored price matches expected price
            $priceDifference = abs($orderProduct->price - $expectedPrice);
            if ($priceDifference > 0.01) {
                $this->warn("│  └─ ⚠️  Price mismatch! Expected: $" . number_format($expectedPrice, 2) . ", Stored: $" . number_format($orderProduct->price, 2));
            } else {
                $this->line("│  └─ ✓ Price matches expected");
            }
            
            // Check if discount is missing from order_products
            if ($expectedDiscountPercent > 0 && $storedDiscountPercent == 0 && $storedFlatDiscount == 0) {
                $this->newLine();
                $this->warn("│  ⚠️  DISCOUNT NOT STORED IN ORDER_PRODUCTS!");
                $this->warn("│     Expected: {$expectedDiscountPercent}% from {$expectedDiscountSource}");
                $this->warn("│     Stored: 0%");
                $this->warn("│     This discount is calculated dynamically and not stored in order_products.percentage");
            }
            
            $totalCalculatedDiscount += $expectedLineDiscount;
            $this->newLine();

            // Simulate XML Calculation
            $this->line("├─ <fg=green>XML CALCULATION (simulated)</>");
            
            // Check if bonification
            $isBonification = DB::table('order_product_bonifications')
                ->where('order_id', $orderId)
                ->where('product_id', $orderProduct->product_id)
                ->exists();

            if ($isBonification) {
                $xmlDiscountPercent = 0;
                $this->line("│  ├─ Type: BONIFICATION (discount always 0%)");
            } else {
                // Simulate the logic from OrderRepository
                $packageQty = $orderProduct->package_quantity ?? 1;
                $shouldCalculatePackage = $product->calculate_package_price ?? false;
                
                if ($shouldCalculatePackage && $packageQty > 1) {
                    $baseUnitPrice = $orderProduct->price / $packageQty;
                } else {
                    $baseUnitPrice = $orderProduct->price;
                }

                // Handle discount type
                if ($discountType === 'fixed_amount' && $storedFlatDiscount > 0) {
                    // Flat discount: applied to unit price, percentage = 0
                    $minAllowedPrice = $baseUnitPrice * 0.1;
                    $maxAllowedReduction = max(0, $baseUnitPrice - $minAllowedPrice);
                    $effectiveFlatDiscount = min($storedFlatDiscount, $maxAllowedReduction);
                    $unitPrice = max($minAllowedPrice, $baseUnitPrice - $effectiveFlatDiscount);
                    $xmlDiscountPercent = 0;
                    
                    $this->line("│  ├─ Discount Type: FIXED AMOUNT");
                    $this->line("│  ├─ Base Unit Price: $" . number_format($baseUnitPrice, 2));
                    $this->line("│  ├─ Flat Discount: $" . number_format($storedFlatDiscount, 2));
                    $this->line("│  ├─ Effective Flat Discount: $" . number_format($effectiveFlatDiscount, 2));
                    $this->line("│  ├─ Final Unit Price: $" . number_format($unitPrice, 2));
                    if ($effectiveFlatDiscount < $storedFlatDiscount) {
                        $this->warn("│  ├─ ⚠️  Discount was capped to prevent price below 10% minimum");
                    }
                } else {
                    // Percentage discount
                    $xmlDiscountPercent = max(0, min(100, (int) $storedDiscountPercent));
                    $unitPrice = $baseUnitPrice;
                    
                    $this->line("│  ├─ Discount Type: PERCENTAGE");
                    $this->line("│  ├─ Base Unit Price: $" . number_format($baseUnitPrice, 2));
                }
                
                $this->line("│  ├─ XML Discount Percentage: {$xmlDiscountPercent}%");
                $this->line("│  └─ XML Unit Price: $" . number_format($unitPrice, 2));
            }
            $this->newLine();

            // Compare with actual XML
            if (!empty($xmlDiscounts)) {
                $this->line("├─ <fg=yellow>ACTUAL XML VALUES</>");
                if (isset($xmlDiscounts[$product->sku])) {
                    $xmlData = $xmlDiscounts[$product->sku];
                    $actualXmlDiscount = $xmlData['discount'];
                    $actualXmlPrice = $xmlData['unit_price'];
                    
                    $this->line("│  ├─ XML Discount: {$actualXmlDiscount}%");
                    $this->line("│  ├─ XML Unit Price: $" . number_format($actualXmlPrice, 2));
                    
                    // Check for discrepancies
                    if (abs($xmlDiscountPercent - $actualXmlDiscount) > 0.01) {
                        $hasDiscrepancy = true;
                        $this->newLine();
                        $this->error("│  ⚠️  DISCREPANCY DETECTED!");
                        $this->error("│     Expected XML Discount: {$xmlDiscountPercent}%");
                        $this->error("│     Actual XML Discount: {$actualXmlDiscount}%");
                        $this->error("│     Difference: " . abs($xmlDiscountPercent - $actualXmlDiscount) . "%");
                    }
                    
                    if (abs($unitPrice - $actualXmlPrice) > 0.01) {
                        $hasDiscrepancy = true;
                        $this->newLine();
                        $this->error("│  ⚠️  PRICE DISCREPANCY DETECTED!");
                        $this->error("│     Expected XML Price: $" . number_format($unitPrice, 2));
                        $this->error("│     Actual XML Price: $" . number_format($actualXmlPrice, 2));
                        $this->error("│     Difference: $" . number_format(abs($unitPrice - $actualXmlPrice), 2));
                    }
                } else {
                    $this->warn("│  └─ Product not found in XML (may have been skipped)");
                }
                $this->newLine();
            }

            // Summary for this product
            $this->line("├─ <fg=magenta>SUMMARY</>");
            if ($discountType === 'fixed_amount' && $storedFlatDiscount > 0) {
                $this->line("│  ├─ Display shows: Flat discount of $" . number_format($storedFlatDiscount, 2));
                $this->line("│  ├─ XML shows: 0% discount (applied to price instead)");
                $this->line("│  └─ This is EXPECTED behavior for fixed amount discounts");
            } else {
                $this->line("│  ├─ Display shows: {$storedDiscountPercent}% discount");
                $this->line("│  ├─ XML shows: {$xmlDiscountPercent}% discount");
                if ($storedDiscountPercent != $xmlDiscountPercent) {
                    $this->warn("│  └─ ⚠️  Percentage mismatch!");
                } else {
                    $this->line("│  └─ ✓ Match");
                }
            }
        }

        // Final Summary
        $this->newLine();
        $this->info("╔════════════════════════════════════════════════════════════════════════════════╗");
        $this->info("║  FINAL SUMMARY");
        $this->info("╚════════════════════════════════════════════════════════════════════════════════╝");
        $this->newLine();
        
        $this->line("Order Total Discount: $" . number_format($order->discount ?? 0, 2));
        $this->line("Order Coupon Discount: $" . number_format($order->coupon_discount ?? 0, 2));
        $this->line("Calculated from order_products.percentage: $" . number_format($totalStoredDiscount, 2));
        $this->line("Calculated from Brand/Vendor discounts: $" . number_format($totalCalculatedDiscount, 2));
        
        $discountDifference = abs($order->discount - $totalCalculatedDiscount);
        if ($discountDifference > 0.01) {
            $this->newLine();
            $this->warn("⚠️  DISCOUNT MISMATCH DETECTED!");
            $this->warn("   Order discount: $" . number_format($order->discount, 2));
            $this->warn("   Calculated discount: $" . number_format($totalCalculatedDiscount, 2));
            $this->warn("   Difference: $" . number_format($discountDifference, 2));
        } else {
            $this->newLine();
            $this->info("✓ Order discount matches calculated brand/vendor discounts.");
        }
        
        if ($totalStoredDiscount == 0 && $order->discount > 0) {
            $this->newLine();
            $this->info("ℹ️  EXPLANATION:");
            $this->info("   The order has a discount of $" . number_format($order->discount, 2) . " but");
            $this->info("   order_products.percentage is 0% for all products.");
            $this->info("   This means the discount comes from Brand or Vendor level discounts,");
            $this->info("   which are calculated dynamically and NOT stored in order_products.");
            $this->info("   The discount is applied to the price when stored, but the percentage");
            $this->info("   is not recorded. This is EXPECTED behavior.");
        }
        
        if ($hasDiscrepancy) {
            $this->newLine();
            $this->error("⚠️  DISCREPANCIES FOUND BETWEEN STORED VALUES AND XML");
            $this->error("   Review the product-level analysis above for details.");
        } else {
            $this->newLine();
            $this->info("✓ No discrepancies found. All discounts match between order and XML.");
        }

        // Common Issues Checklist
        $this->newLine();
        $this->info("COMMON ISSUES CHECKLIST:");
        $this->line("  [ ] Fixed amount discount: Display shows discount, XML shows 0% (EXPECTED)");
        $this->line("  [ ] Discount capped: Flat discount reduced to prevent price < 10% minimum");
        $this->line("  [ ] Bonification product: Always has 0% discount in XML");
        $this->line("  [ ] Coupon discount: May not appear in product-level XML (check order-level)");
        $this->line("  [ ] Percentage clamped: Discount > 100% or < 0% was adjusted");

        return 0;
    }
}
