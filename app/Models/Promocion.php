<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Promocion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'discount_type',
        'discount_value',
        'valid_from',
        'valid_to',
        'level',
        'level_ids',
        'minimum_cart_value',
        'minimum_cart_units',
        'usage_limit',
        'current_usage',
        'active',
    ];

    protected $casts = [
        'level_ids' => 'array',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'active' => 'boolean',
        'discount_value' => 'decimal:2',
        'minimum_cart_value' => 'decimal:2',
    ];

    const DISCOUNT_TYPE_PERCENTAGE = 'percentage';
    const DISCOUNT_TYPE_FIXED_AMOUNT = 'fixed_amount';

    const LEVEL_PRODUCTS = 'products';
    const LEVEL_CATEGORIES = 'categories';
    const LEVEL_BRANDS = 'brands';
    const LEVEL_VENDORS = 'vendors';
    const LEVEL_ZONES = 'zones';

    public function usages(): HasMany
    {
        return $this->hasMany(PromocionUsage::class);
    }

    public function isActive(): bool
    {
        $now = Carbon::now();
        return $this->active && $now->between($this->valid_from, $this->valid_to);
    }

    public function hasReachedUsageLimit(): bool
    {
        return $this->usage_limit && $this->current_usage >= $this->usage_limit;
    }

    public function calculateDiscount(float $cartValue, int $cartUnits = 0): float
    {
        if (!$this->isActive() || $this->hasReachedUsageLimit()) {
            return 0;
        }

        // Check minimum requirements
        if ($this->minimum_cart_value && $cartValue < $this->minimum_cart_value) {
            return 0;
        }

        if ($this->minimum_cart_units && $cartUnits < $this->minimum_cart_units) {
            return 0;
        }

        if ($this->discount_type === self::DISCOUNT_TYPE_PERCENTAGE) {
            return $cartValue * ($this->discount_value / 100);
        } else {
            return min($this->discount_value, $cartValue);
        }
    }

    public function incrementUsage(): void
    {
        $this->increment('current_usage');
    }
}
