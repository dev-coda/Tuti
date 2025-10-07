<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class VolumeDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'discount_type',
        'discount_value',
        'min_quantity',
        'max_quantity',
        'applies_to',
        'applies_to_ids',
        'valid_from',
        'valid_to',
        'active',
    ];

    protected $casts = [
        'applies_to_ids' => 'array',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'active' => 'boolean',
        'discount_value' => 'decimal:2',
    ];

    const DISCOUNT_TYPE_PERCENTAGE = 'percentage';
    const DISCOUNT_TYPE_FIXED_AMOUNT = 'fixed_amount';

    const APPLIES_TO_PRODUCTS = 'products';
    const APPLIES_TO_CATEGORIES = 'categories';
    const APPLIES_TO_BRANDS = 'brands';
    const APPLIES_TO_VENDORS = 'vendors';
    const APPLIES_TO_CART = 'cart';

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'volume_discount_products');
    }

    public function isActive(): bool
    {
        $now = Carbon::now();
        return $this->active && $now->between($this->valid_from, $this->valid_to);
    }

    public function calculateDiscount(float $quantity, float $unitPrice): float
    {
        if (!$this->isActive() || $quantity < $this->min_quantity) {
            return 0;
        }

        if ($this->max_quantity && $quantity > $this->max_quantity) {
            $quantity = $this->max_quantity;
        }

        $totalValue = $quantity * $unitPrice;

        if ($this->discount_type === self::DISCOUNT_TYPE_PERCENTAGE) {
            return $totalValue * ($this->discount_value / 100);
        } else {
            return min($this->discount_value, $totalValue);
        }
    }
}
