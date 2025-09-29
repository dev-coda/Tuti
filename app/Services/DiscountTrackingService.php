<?php

namespace App\Services;

use App\Models\DiscountApplication;
use App\Models\CouponUsageAnalytic;
use App\Models\BonificationAnalytic;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Collection;

class DiscountTrackingService
{
    /**
     * Track all discount applications for an order
     */
    public function trackOrderDiscounts(Order $order, User $user, Collection $cartProducts, array $appliedDiscounts = [])
    {
        $trackedApplications = [];

        foreach ($appliedDiscounts as $discount) {
            $application = $this->createDiscountApplication($order, $user, $discount);
            if ($application) {
                $trackedApplications[] = $application;
            }
        }

        // Track coupon usage if applicable
        $this->trackCouponUsage($order, $user, $cartProducts);

        // Track bonifications if applicable
        $this->trackBonifications($order, $user, $cartProducts);

        return $trackedApplications;
    }

    /**
     * Create a discount application record
     */
    private function createDiscountApplication(Order $order, User $user, array $discount): ?DiscountApplication
    {
        return DiscountApplication::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'discount_type' => $discount['type'],
            'discount_id' => $discount['id'] ?? null,
            'discount_name' => $discount['name'],
            'discount_value_type' => $discount['value_type'] ?? 'percentage',
            'discount_value' => $discount['value'],
            'discount_amount' => $discount['amount'],
            'original_amount' => $discount['original_amount'] ?? 0,
            'final_amount' => $discount['final_amount'] ?? 0,
            'applied_to' => $discount['applied_to'] ?? null,
            'notes' => $discount['notes'] ?? null,
        ]);
    }

    /**
     * Track coupon usage analytics
     */
    private function trackCouponUsage(Order $order, User $user, Collection $cartProducts)
    {
        $appliedCoupon = session()->get('applied_coupon');
        if (!$appliedCoupon) {
            return;
        }

        $coupon = \App\Models\Coupon::find($appliedCoupon['coupon_id']);
        if (!$coupon) {
            return;
        }

        $orderTotal = $order->total ?? 0;
        $orderSubtotal = $order->subtotal ?? 0;
        $itemsCount = $cartProducts->sum('quantity');

        CouponUsageAnalytic::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => $user->id,
            'discount_amount' => $appliedCoupon['discount_amount'] ?? 0,
            'order_total' => $orderTotal,
            'order_subtotal' => $orderSubtotal,
            'items_count' => $itemsCount,
            'applied_to_products' => $this->getAppliedProductIds($cartProducts),
            'user_email' => $user->email,
            'user_name' => $user->name,
        ]);
    }

    /**
     * Track bonification analytics
     */
    private function trackBonifications(Order $order, User $user, Collection $cartProducts)
    {
        foreach ($cartProducts as $cartItem) {
            $product = Product::with('bonifications')->find($cartItem['product_id']);
            if (!$product || !$product->bonifications->count()) {
                continue;
            }

            foreach ($product->bonifications as $bonification) {
                // Check if bonification was triggered
                $triggerQuantity = $cartItem['quantity'];
                $bonusQuantity = $this->calculateBonusQuantity($bonification, $triggerQuantity);

                if ($bonusQuantity > 0) {
                    BonificationAnalytic::create([
                        'bonification_id' => $bonification->id,
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'product_id' => $product->id,
                        'bonus_product_id' => $bonification->bonus_product_id ?? $product->id,
                        'bonus_quantity' => $bonusQuantity,
                        'bonus_value' => $this->calculateBonusValue($bonification, $bonusQuantity),
                        'order_total' => $order->total ?? 0,
                        'trigger_quantity' => $triggerQuantity,
                        'user_email' => $user->email,
                        'user_name' => $user->name,
                    ]);
                }
            }
        }
    }

    /**
     * Get product IDs that discounts were applied to
     */
    private function getAppliedProductIds(Collection $cartProducts): array
    {
        return $cartProducts->pluck('product_id')->toArray();
    }

    /**
     * Calculate bonus quantity for bonifications
     */
    private function calculateBonusQuantity($bonification, int $triggerQuantity): int
    {
        // This would need to be implemented based on your bonification logic
        // For now, return 0 as a placeholder
        return 0;
    }

    /**
     * Calculate bonus value for bonifications
     */
    private function calculateBonusValue($bonification, int $bonusQuantity): float
    {
        // This would need to be implemented based on your bonification logic
        // For now, return 0 as a placeholder
        return 0.0;
    }

    /**
     * Get discount analytics for a specific period
     */
    public function getDiscountAnalytics($startDate = null, $endDate = null)
    {
        return [
            'total_applications' => DiscountApplication::getAnalytics($startDate, $endDate),
            'by_type' => DiscountApplication::getTotalDiscountsByType($startDate, $endDate),
            'top_performing' => DiscountApplication::getTopPerformingDiscounts(10, $startDate, $endDate),
        ];
    }

    /**
     * Get coupon analytics for a specific period
     */
    public function getCouponAnalytics($startDate = null, $endDate = null)
    {
        return [
            'performance' => CouponUsageAnalytic::getCouponPerformance($startDate, $endDate),
            'trends' => CouponUsageAnalytic::getUsageTrends($startDate, $endDate, 'day'),
        ];
    }

    /**
     * Get bonification analytics for a specific period
     */
    public function getBonificationAnalytics($startDate = null, $endDate = null)
    {
        return [
            'performance' => BonificationAnalytic::getBonificationPerformance($startDate, $endDate),
            'popular_products' => BonificationAnalytic::getPopularBonusProducts(10, $startDate, $endDate),
        ];
    }
}

