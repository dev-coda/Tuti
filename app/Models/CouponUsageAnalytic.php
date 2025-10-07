<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class CouponUsageAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'order_id',
        'user_id',
        'discount_amount',
        'order_total',
        'order_subtotal',
        'items_count',
        'applied_to_products',
        'user_email',
        'user_name',
    ];

    protected $casts = [
        'applied_to_products' => 'array',
        'discount_amount' => 'decimal:2',
        'order_total' => 'decimal:2',
        'order_subtotal' => 'decimal:2',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get coupon performance analytics
     */
    public static function getCouponPerformance($startDate = null, $endDate = null)
    {
        $query = self::with('coupon');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->selectRaw('
                coupon_id,
                COUNT(*) as usage_count,
                SUM(discount_amount) as total_discount,
                AVG(discount_amount) as avg_discount,
                SUM(order_total) as total_order_value,
                AVG(order_total) as avg_order_value,
                COUNT(DISTINCT user_id) as unique_users
            ')
            ->groupBy('coupon_id')
            ->orderBy('total_discount', 'desc')
            ->get();
    }

    /**
     * Get coupon usage trends over time
     */
    public static function getUsageTrends($startDate = null, $endDate = null, $groupBy = 'day')
    {
        $query = self::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        // Use database-agnostic date grouping
        $dateTrunc = match ($groupBy) {
            'hour' => "DATE_TRUNC('hour', created_at)",
            'day' => "DATE_TRUNC('day', created_at)",
            'week' => "DATE_TRUNC('week', created_at)",
            'month' => "DATE_TRUNC('month', created_at)",
            default => "DATE_TRUNC('day', created_at)"
        };

        return $query->selectRaw("
                {$dateTrunc} as period,
                COUNT(*) as usage_count,
                SUM(discount_amount) as total_discount,
                COUNT(DISTINCT coupon_id) as unique_coupons,
                COUNT(DISTINCT user_id) as unique_users
            ")
            ->groupBy('period')
            ->orderBy('period')
            ->get();
    }
}
