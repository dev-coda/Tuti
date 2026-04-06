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

        // Check zone/route restrictions
        if (!$this->isUserZoneAllowed($coupon, $user)) {
            return [
                'valid' => false,
                'message' => 'Este cupón no está disponible para tu zona/ruta.',
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
                if (empty($coupon->applies_to_ids)) {
                    return false;
                }
                $normalizedIds = array_map('intval', $coupon->applies_to_ids);
                return in_array((int) $user->id, $normalizedIds, true);

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
     * Check if user's zone/route is allowed for this coupon
     */
    private function isUserZoneAllowed(Coupon $coupon, User $user): bool
    {
        // If no restrictions are set, allow all zones/routes
        if (empty($coupon->allowed_zone_ids) && empty($coupon->allowed_zones) && empty($coupon->allowed_routes)) {
            return true;
        }

        // Check zone IDs (from zones table)
        if (!empty($coupon->allowed_zone_ids)) {
            $userZoneIds = $user->zones()->pluck('id')->toArray();
            if (!empty(array_intersect($userZoneIds, $coupon->allowed_zone_ids))) {
                return true;
            }
        }

        // Check zone numbers (from users.zone field)
        if (!empty($coupon->allowed_zones) && $user->zone) {
            if (in_array($user->zone, $coupon->allowed_zones)) {
                return true;
            }
        }

        // Check routes (from zones.route field)
        if (!empty($coupon->allowed_routes)) {
            $userRoutes = $user->zones()->whereNotNull('route')->pluck('route')->toArray();
            if (!empty(array_intersect($userRoutes, $coupon->allowed_routes))) {
                return true;
            }
        }

        // If restrictions exist but user doesn't match any, deny access
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

    /**
     * Calculate discounts for multiple coupons, applying best discount per product
     * Returns array with product-level discounts and total discount
     */
    public function calculateMultipleCouponDiscounts(array $couponCodes, User $user, Collection $cartProducts): array
    {
        $codes = collect($couponCodes)
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->values();

        if ($codes->isEmpty()) {
            return [
                'success' => false,
                'product_discounts' => [],
                'total_discount' => 0,
                'applied_coupons' => [],
            ];
        }

        $coupons = Coupon::whereIn('code', $codes->all())->get();
        if ($coupons->isEmpty()) {
            return [
                'success' => false,
                'product_discounts' => [],
                'total_discount' => 0,
                'applied_coupons' => [],
            ];
        }

        $couponResult = app(CouponDiscountService::class)->applyMultipleCouponsToProducts(
            $coupons->all(),
            $user,
            $cartProducts
        );

        if (!$couponResult['success']) {
            return [
                'success' => false,
                'product_discounts' => [],
                'total_discount' => 0,
                'applied_coupons' => [],
            ];
        }

        $productDiscounts = [];
        foreach ($couponResult['modified_products'] as $line) {
            $lineKey = $line['product_id'] . '_' . ($line['variation_id'] ?? 'null');
            $productDiscounts[$lineKey] = [
                'coupon_id' => $line['winning_coupon_id'] ?? null,
                'coupon_code' => $line['winning_coupon_code'] ?? null,
                'discount' => (float) ($line['coupon_contribution'] ?? 0),
                'type' => $line['applied_discount_type'] ?? 'percentage',
                'value' => (float) ($line['applied_discount_percentage'] ?? 0),
                'line_savings' => (float) ($line['line_savings'] ?? 0),
                'line_total' => (float) ($line['line_total'] ?? 0),
            ];
        }

        $appliedCoupons = collect($couponResult['winning_coupons'] ?? [])
            ->map(fn ($code, $id) => ['coupon_id' => (int) $id, 'coupon_code' => $code])
            ->values()
            ->all();

        return [
            'success' => true,
            'product_discounts' => $productDiscounts,
            'total_discount' => (float) ($couponResult['total_coupon_discount'] ?? 0),
            'applied_coupons' => $appliedCoupons,
        ];
    }
}
