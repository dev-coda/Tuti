<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class CouponDiscountService
{
    /**
     * Apply coupon discount to cart products following the specified rules
     * 
     * @param Coupon $coupon
     * @param User $user
     * @param Collection $cartProducts
     * @param bool $hasOrders
     * @return array
     */
    public function applyCouponDiscountToProducts(Coupon $coupon, User $user, Collection $cartProducts, bool $hasOrders = false): array
    {
        $couponService = app(CouponService::class);

        // First validate and get basic coupon application data
        $couponResult = $couponService->applyCouponToCart($coupon, $user, $cartProducts, $hasOrders);

        if (!$couponResult['success']) {
            return $couponResult;
        }

        $totalDiscountAmount = $couponResult['discount_amount'];
        $applicableProducts = $couponResult['applicable_products'];
        $totalCartValue = $couponResult['total_cart_value'];

        // Apply discount based on coupon type
        if ($coupon->type === Coupon::TYPE_PERCENTAGE) {
            return $this->applyPercentageCouponDiscount($coupon, $cartProducts, $applicableProducts, $hasOrders);
        } else {
            return $this->applyFixedAmountCouponDiscount($coupon, $cartProducts, $applicableProducts, $totalDiscountAmount, $hasOrders);
        }
    }

    /**
     * Apply percentage coupon discount to product discount fields
     * Rule: Apply percentage to each product's discount field, using larger value if competing discounts exist
     */
    private function applyPercentageCouponDiscount(Coupon $coupon, Collection $cartProducts, array $applicableProducts, bool $hasOrders): array
    {
        $modifiedProducts = [];
        $totalCouponDiscount = 0;

        foreach ($cartProducts as $cartItem) {
            $product = Product::with(['brand.vendor', 'categories'])->find($cartItem['product_id']);
            if (!$product) continue;

            $quantity = $cartItem['quantity'];
            $couponPercentage = 0;

            // Check if this product is applicable for the coupon
            $isApplicable = $this->isProductApplicableForCoupon($product, $applicableProducts);

            if ($isApplicable) {
                $couponPercentage = $coupon->value; // This is the percentage value
            }

            // Get existing discount information
            $existingPriceInfo = $product->getFinalPriceForUser($hasOrders);
            $existingDiscountPercentage = $existingPriceInfo['discount'];

            // Use the larger discount percentage
            $finalDiscountPercentage = max($existingDiscountPercentage, $couponPercentage);

            // Calculate base price (with variations if applicable)
            $basePrice = $product->price;
            $variation = $product->items->where('id', $cartItem['variation_id'])->first();
            if ($variation) {
                $basePrice = $variation->pivot->price;
            }

            // Calculate discount amounts
            $packageQuantity = $product->package_quantity ?? 1;
            $lineDiscountAmount = ($basePrice * $finalDiscountPercentage / 100) * $quantity * $packageQuantity;
            $couponContribution = ($basePrice * $couponPercentage / 100) * $quantity * $packageQuantity;

            $totalCouponDiscount += $couponContribution;

            $modifiedProducts[] = [
                'product_id' => $product->id,
                'variation_id' => $cartItem['variation_id'] ?? null,
                'quantity' => $quantity,
                'base_price' => $basePrice,
                'package_quantity' => $packageQuantity,
                'applied_discount_percentage' => $finalDiscountPercentage,
                'existing_discount_percentage' => $existingDiscountPercentage,
                'coupon_discount_percentage' => $couponPercentage,
                'coupon_contribution' => $couponContribution,
                'line_discount_amount' => $lineDiscountAmount,
                'discount_source' => $finalDiscountPercentage > $existingDiscountPercentage ? 'coupon' : 'existing',
            ];
        }

        return [
            'success' => true,
            'type' => 'percentage',
            'total_coupon_discount' => $totalCouponDiscount,
            'modified_products' => $modifiedProducts,
            'message' => 'Percentage coupon applied successfully'
        ];
    }

    /**
     * Apply fixed amount coupon discount proportionally to unit prices
     * Rule: Subtract proportionally from unit prices, never going negative/zero, negate other discounts unless they're larger
     */
    private function applyFixedAmountCouponDiscount(Coupon $coupon, Collection $cartProducts, array $applicableProducts, float $totalDiscountAmount, bool $hasOrders): array
    {
        $modifiedProducts = [];
        $applicableProductsTotal = 0;

        // First pass: calculate total value of applicable products and their existing discounts
        $applicableProductsData = [];
        foreach ($cartProducts as $cartItem) {
            $product = Product::with(['brand.vendor', 'categories'])->find($cartItem['product_id']);
            if (!$product) continue;

            $quantity = $cartItem['quantity'];
            $isApplicable = $this->isProductApplicableForCoupon($product, $applicableProducts);

            if (!$isApplicable) continue;

            // Get base price
            $basePrice = $product->price;
            $variation = $product->items->where('id', $cartItem['variation_id'])->first();
            if ($variation) {
                $basePrice = $variation->pivot->price;
            }

            $packageQuantity = $product->package_quantity ?? 1;
            $lineTotal = $basePrice * $quantity * $packageQuantity;
            $applicableProductsTotal += $lineTotal;

            // Get existing discount info
            $existingPriceInfo = $product->getFinalPriceForUser($hasOrders);
            $existingDiscountPercentage = $existingPriceInfo['discount'];
            $existingDiscountAmount = ($basePrice * $existingDiscountPercentage / 100) * $quantity * $packageQuantity;

            $applicableProductsData[] = [
                'product' => $product,
                'cart_item' => $cartItem,
                'base_price' => $basePrice,
                'quantity' => $quantity,
                'package_quantity' => $packageQuantity,
                'line_total' => $lineTotal,
                'existing_discount_percentage' => $existingDiscountPercentage,
                'existing_discount_amount' => $existingDiscountAmount,
            ];
        }

        if ($applicableProductsTotal == 0) {
            return [
                'success' => false,
                'message' => 'No applicable products found for fixed amount coupon',
                'total_coupon_discount' => 0,
                'modified_products' => []
            ];
        }

        // Second pass: distribute the fixed discount amount proportionally
        $remainingDiscountToDistribute = $totalDiscountAmount;
        $actualTotalDiscount = 0;

        foreach ($applicableProductsData as $productData) {
            $product = $productData['product'];
            $cartItem = $productData['cart_item'];
            $basePrice = $productData['base_price'];
            $quantity = $productData['quantity'];
            $packageQuantity = $productData['package_quantity'];
            $lineTotal = $productData['line_total'];
            $existingDiscountAmount = $productData['existing_discount_amount'];

            // Calculate proportional discount for this product line
            $proportionalDiscount = ($lineTotal / $applicableProductsTotal) * $totalDiscountAmount;

            // Calculate what the unit price reduction would be
            $totalUnits = $quantity * $packageQuantity;
            $unitPriceReduction = $proportionalDiscount / $totalUnits;

            // Ensure we don't go below a minimum price (e.g., 10% of original price)
            $minAllowedPrice = $basePrice * 0.1;
            $maxAllowedReduction = max(0, $basePrice - $minAllowedPrice);
            $actualUnitPriceReduction = min($unitPriceReduction, $maxAllowedReduction);

            // Calculate the actual discount we can apply
            $actualLineDiscount = $actualUnitPriceReduction * $totalUnits;
            $actualTotalDiscount += $actualLineDiscount;

            // Calculate equivalent percentage for comparison with existing discounts
            $equivalentDiscountPercentage = ($actualUnitPriceReduction / $basePrice) * 100;
            $existingDiscountPercentage = $productData['existing_discount_percentage'];

            // Determine which discount to use
            $shouldUseFixedDiscount = $equivalentDiscountPercentage > $existingDiscountPercentage;

            if ($shouldUseFixedDiscount) {
                // Use the fixed amount approach - modify unit price
                $newUnitPrice = $basePrice - $actualUnitPriceReduction;
                $appliedDiscountType = 'fixed_amount';
                $appliedDiscountPercentage = 0; // Don't use percentage field
                $finalDiscountAmount = $actualLineDiscount;
            } else {
                // Existing percentage discount is better - keep it
                $newUnitPrice = $basePrice - ($basePrice * $existingDiscountPercentage / 100);
                $appliedDiscountType = 'existing_percentage';
                $appliedDiscountPercentage = $existingDiscountPercentage;
                $finalDiscountAmount = $existingDiscountAmount;
                // Subtract this from our coupon contribution since we're not using it
                $actualTotalDiscount -= $actualLineDiscount;
                $actualTotalDiscount += $existingDiscountAmount;
            }

            $modifiedProducts[] = [
                'product_id' => $product->id,
                'variation_id' => $cartItem['variation_id'] ?? null,
                'quantity' => $quantity,
                'base_price' => $basePrice,
                'new_unit_price' => $newUnitPrice,
                'package_quantity' => $packageQuantity,
                'proportional_discount_amount' => $proportionalDiscount,
                'actual_discount_amount' => $actualLineDiscount,
                'equivalent_discount_percentage' => $equivalentDiscountPercentage,
                'existing_discount_percentage' => $existingDiscountPercentage,
                'applied_discount_type' => $appliedDiscountType,
                'applied_discount_percentage' => $appliedDiscountPercentage,
                'final_discount_amount' => $finalDiscountAmount,
                'unit_price_reduction' => $actualUnitPriceReduction,
            ];
        }

        // Handle non-applicable products (they keep their existing discounts)
        foreach ($cartProducts as $cartItem) {
            $product = Product::find($cartItem['product_id']);
            if (!$product) continue;

            $isApplicable = $this->isProductApplicableForCoupon($product, $applicableProducts);
            if ($isApplicable) continue; // Already handled above

            // Keep existing discount
            $basePrice = $product->price;
            $variation = $product->items->where('id', $cartItem['variation_id'])->first();
            if ($variation) {
                $basePrice = $variation->pivot->price;
            }

            $existingPriceInfo = $product->getFinalPriceForUser($hasOrders);

            $modifiedProducts[] = [
                'product_id' => $product->id,
                'variation_id' => $cartItem['variation_id'] ?? null,
                'quantity' => $cartItem['quantity'],
                'base_price' => $basePrice,
                'new_unit_price' => $basePrice,
                'package_quantity' => $product->package_quantity ?? 1,
                'applied_discount_type' => 'existing_percentage',
                'applied_discount_percentage' => $existingPriceInfo['discount'],
                'final_discount_amount' => $existingPriceInfo['totalDiscount'],
                'unit_price_reduction' => 0,
            ];
        }

        return [
            'success' => true,
            'type' => 'fixed_amount',
            'total_coupon_discount' => $actualTotalDiscount,
            'requested_discount' => $totalDiscountAmount,
            'modified_products' => $modifiedProducts,
            'message' => 'Fixed amount coupon applied successfully'
        ];
    }

    /**
     * Check if a product is applicable for the coupon based on the applicable products array
     */
    private function isProductApplicableForCoupon(Product $product, array $applicableProducts): bool
    {
        foreach ($applicableProducts as $applicableProduct) {
            if ($applicableProduct['product']->id === $product->id) {
                return true;
            }
        }
        return false;
    }

    /**
     * Calculate the final cart totals after applying coupon discounts
     */
    public function calculateFinalCartTotals(array $couponResult, Collection $cartProducts): array
    {
        if (!$couponResult['success']) {
            return [
                'subtotal' => 0,
                'total_discount' => 0,
                'coupon_discount' => 0,
                'final_total' => 0,
                'products' => []
            ];
        }

        $subtotal = 0;
        $totalDiscount = 0;
        $couponDiscount = $couponResult['total_coupon_discount'];
        $products = [];

        foreach ($couponResult['modified_products'] as $modifiedProduct) {
            $basePrice = $modifiedProduct['base_price'];
            $quantity = $modifiedProduct['quantity'];
            $packageQuantity = $modifiedProduct['package_quantity'];

            // Calculate line totals
            $lineSubtotal = $basePrice * $quantity * $packageQuantity;
            $lineDiscount = $modifiedProduct['final_discount_amount'];
            $lineTotal = $lineSubtotal - $lineDiscount;

            $subtotal += $lineSubtotal;
            $totalDiscount += $lineDiscount;

            $products[] = [
                'product_id' => $modifiedProduct['product_id'],
                'variation_id' => $modifiedProduct['variation_id'],
                'quantity' => $quantity,
                'package_quantity' => $packageQuantity,
                'unit_price' => $basePrice,
                'line_subtotal' => $lineSubtotal,
                'line_discount' => $lineDiscount,
                'line_total' => $lineTotal,
                'discount_type' => $modifiedProduct['applied_discount_type'] ?? 'percentage',
                'discount_percentage' => $modifiedProduct['applied_discount_percentage'] ?? 0,
            ];
        }

        $finalTotal = $subtotal - $totalDiscount;

        return [
            'subtotal' => $subtotal,
            'total_discount' => $totalDiscount,
            'coupon_discount' => $couponDiscount,
            'final_total' => $finalTotal,
            'products' => $products
        ];
    }
}
