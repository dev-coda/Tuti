<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'discount_type',
        'discount_id',
        'discount_name',
        'discount_value_type',
        'discount_value',
        'discount_amount',
        'original_amount',
        'final_amount',
        'applied_to',
        'notes',
    ];

    protected $casts = [
        'applied_to' => 'array',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    const DISCOUNT_TYPE_PRODUCT = 'product';
    const DISCOUNT_TYPE_BRAND = 'brand';
    const DISCOUNT_TYPE_VENDOR = 'vendor';
    const DISCOUNT_TYPE_COUPON = 'coupon';
    const DISCOUNT_TYPE_PROMOCION = 'promocion';
    const DISCOUNT_TYPE_VOLUME_DISCOUNT = 'volume_discount';
    const DISCOUNT_TYPE_BONIFICATION = 'bonification';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get analytics data for a specific period
     */
    public static function getAnalytics($startDate = null, $endDate = null)
    {
        $query = self::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->get();
    }

    /**
     * Get total discount amount by type
     */
    public static function getTotalDiscountsByType($startDate = null, $endDate = null)
    {
        $query = self::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->selectRaw('discount_type, COUNT(*) as count, SUM(discount_amount) as total_amount')
            ->groupBy('discount_type')
            ->orderBy('total_amount', 'desc')
            ->get();
    }

    /**
     * Get top performing discounts
     */
    public static function getTopPerformingDiscounts($limit = 10, $startDate = null, $endDate = null)
    {
        $query = self::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->selectRaw('discount_name, discount_type, COUNT(*) as usage_count, SUM(discount_amount) as total_discount')
            ->groupBy('discount_name', 'discount_type')
            ->orderBy('total_discount', 'desc')
            ->limit($limit)
            ->get();
    }
}
