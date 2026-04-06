<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
        try {
            $couponService = app(CouponService::class);

            // First validate and get basic coupon application data
            $couponResult = $couponService->applyCouponToCart($coupon, $user, $cartProducts, $hasOrders);

            Log::info('CouponDiscountService: applyCouponToCart result', [
                'coupon_id' => $coupon->id,
                'coupon_code' => $coupon->code,
                'coupon_type' => $coupon->type,
                'coupon_applies_to' => $coupon->applies_to,
                'coupon_value' => $coupon->value,
                'success' => $couponResult['success'],
                'discount_amount' => $couponResult['discount_amount'] ?? 0,
                'applicable_products_count' => count($couponResult['applicable_products'] ?? []),
                'total_cart_value' => $couponResult['total_cart_value'] ?? 0,
            ]);

            if (!$couponResult['success']) {
                return $couponResult;
            }

            $totalDiscountAmount = $couponResult['discount_amount'];
            $applicableProducts = $couponResult['applicable_products'];
            $totalCartValue = $couponResult['total_cart_value'];

            // Apply discount based on coupon type
            if ($coupon->type === Coupon::TYPE_PERCENTAGE) {
                $result = $this->applyPercentageCouponDiscount($coupon, $cartProducts, $applicableProducts, $hasOrders);
            } else {
                $result = $this->applyFixedAmountCouponDiscount($coupon, $cartProducts, $applicableProducts, $totalDiscountAmount, $hasOrders);
            }

            Log::info('CouponDiscountService: Final result', [
                'coupon_id' => $coupon->id,
                'coupon_type' => $coupon->type,
                'success' => $result['success'],
                'total_coupon_discount' => $result['total_coupon_discount'] ?? 0,
                'modified_products_count' => count($result['modified_products'] ?? []),
                'modified_products_summary' => collect($result['modified_products'] ?? [])->map(function($p) {
                    return [
                        'product_id' => $p['product_id'],
                        'discount_type' => $p['applied_discount_type'] ?? 'unknown',
                        'discount_pct' => $p['applied_discount_percentage'] ?? 0,
                        'unit_price_reduction' => $p['unit_price_reduction'] ?? 0,
                    ];
                })->toArray(),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('CouponDiscountService: Exception during coupon application', [
                'coupon_id' => $coupon->id ?? null,
                'coupon_code' => $coupon->code ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al aplicar el cupón: ' . $e->getMessage(),
                'total_coupon_discount' => 0,
                'modified_products' => [],
            ];
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
            $product = Product::with(['brand.vendor', 'categories', 'items'])->find($cartItem['product_id']);
            if (!$product) {
                continue;
            }

            $quantity = (int) ($cartItem['quantity'] ?? 1);
            $packageQuantity = (int) ($product->package_quantity ?? 1);
            $basePrice = $this->resolveBasePrice($product, $cartItem['variation_id'] ?? null);

            $existingPriceInfo = $product->getFinalPriceForUser($hasOrders);
            $existingDiscountPercentage = $this->clampPercentage((float) ($existingPriceInfo['discount'] ?? 0));
            $isApplicable = $this->isProductApplicableForCoupon($product, $applicableProducts);
            $couponPercentage = $isApplicable ? $this->clampPercentage((float) $coupon->value) : 0.0;

            $finalDiscountPercentage = max($existingDiscountPercentage, $couponPercentage);
            $totalUnits = max(1, $quantity * $packageQuantity);
            $lineSubtotal = $basePrice * $totalUnits;
            $newUnitPrice = max(0, $basePrice - ($basePrice * $finalDiscountPercentage / 100));
            $lineTotal = $newUnitPrice * $totalUnits;
            $lineDiscountAmount = max(0, $lineSubtotal - $lineTotal);

            // Coupon contribution is the incremental improvement over existing line pricing.
            $couponContributionPct = max(0, $finalDiscountPercentage - $existingDiscountPercentage);
            $couponContribution = ($basePrice * $couponContributionPct / 100) * $totalUnits;
            $totalCouponDiscount += $couponContribution;

            $modifiedProducts[] = [
                'product_id' => $product->id,
                'variation_id' => $cartItem['variation_id'] ?? null,
                'quantity' => $quantity,
                'base_price' => $basePrice,
                'new_unit_price' => $newUnitPrice,
                'package_quantity' => $packageQuantity,
                'applied_discount_type' => 'percentage',
                'applied_discount_percentage' => $finalDiscountPercentage,
                'discount_source' => $couponContribution > 0 ? 'coupon' : 'existing',
                'effective_discount_percentage' => $finalDiscountPercentage,
                'fixed_discount_per_unit' => 0.0,
                'line_subtotal' => $lineSubtotal,
                'line_total' => $lineTotal,
                'line_savings' => $lineDiscountAmount,
                'coupon_contribution' => $couponContribution,
                'coupon_discount_percentage' => $couponPercentage,
                'existing_discount_percentage' => $existingDiscountPercentage,
                'line_discount_amount' => $lineDiscountAmount,
                'final_discount_amount' => $lineDiscountAmount,
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

        Log::debug('applyFixedAmountCouponDiscount: Starting', [
            'coupon_id' => $coupon->id,
            'coupon_applies_to' => $coupon->applies_to,
            'total_discount_amount' => $totalDiscountAmount,
            'cart_products_count' => $cartProducts->count(),
            'applicable_products_count' => count($applicableProducts),
            'applicable_product_ids' => collect($applicableProducts)->pluck('product.id')->toArray(),
        ]);

        // First pass: calculate total value of applicable products and their existing discounts
        $applicableProductsData = [];
        foreach ($cartProducts as $cartItem) {
            $product = Product::with(['brand.vendor', 'categories', 'items'])->find($cartItem['product_id']);
            if (!$product) continue;

            $quantity = (int) ($cartItem['quantity'] ?? 1);
            $isApplicable = $this->isProductApplicableForCoupon($product, $applicableProducts);

            Log::debug('applyFixedAmountCouponDiscount: Checking product applicability', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'is_applicable' => $isApplicable,
                'quantity' => $quantity,
            ]);

            if (!$isApplicable) continue;

            $basePrice = $this->resolveBasePrice($product, $cartItem['variation_id'] ?? null);

            $packageQuantity = (int) ($product->package_quantity ?? 1);
            $lineTotal = $basePrice * $quantity * $packageQuantity;
            $applicableProductsTotal += $lineTotal;

            // Get existing discount info with null-safe handling
            $existingPriceInfo = $product->getFinalPriceForUser($hasOrders);
            $existingDiscountPercentage = (float) ($existingPriceInfo['discount'] ?? 0);
            $existingDiscountPercentage = max(0, min(100, $existingDiscountPercentage));
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
        $actualTotalDiscount = 0;

        foreach ($applicableProductsData as $productData) {
            $product = $productData['product'];
            $cartItem = $productData['cart_item'];
            $basePrice = $productData['base_price'];
            $quantity = (int) $productData['quantity'];
            $packageQuantity = (int) $productData['package_quantity'];
            $lineTotal = $productData['line_total'];
            $existingDiscountAmount = $productData['existing_discount_amount'];

            // Calculate proportional discount for this product line
            $proportionalDiscount = ($lineTotal / $applicableProductsTotal) * $totalDiscountAmount;

            // Calculate what the unit price reduction would be
            $totalUnits = max(1, $quantity * $packageQuantity);
            $unitPriceReduction = $proportionalDiscount / $totalUnits;

            // IMPORTANT: When calculate_package_price is true, the SOAP transmission divides
            // the stored price by packageQuantity to get per-individual-unit price.
            // The safeguard must use this actual SOAP unit price to prevent zero prices.
            $soapUnitPrice = $product->calculate_package_price 
                ? ($basePrice / $packageQuantity) 
                : $basePrice;
            
            // Ensure we don't go below a minimum price (10% of the actual SOAP unit price)
            $minAllowedPrice = $soapUnitPrice * 0.1;
            $maxAllowedReduction = max(0, $soapUnitPrice - $minAllowedPrice);
            $actualUnitPriceReduction = min($unitPriceReduction, $maxAllowedReduction);

            // Calculate the actual discount we can apply
            $actualLineDiscount = $actualUnitPriceReduction * $totalUnits;

            // Calculate equivalent percentage for comparison with existing discounts
            // Use soapUnitPrice for accurate comparison since that's the price sent to SOAP
            $equivalentDiscountPercentage = $soapUnitPrice > 0 
                ? ($actualUnitPriceReduction / $soapUnitPrice) * 100 
                : 0;
            $existingDiscountPercentage = $productData['existing_discount_percentage'];

            // Determine which discount to use (compare actual savings per unit)
            // For existing percentage: savings = soapUnitPrice * percentage / 100
            // For fixed amount: savings = actualUnitPriceReduction
            $existingSavingsPerUnit = $soapUnitPrice * $existingDiscountPercentage / 100;
            $shouldUseFixedDiscount = $actualUnitPriceReduction > $existingSavingsPerUnit;

            $lineSubtotal = $lineTotal;
            if ($shouldUseFixedDiscount) {
                // Use the fixed amount approach - modify unit price
                $newUnitPrice = max(0, $basePrice - $actualUnitPriceReduction);
                $appliedDiscountType = 'fixed_amount';
                $appliedDiscountPercentage = 0;
                $finalDiscountAmount = $actualLineDiscount;
                $couponContribution = $actualLineDiscount;
                $actualTotalDiscount += $couponContribution;
            } else {
                // Existing percentage discount is better - keep it.
                $newUnitPrice = $basePrice - ($basePrice * $existingDiscountPercentage / 100);
                $appliedDiscountType = 'percentage';
                $appliedDiscountPercentage = $existingDiscountPercentage;
                $finalDiscountAmount = $existingDiscountAmount;
                $couponContribution = 0;
            }

            $lineTotalAfterDiscount = max(0, $newUnitPrice * $totalUnits);
            $lineSavings = max(0, $lineSubtotal - $lineTotalAfterDiscount);

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
                'fixed_discount_per_unit' => $appliedDiscountType === 'fixed_amount' ? $actualUnitPriceReduction : 0.0,
                'line_subtotal' => $lineSubtotal,
                'line_total' => $lineTotalAfterDiscount,
                'line_savings' => $lineSavings,
                'coupon_contribution' => $couponContribution,
                'discount_source' => $couponContribution > 0 ? 'coupon' : 'existing',
                'effective_discount_percentage' => $appliedDiscountPercentage,
            ];
        }

        // Handle non-applicable products (they keep their existing discounts)
        foreach ($cartProducts as $cartItem) {
            $product = Product::find($cartItem['product_id']);
            if (!$product) continue;

            $isApplicable = $this->isProductApplicableForCoupon($product, $applicableProducts);
            if ($isApplicable) continue; // Already handled above

            $modifiedProducts[] = $this->buildExistingLineResult($product, $cartItem, $hasOrders);
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
     * Apply MULTIPLE coupons to cart products, picking the best discount per product.
     *
     * For each product we evaluate every coupon independently, then keep the ONE
     * result that yields the largest monetary saving on that line.  Discounts are
     * never added together — each item gets exactly one discount source.
     *
     * Percentage coupons populate the discount-percentage field; fixed-amount
     * coupons reduce the unit price (percentage field stays 0).
     */
    public function applyMultipleCouponsToProducts(array $coupons, User $user, Collection $cartProducts, bool $hasOrders = false): array
    {
        if (empty($coupons)) {
            return [
                'success' => false,
                'message' => 'No coupons provided',
                'total_coupon_discount' => 0,
                'modified_products' => [],
                'winning_coupons' => [],
            ];
        }

        // Run each coupon individually through the full discount calculation
        $allCouponResults = [];
        foreach ($coupons as $coupon) {
            try {
                $result = $this->applyCouponDiscountToProducts($coupon, $user, $cartProducts, $hasOrders);
                if ($result['success']) {
                    $allCouponResults[] = [
                        'coupon' => $coupon,
                        'result' => $result,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Multi-coupon: failed to apply coupon', [
                    'coupon_id' => $coupon->id,
                    'coupon_code' => $coupon->code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($allCouponResults)) {
            return [
                'success' => false,
                'message' => 'No coupons could be applied',
                'total_coupon_discount' => 0,
                'modified_products' => [],
                'winning_coupons' => [],
            ];
        }

        // For each product, pick the coupon that saves the customer the most money.
        // We compare the actual monetary saving per line (base_price * qty * pkg - final price)
        // so percentage and fixed coupons are compared on equal footing.
        $mergedProducts = [];

        foreach ($allCouponResults as $couponData) {
            $coupon = $couponData['coupon'];
            $result = $couponData['result'];

            foreach ($result['modified_products'] as $modProduct) {
                $key = $modProduct['product_id'] . '_' . ($modProduct['variation_id'] ?? 'null');

                $thisSaving = (float) ($modProduct['line_savings'] ?? 0);
                if ($thisSaving <= 0) {
                    $basePrice = (float) ($modProduct['base_price'] ?? 0);
                    $quantity  = (int) ($modProduct['quantity'] ?? 1);
                    $pkgQty    = (int) ($modProduct['package_quantity'] ?? 1);
                    $lineGross = $basePrice * $quantity * $pkgQty;
                    $newUnitPrice = (float) ($modProduct['new_unit_price'] ?? $basePrice);
                    $lineNet = $newUnitPrice * $quantity * $pkgQty;
                    $thisSaving = max(0, $lineGross - $lineNet);
                }

                $currentBestSaving = 0;
                if (isset($mergedProducts[$key])) {
                    $cb = $mergedProducts[$key];
                    $currentBestSaving = (float) ($cb['line_savings'] ?? 0);
                    if ($currentBestSaving <= 0) {
                        $cbGross  = (float)$cb['base_price'] * (int)$cb['quantity'] * (int)$cb['package_quantity'];
                        $cbNet    = (float)$cb['new_unit_price'] * (int)$cb['quantity'] * (int)$cb['package_quantity'];
                        $currentBestSaving = max(0, $cbGross - $cbNet);
                    }
                }

                if (!isset($mergedProducts[$key]) || $thisSaving > $currentBestSaving) {
                    $mergedProducts[$key] = $modProduct;
                    $mergedProducts[$key]['winning_coupon_id']   = $coupon->id;
                    $mergedProducts[$key]['winning_coupon_code'] = $coupon->code;
                }
            }
        }

        // Calculate total coupon discount (only the portion the coupon itself contributed)
        $totalCouponDiscount = 0;
        $winningCoupons = [];

        foreach ($mergedProducts as $product) {
            $totalCouponDiscount += (float) ($product['coupon_contribution'] ?? 0);

            if (isset($product['winning_coupon_id'])) {
                $winningCoupons[$product['winning_coupon_id']] = $product['winning_coupon_code'];
            }
        }

        Log::info('Multi-coupon merge result', [
            'coupons_evaluated' => count($allCouponResults),
            'winning_coupons' => $winningCoupons,
            'total_coupon_discount' => $totalCouponDiscount,
            'products_count' => count($mergedProducts),
        ]);

        return [
            'success' => true,
            'total_coupon_discount' => $totalCouponDiscount,
            'modified_products' => array_values($mergedProducts),
            'winning_coupons' => $winningCoupons,
        ];
    }

    private function resolveBasePrice(Product $product, $variationId = null): float
    {
        $basePrice = (float) $product->price;
        if ($variationId) {
            $variation = $product->items->where('id', $variationId)->first();
            if ($variation && $variation->pivot) {
                $basePrice = (float) $variation->pivot->price;
            }
        }
        return $basePrice;
    }

    private function clampPercentage(float $value): float
    {
        return max(0, min(100, $value));
    }

    private function buildExistingLineResult(Product $product, array $cartItem, bool $hasOrders): array
    {
        $quantity = (int) ($cartItem['quantity'] ?? 1);
        $packageQuantity = (int) ($product->package_quantity ?? 1);
        $basePrice = $this->resolveBasePrice($product, $cartItem['variation_id'] ?? null);

        $existingPriceInfo = $product->getFinalPriceForUser($hasOrders);
        $existingDiscountPercentage = $this->clampPercentage((float) ($existingPriceInfo['discount'] ?? 0));
        $totalUnits = max(1, $quantity * $packageQuantity);
        $newUnitPrice = max(0, $basePrice - ($basePrice * $existingDiscountPercentage / 100));
        $lineSubtotal = $basePrice * $totalUnits;
        $lineTotal = $newUnitPrice * $totalUnits;
        $lineSavings = max(0, $lineSubtotal - $lineTotal);

        return [
            'product_id' => $product->id,
            'variation_id' => $cartItem['variation_id'] ?? null,
            'quantity' => $quantity,
            'base_price' => $basePrice,
            'new_unit_price' => $newUnitPrice,
            'package_quantity' => $packageQuantity,
            'applied_discount_type' => 'percentage',
            'applied_discount_percentage' => $existingDiscountPercentage,
            'discount_source' => 'existing',
            'effective_discount_percentage' => $existingDiscountPercentage,
            'fixed_discount_per_unit' => 0.0,
            'line_subtotal' => $lineSubtotal,
            'line_total' => $lineTotal,
            'line_savings' => $lineSavings,
            'coupon_contribution' => 0.0,
            'existing_discount_percentage' => $existingDiscountPercentage,
            'line_discount_amount' => $lineSavings,
            'final_discount_amount' => $lineSavings,
            'unit_price_reduction' => 0.0,
        ];
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
