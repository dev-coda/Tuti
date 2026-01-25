<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'valid_from',
        'valid_to',
        'usage_limit_per_customer',
        'usage_limit_per_vendor',
        'total_usage_limit',
        'current_usage',
        'applies_to',
        'applies_to_ids',
        'except_product_ids',
        'except_category_ids',
        'except_brand_ids',
        'except_vendor_ids',
        'except_customer_ids',
        'except_customer_types',
        'minimum_amount',
        'active',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'applies_to_ids' => 'array',
        'except_product_ids' => 'array',
        'except_category_ids' => 'array',
        'except_brand_ids' => 'array',
        'except_vendor_ids' => 'array',
        'except_customer_ids' => 'array',
        'except_customer_types' => 'array',
        'active' => 'boolean',
        'value' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
    ];

    const TYPE_FIXED_AMOUNT = 'fixed_amount';
    const TYPE_PERCENTAGE = 'percentage';

    const APPLIES_TO_CART = 'cart';
    const APPLIES_TO_PRODUCT = 'product';
    const APPLIES_TO_CATEGORY = 'category';
    const APPLIES_TO_BRAND = 'brand';
    const APPLIES_TO_VENDOR = 'vendor';
    const APPLIES_TO_CUSTOMER = 'customer';
    const APPLIES_TO_CUSTOMER_TYPE = 'customer_type';

    /**
     * Get the coupon usages
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Get the orders that used this coupon
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Check if the coupon is currently valid (active and within date range)
     */
    public function isValid(): bool
    {
        $now = Carbon::now();
        return $this->active
            && $now->between($this->valid_from, $this->valid_to);
    }

    /**
     * Check if the coupon has exceeded its total usage limit
     */
    public function hasExceededTotalLimit(): bool
    {
        return $this->total_usage_limit && $this->current_usage >= $this->total_usage_limit;
    }

    /**
     * Check if a user has exceeded their usage limit for this coupon
     */
    public function hasUserExceededLimit(int $userId): bool
    {
        // CRITICAL: Explicitly check for NULL or 0 - both mean unlimited
        // This handles cases where the field might be null, 0, empty string, or false
        if (is_null($this->usage_limit_per_customer) || $this->usage_limit_per_customer <= 0) {
            return false; // No limit set = unlimited uses
        }

        $userUsageCount = $this->usages()->where('user_id', $userId)->count();
        
        // User has exceeded if they've used it >= limit times
        // Example: limit=1, count=1 → true (can't use again)
        // Example: limit=100, count=50 → false (can use 50 more times)
        return $userUsageCount >= $this->usage_limit_per_customer;
    }

    /**
     * Check if a vendor has exceeded their usage limit for this coupon
     */
    public function hasVendorExceededLimit(int $vendorId): bool
    {
        if (!$this->usage_limit_per_vendor) {
            return false;
        }

        $vendorUsageCount = $this->usages()->where('vendor_id', $vendorId)->count();
        return $vendorUsageCount >= $this->usage_limit_per_vendor;
    }

    /**
     * Check if the coupon applies to a specific product
     */
    public function appliesToProduct(Product $product, User $user): bool
    {
        // Check if user is in the exclusion list
        if ($this->isUserExcluded($user)) {
            \Log::debug('Coupon: User excluded', [
                'coupon_id' => $this->id,
                'user_id' => $user->id,
            ]);
            return false;
        }

        // Check if product is specifically excluded
        if ($this->except_product_ids && in_array($product->id, $this->except_product_ids)) {
            \Log::debug('Coupon: Product excluded', [
                'coupon_id' => $this->id,
                'product_id' => $product->id,
            ]);
            return false;
        }

        // Check category exclusions
        if ($this->except_category_ids) {
            $productCategoryIds = $product->categories->pluck('id')->toArray();
            if (!empty(array_intersect($productCategoryIds, $this->except_category_ids))) {
                \Log::debug('Coupon: Product category excluded', [
                    'coupon_id' => $this->id,
                    'product_id' => $product->id,
                    'category_ids' => $productCategoryIds,
                ]);
                return false;
            }
        }

        // Check brand exclusions
        if ($this->except_brand_ids && in_array($product->brand_id, $this->except_brand_ids)) {
            \Log::debug('Coupon: Product brand excluded', [
                'coupon_id' => $this->id,
                'product_id' => $product->id,
                'brand_id' => $product->brand_id,
            ]);
            return false;
        }

        // Check vendor exclusions
        if ($this->except_vendor_ids && $product->brand && in_array($product->brand->vendor_id, $this->except_vendor_ids)) {
            \Log::debug('Coupon: Product vendor excluded', [
                'coupon_id' => $this->id,
                'product_id' => $product->id,
                'vendor_id' => $product->brand->vendor_id,
            ]);
            return false;
        }

        // Now check if it applies based on the coupon's applies_to setting
        // IMPORTANT: applies_to_ids is stored as JSON and may contain mixed types
        // We normalize IDs to integers for proper comparison
        $appliesIds = $this->applies_to_ids ?? [];
        
        switch ($this->applies_to) {
            case self::APPLIES_TO_CART:
                return true; // Applies to entire cart

            case self::APPLIES_TO_PRODUCT:
                if (empty($appliesIds)) return false;
                $normalizedIds = array_map('intval', $appliesIds);
                $matches = in_array((int) $product->id, $normalizedIds, true);
                \Log::debug('Coupon: APPLIES_TO_PRODUCT check', [
                    'coupon_id' => $this->id,
                    'product_id' => $product->id,
                    'applies_to_ids' => $normalizedIds,
                    'matches' => $matches,
                ]);
                return $matches;

            case self::APPLIES_TO_CATEGORY:
                if (empty($appliesIds)) return false;
                $normalizedIds = array_map('intval', $appliesIds);
                $productCategoryIds = $product->categories->pluck('id')->map(fn($id) => (int) $id)->toArray();
                $matches = !empty(array_intersect($productCategoryIds, $normalizedIds));
                \Log::debug('Coupon: APPLIES_TO_CATEGORY check', [
                    'coupon_id' => $this->id,
                    'product_id' => $product->id,
                    'product_category_ids' => $productCategoryIds,
                    'applies_to_ids' => $normalizedIds,
                    'matches' => $matches,
                ]);
                return $matches;

            case self::APPLIES_TO_BRAND:
                if (empty($appliesIds) || !$product->brand_id) {
                    \Log::debug('Coupon: APPLIES_TO_BRAND - empty applies_ids or no brand_id', [
                        'coupon_id' => $this->id,
                        'product_id' => $product->id,
                        'product_brand_id' => $product->brand_id,
                        'applies_ids_empty' => empty($appliesIds),
                    ]);
                    return false;
                }
                $normalizedIds = array_map('intval', $appliesIds);
                $matches = in_array((int) $product->brand_id, $normalizedIds, true);
                \Log::debug('Coupon: APPLIES_TO_BRAND check', [
                    'coupon_id' => $this->id,
                    'product_id' => $product->id,
                    'product_brand_id' => $product->brand_id,
                    'applies_to_ids' => $normalizedIds,
                    'matches' => $matches,
                ]);
                return $matches;

            case self::APPLIES_TO_VENDOR:
                if (empty($appliesIds) || !$product->brand || !$product->brand->vendor_id) {
                    \Log::debug('Coupon: APPLIES_TO_VENDOR - empty applies_ids or no vendor', [
                        'coupon_id' => $this->id,
                        'product_id' => $product->id,
                        'has_brand' => (bool) $product->brand,
                        'vendor_id' => $product->brand?->vendor_id,
                        'applies_ids_empty' => empty($appliesIds),
                    ]);
                    return false;
                }
                $normalizedIds = array_map('intval', $appliesIds);
                $matches = in_array((int) $product->brand->vendor_id, $normalizedIds, true);
                \Log::debug('Coupon: APPLIES_TO_VENDOR check', [
                    'coupon_id' => $this->id,
                    'product_id' => $product->id,
                    'product_vendor_id' => $product->brand->vendor_id,
                    'applies_to_ids' => $normalizedIds,
                    'matches' => $matches,
                ]);
                return $matches;

            case self::APPLIES_TO_CUSTOMER:
                if (empty($appliesIds)) return false;
                $normalizedIds = array_map('intval', $appliesIds);
                return in_array((int) $user->id, $normalizedIds, true);

            case self::APPLIES_TO_CUSTOMER_TYPE:
                if (empty($appliesIds)) return false;
                $userRoles = $user->roles->pluck('name')->toArray();
                return !empty(array_intersect($userRoles, $appliesIds));

            default:
                return false;
        }
    }

    /**
     * Check if a user is excluded from using this coupon
     */
    private function isUserExcluded(User $user): bool
    {
        // Check if user is specifically excluded
        if ($this->except_customer_ids && in_array($user->id, $this->except_customer_ids)) {
            return true;
        }

        // Check if user's roles are excluded
        if ($this->except_customer_types) {
            $userRoles = $user->roles->pluck('name')->toArray();
            if (!empty(array_intersect($userRoles, $this->except_customer_types))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate the discount amount for a given cart total
     */
    public function calculateDiscount(float $cartTotal): float
    {
        if ($this->type === self::TYPE_FIXED_AMOUNT) {
            // For fixed amount, return the full coupon value but don't exceed cart total
            return min($this->value, $cartTotal);
        } else {
            // For percentage, calculate percentage of cart total
            return $cartTotal * ($this->value / 100);
        }
    }

    /**
     * Increment the usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('current_usage');
    }

    /**
     * Scope for active coupons
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for valid coupons (active and within date range)
     */
    public function scopeValid($query)
    {
        $now = Carbon::now();
        return $query->active()
            ->where('valid_from', '<=', $now)
            ->where('valid_to', '>=', $now);
    }

    /**
     * Scope for coupons by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }
}
