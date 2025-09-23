<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Vendor;
use App\Models\Coupon;
use App\Models\Promocion;
use App\Models\VolumeDiscount;
use App\Models\Bonification;
use App\Models\User;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class DiscountService
{
    /**
     * Apply all applicable discounts to a cart and return the best one
     * Following the rule that discounts are NOT cumulative - only the best one applies
     */
    public function applyBestDiscount(Collection $cartProducts, User $user, bool $hasOrders = false): array
    {
        $cartTotal = $this->calculateCartTotal($cartProducts, $hasOrders);
        $cartUnits = $this->calculateCartUnits($cartProducts);

        $applicableDiscounts = [];

        // 1. Check direct discounts (brand/vendor)
        $directDiscount = $this->calculateDirectDiscount($cartProducts, $hasOrders);
        if ($directDiscount['amount'] > 0) {
            $applicableDiscounts[] = $directDiscount;
        }

        // 2. Check volume discounts
        $volumeDiscount = $this->calculateVolumeDiscount($cartProducts, $hasOrders);
        if ($volumeDiscount['amount'] > 0) {
            $applicableDiscounts[] = $volumeDiscount;
        }

        // 3. Check coupons
        $couponDiscount = $this->calculateCouponDiscount($cartProducts, $user, $hasOrders);
        if ($couponDiscount['amount'] > 0) {
            $applicableDiscounts[] = $couponDiscount;
        }

        // 4. Check promociones
        $promocionDiscount = $this->calculatePromocionDiscount($cartProducts, $user, $hasOrders);
        if ($promocionDiscount['amount'] > 0) {
            $applicableDiscounts[] = $promocionDiscount;
        }

        // Return the best discount (highest amount)
        if (empty($applicableDiscounts)) {
            return [
                'type' => 'none',
                'amount' => 0,
                'description' => 'Sin descuento aplicable',
                'details' => []
            ];
        }

        $bestDiscount = collect($applicableDiscounts)->sortByDesc('amount')->first();

        return $bestDiscount;
    }

    /**
     * Calculate cart total without any discounts
     */
    private function calculateCartTotal(Collection $cartProducts, bool $hasOrders = false): float
    {
        $total = 0;

        foreach ($cartProducts as $cartItem) {
            $product = Product::with('brand.vendor')->find($cartItem['product_id']);
            if (!$product) continue;

            $quantity = $cartItem['quantity'];
            $unitPrice = $this->getProductBasePrice($product, $cartItem['variation_id'] ?? null);

            $total += $unitPrice * $quantity * ($product->package_quantity ?? 1);
        }

        return $total;
    }

    /**
     * Calculate total units in cart
     */
    private function calculateCartUnits(Collection $cartProducts): int
    {
        return $cartProducts->sum('quantity');
    }

    /**
     * Calculate direct discounts (brand/vendor)
     */
    private function calculateDirectDiscount(Collection $cartProducts, bool $hasOrders = false): array
    {
        $totalDiscount = 0;
        $discountDetails = [];

        foreach ($cartProducts as $cartItem) {
            $product = Product::with('brand.vendor')->find($cartItem['product_id']);
            if (!$product) continue;

            $quantity = $cartItem['quantity'];
            $unitPrice = $this->getProductBasePrice($product, $cartItem['variation_id'] ?? null);
            $lineTotal = $unitPrice * $quantity * ($product->package_quantity ?? 1);

            // Check brand discount
            if ($product->brand && $product->brand->discount > 0) {
                if (!$hasOrders || !$product->brand->first_purchase_only) {
                    $brandDiscount = $this->calculateDiscountAmount(
                        $lineTotal,
                        $product->brand->discount,
                        $product->brand->discount_type ?? 'percentage'
                    );
                    $totalDiscount += $brandDiscount;
                    $discountDetails[] = [
                        'type' => 'brand',
                        'name' => $product->brand->name,
                        'amount' => $brandDiscount,
                        'product_id' => $product->id
                    ];
                }
            }

            // Check vendor discount
            if ($product->brand && $product->brand->vendor && $product->brand->vendor->discount > 0) {
                if (!$hasOrders || !$product->brand->vendor->first_purchase_only) {
                    $vendorDiscount = $this->calculateDiscountAmount(
                        $lineTotal,
                        $product->brand->vendor->discount,
                        $product->brand->vendor->discount_type ?? 'percentage'
                    );
                    $totalDiscount += $vendorDiscount;
                    $discountDetails[] = [
                        'type' => 'vendor',
                        'name' => $product->brand->vendor->name,
                        'amount' => $vendorDiscount,
                        'product_id' => $product->id
                    ];
                }
            }
        }

        return [
            'type' => 'direct',
            'amount' => $totalDiscount,
            'description' => 'Descuento directo (marca/proveedor)',
            'details' => $discountDetails
        ];
    }

    /**
     * Calculate volume discounts
     */
    private function calculateVolumeDiscount(Collection $cartProducts, bool $hasOrders = false): array
    {
        $totalDiscount = 0;
        $discountDetails = [];

        $volumeDiscounts = VolumeDiscount::where('active', true)
            ->where('valid_from', '<=', Carbon::now())
            ->where('valid_to', '>=', Carbon::now())
            ->get();

        foreach ($volumeDiscounts as $volumeDiscount) {
            foreach ($cartProducts as $cartItem) {
                $product = Product::find($cartItem['product_id']);
                if (!$product) continue;

                $quantity = $cartItem['quantity'];
                $unitPrice = $this->getProductBasePrice($product, $cartItem['variation_id'] ?? null);

                // Check if this volume discount applies to this product
                if ($this->volumeDiscountAppliesToProduct($volumeDiscount, $product)) {
                    $discount = $volumeDiscount->calculateDiscount($quantity, $unitPrice);
                    if ($discount > 0) {
                        $totalDiscount += $discount;
                        $discountDetails[] = [
                            'type' => 'volume',
                            'name' => $volumeDiscount->name,
                            'amount' => $discount,
                            'product_id' => $product->id
                        ];
                    }
                }
            }
        }

        return [
            'type' => 'volume',
            'amount' => $totalDiscount,
            'description' => 'Descuento por volumen',
            'details' => $discountDetails
        ];
    }

    /**
     * Calculate coupon discounts
     */
    private function calculateCouponDiscount(Collection $cartProducts, User $user, bool $hasOrders = false): array
    {
        $appliedCoupon = session()->get('applied_coupon');
        if (!$appliedCoupon) {
            return ['type' => 'coupon', 'amount' => 0, 'description' => 'Sin cupón aplicado', 'details' => []];
        }

        $coupon = Coupon::find($appliedCoupon['coupon_id']);
        if (!$coupon || !$coupon->isValid()) {
            return ['type' => 'coupon', 'amount' => 0, 'description' => 'Cupón inválido', 'details' => []];
        }

        $cartTotal = $this->calculateCartTotal($cartProducts, $hasOrders);
        $discountAmount = $coupon->calculateDiscount($cartTotal);

        return [
            'type' => 'coupon',
            'amount' => $discountAmount,
            'description' => "Cupón: {$coupon->code}",
            'details' => [
                [
                    'type' => 'coupon',
                    'name' => $coupon->name,
                    'amount' => $discountAmount,
                    'coupon_id' => $coupon->id
                ]
            ]
        ];
    }

    /**
     * Calculate promocion discounts
     */
    private function calculatePromocionDiscount(Collection $cartProducts, User $user, bool $hasOrders = false): array
    {
        $totalDiscount = 0;
        $discountDetails = [];

        $promociones = Promocion::where('active', true)
            ->where('valid_from', '<=', Carbon::now())
            ->where('valid_to', '>=', Carbon::now())
            ->get();

        foreach ($promociones as $promocion) {
            if ($promocion->hasReachedUsageLimit()) {
                continue;
            }

            $cartTotal = $this->calculateCartTotal($cartProducts, $hasOrders);
            $cartUnits = $this->calculateCartUnits($cartProducts);

            $discountAmount = $promocion->calculateDiscount($cartTotal, $cartUnits);
            if ($discountAmount > 0) {
                $totalDiscount += $discountAmount;
                $discountDetails[] = [
                    'type' => 'promocion',
                    'name' => $promocion->name,
                    'amount' => $discountAmount,
                    'promocion_id' => $promocion->id
                ];
            }
        }

        return [
            'type' => 'promocion',
            'amount' => $totalDiscount,
            'description' => 'Promociones aplicables',
            'details' => $discountDetails
        ];
    }

    /**
     * Check if a volume discount applies to a specific product
     */
    private function volumeDiscountAppliesToProduct(VolumeDiscount $volumeDiscount, Product $product): bool
    {
        switch ($volumeDiscount->applies_to) {
            case 'products':
                return $volumeDiscount->products->contains($product);
            case 'categories':
                return $product->categories->whereIn('id', $volumeDiscount->applies_to_ids ?? [])->isNotEmpty();
            case 'brands':
                return in_array($product->brand_id, $volumeDiscount->applies_to_ids ?? []);
            case 'vendors':
                return $product->brand && in_array($product->brand->vendor_id, $volumeDiscount->applies_to_ids ?? []);
            case 'cart':
                return true;
            default:
                return false;
        }
    }

    /**
     * Calculate discount amount based on type
     */
    private function calculateDiscountAmount(float $amount, float $discountValue, string $discountType): float
    {
        if ($discountType === 'percentage') {
            return $amount * ($discountValue / 100);
        } else {
            return min($discountValue, $amount);
        }
    }

    /**
     * Get product base price (without any discounts)
     */
    private function getProductBasePrice(Product $product, ?int $variationId = null): float
    {
        if ($variationId) {
            $variation = $product->items->where('id', $variationId)->first();
            if ($variation) {
                return $variation->pivot->price;
            }
        }

        return $product->price;
    }

    /**
     * Distribute fixed amount discount proportionally across products
     */
    public function distributeFixedDiscount(float $totalDiscount, Collection $cartProducts, bool $hasOrders = false): array
    {
        $cartTotal = $this->calculateCartTotal($cartProducts, $hasOrders);
        $distributedDiscounts = [];

        foreach ($cartProducts as $cartItem) {
            $product = Product::find($cartItem['product_id']);
            if (!$product) continue;

            $quantity = $cartItem['quantity'];
            $unitPrice = $this->getProductBasePrice($product, $cartItem['variation_id'] ?? null);
            $lineTotal = $unitPrice * $quantity * ($product->package_quantity ?? 1);

            // Calculate proportional discount for this line
            $lineDiscount = ($lineTotal / $cartTotal) * $totalDiscount;
            $newUnitPrice = $unitPrice - ($lineDiscount / ($quantity * ($product->package_quantity ?? 1)));

            $distributedDiscounts[] = [
                'product_id' => $product->id,
                'variation_id' => $cartItem['variation_id'] ?? null,
                'original_price' => $unitPrice,
                'new_price' => max(0, $newUnitPrice), // Ensure price doesn't go negative
                'discount_amount' => $lineDiscount
            ];
        }

        return $distributedDiscounts;
    }
}
