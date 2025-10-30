<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Product;
use App\Models\User;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CouponService
{
    /**
     * Validate a coupon code for a specific user and cart
     */
    public function validateCoupon(string $code, User $user, Collection $cartProducts = null, float $cartTotal = 0): array
    {
        $coupon = Coupon::byCode($code)->first();

        if (!$coupon) {
            return [
                'valid' => false,
                'message' => 'Cupón no encontrado.',
                'coupon' => null
            ];
        }

        // Check if coupon is valid (active and within date range)
        if (!$coupon->isValid()) {
            return [
                'valid' => false,
                'message' => 'Cupón expirado o inactivo.',
                'coupon' => $coupon
            ];
        }

        // Check total usage limit
        if ($coupon->hasExceededTotalLimit()) {
            return [
                'valid' => false,
                'message' => 'Cupón agotado.',
                'coupon' => $coupon
            ];
        }

        // Check user usage limit
        if ($coupon->hasUserExceededLimit($user->id)) {
            return [
                'valid' => false,
                'message' => 'Has excedido el límite de uso para este cupón.',
                'coupon' => $coupon
            ];
        }

        // Check minimum cart amount
        if ($coupon->minimum_amount && $cartTotal < $coupon->minimum_amount) {
            return [
                'valid' => false,
                'message' => sprintf('Monto mínimo requerido: $%.2f', $coupon->minimum_amount),
                'coupon' => $coupon
            ];
        }

        // Check if coupon applies to user's role/type (for customer/customer_type coupons)
        if (in_array($coupon->applies_to, [Coupon::APPLIES_TO_CUSTOMER, Coupon::APPLIES_TO_CUSTOMER_TYPE])) {
            if (!$this->couponAppliesForUser($coupon, $user)) {
                return [
                    'valid' => false,
                    'message' => 'Este cupón no está disponible para tu tipo de cuenta.',
                    'coupon' => $coupon
                ];
            }
        }

        // Check if user is excluded
        if ($this->isUserExcluded($coupon, $user)) {
            return [
                'valid' => false,
                'message' => 'No puedes usar este cupón.',
                'coupon' => $coupon
            ];
        }

        // If cart products are provided, check if coupon applies to at least one product
        if ($cartProducts && $cartProducts->isNotEmpty()) {
            $hasApplicableProducts = false;

            foreach ($cartProducts as $cartItem) {
                $product = Product::with(['brand.vendor', 'categories'])->find($cartItem['product_id']);
                if ($product && $coupon->appliesToProduct($product, $user)) {
                    $hasApplicableProducts = true;
                    break;
                }
            }

            if (!$hasApplicableProducts && $coupon->applies_to !== Coupon::APPLIES_TO_CART) {
                return [
                    'valid' => false,
                    'message' => 'Este cupón no se aplica a los productos en tu carrito.',
                    'coupon' => $coupon
                ];
            }
        }

        return [
            'valid' => true,
            'message' => 'Cupón válido',
            'coupon' => $coupon
        ];
    }

    /**
     * Apply coupon to cart and calculate discount
     */
    public function applyCouponToCart(Coupon $coupon, User $user, Collection $cartProducts, bool $hasOrders = false): array
    {
        $applicableTotal = 0;
        $applicableProducts = [];
        $totalCartValue = 0;

        // Calculate the applicable total based on coupon rules
        foreach ($cartProducts as $cartItem) {
            $product = Product::with(['brand.vendor', 'categories'])->find($cartItem['product_id']);
            if (!$product) continue;

            $quantity = $cartItem['quantity'];

            // Calculate product price without existing promotions (as per requirement: coupons can't be combined)
            $basePrice = $product->price;
            $variation = $product->items->where('id', $cartItem['variation_id'])->first();
            if ($variation) {
                $basePrice = $variation->pivot->price;
            }

            // Apply tax to get pre-VAT amount (as specified in requirements)
            $preTaxPrice = $basePrice;
            $totalProductValue = $preTaxPrice * $quantity * ($product->package_quantity ?? 1);
            $totalCartValue += $totalProductValue;

            // Check if coupon applies to this product
            if ($coupon->applies_to === Coupon::APPLIES_TO_CART || $coupon->appliesToProduct($product, $user)) {
                $applicableTotal += $totalProductValue;
                $applicableProducts[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'total_value' => $totalProductValue
                ];
            }
        }

        // Calculate discount
        $discountAmount = 0;

        if ($coupon->applies_to === Coupon::APPLIES_TO_CART) {
            // Apply to entire cart
            $discountAmount = $coupon->calculateDiscount($totalCartValue);
        } else {
            // Apply only to applicable products
            $discountAmount = $coupon->calculateDiscount($applicableTotal);
        }

        // For fixed amount coupons, ensure full amount is used in a single purchase
        if ($coupon->type === Coupon::TYPE_FIXED_AMOUNT) {
            $maxApplicable = $coupon->applies_to === Coupon::APPLIES_TO_CART ? $totalCartValue : $applicableTotal;
            if ($discountAmount > $maxApplicable) {
                return [
                    'success' => false,
                    'message' => 'El monto del cupón excede el valor aplicable del carrito.',
                    'discount_amount' => 0,
                    'applicable_total' => $applicableTotal,
                    'total_cart_value' => $totalCartValue
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Cupón aplicado exitosamente',
            'discount_amount' => $discountAmount,
            'applicable_total' => $applicableTotal,
            'total_cart_value' => $totalCartValue,
            'applicable_products' => $applicableProducts
        ];
    }

    /**
     * Record coupon usage when order is created
     */
    public function recordCouponUsage(Coupon $coupon, User $user, Order $order, float $discountAmount): CouponUsage
    {
        // Validate inputs
        if (!$coupon || !$coupon->id) {
            throw new \Exception('Invalid coupon provided for usage recording');
        }

        if (!$user || !$user->id) {
            throw new \Exception('Invalid user provided for coupon usage recording');
        }

        if (!$order || !$order->id) {
            throw new \Exception('Invalid order provided for coupon usage recording');
        }

        // Increment coupon usage
        $coupon->incrementUsage();

        // Create usage record
        return CouponUsage::create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'vendor_id' => null, // Could be populated based on business logic
            'discount_amount' => $discountAmount,
        ]);
    }

    /**
     * Check if a coupon applies for a specific user (customer/customer_type rules)
     */
    private function couponAppliesForUser(Coupon $coupon, User $user): bool
    {
        switch ($coupon->applies_to) {
            case Coupon::APPLIES_TO_CUSTOMER:
                return $coupon->applies_to_ids && in_array($user->id, $coupon->applies_to_ids);

            case Coupon::APPLIES_TO_CUSTOMER_TYPE:
                if (!$coupon->applies_to_ids) return false;
                $userRoles = $user->roles->pluck('name')->toArray();
                return !empty(array_intersect($userRoles, $coupon->applies_to_ids));

            default:
                return true; // For other types, user validation is not needed here
        }
    }

    /**
     * Check if a user is excluded from using a coupon
     */
    private function isUserExcluded(Coupon $coupon, User $user): bool
    {
        // Check if user is specifically excluded
        if ($coupon->except_customer_ids && in_array($user->id, $coupon->except_customer_ids)) {
            return true;
        }

        // Check if user's roles are excluded
        if ($coupon->except_customer_types) {
            $userRoles = $user->roles->pluck('name')->toArray();
            if (!empty(array_intersect($userRoles, $coupon->except_customer_types))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get available coupons for a user
     */
    public function getAvailableCouponsForUser(User $user): Collection
    {
        return Coupon::valid()
            ->where(function ($query) use ($user) {
                $query->where('applies_to', Coupon::APPLIES_TO_CART)
                    ->orWhere(function ($subQuery) use ($user) {
                        $subQuery->where('applies_to', Coupon::APPLIES_TO_CUSTOMER)
                            ->whereJsonContains('applies_to_ids', $user->id);
                    })
                    ->orWhere(function ($subQuery) use ($user) {
                        $subQuery->where('applies_to', Coupon::APPLIES_TO_CUSTOMER_TYPE);
                        $userRoles = $user->roles->pluck('name')->toArray();
                        foreach ($userRoles as $role) {
                            $subQuery->orWhereJsonContains('applies_to_ids', $role);
                        }
                    });
            })
            ->whereNotIn('id', function ($query) use ($user) {
                $query->select('coupon_id')
                    ->from('coupon_usages')
                    ->where('user_id', $user->id)
                    ->groupBy('coupon_id')
                    ->havingRaw('COUNT(*) >= coupons.usage_limit_per_customer');
            })
            ->get();
    }
}
