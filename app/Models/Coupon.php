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
        if (!$this->usage_limit_per_customer) {
            return false;
        }

        $userUsageCount = $this->usages()->where('user_id', $userId)->count();
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
            return false;
        }

        // Check if product is specifically excluded
        if ($this->except_product_ids && in_array($product->id, $this->except_product_ids)) {
            return false;
        }

        // Check category exclusions
        if ($this->except_category_ids) {
            $productCategoryIds = $product->categories->pluck('id')->toArray();
            if (!empty(array_intersect($productCategoryIds, $this->except_category_ids))) {
                return false;
            }
        }

        // Check brand exclusions
        if ($this->except_brand_ids && in_array($product->brand_id, $this->except_brand_ids)) {
            return false;
        }

        // Check vendor exclusions
        if ($this->except_vendor_ids && $product->brand && in_array($product->brand->vendor_id, $this->except_vendor_ids)) {
            return false;
        }

        // Now check if it applies based on the coupon's applies_to setting
        switch ($this->applies_to) {
            case self::APPLIES_TO_CART:
                return true; // Applies to entire cart

            case self::APPLIES_TO_PRODUCT:
                return $this->applies_to_ids && in_array($product->id, $this->applies_to_ids);

            case self::APPLIES_TO_CATEGORY:
                if (!$this->applies_to_ids) return false;
                $productCategoryIds = $product->categories->pluck('id')->toArray();
                return !empty(array_intersect($productCategoryIds, $this->applies_to_ids));

            case self::APPLIES_TO_BRAND:
                return $this->applies_to_ids && in_array($product->brand_id, $this->applies_to_ids);

            case self::APPLIES_TO_VENDOR:
                return $this->applies_to_ids && $product->brand && in_array($product->brand->vendor_id, $this->applies_to_ids);

            case self::APPLIES_TO_CUSTOMER:
                return $this->applies_to_ids && in_array($user->id, $this->applies_to_ids);

            case self::APPLIES_TO_CUSTOMER_TYPE:
                if (!$this->applies_to_ids) return false;
                $userRoles = $user->roles->pluck('name')->toArray();
                return !empty(array_intersect($userRoles, $this->applies_to_ids));

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
