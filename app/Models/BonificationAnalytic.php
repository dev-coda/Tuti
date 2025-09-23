<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonificationAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'bonification_id',
        'order_id',
        'user_id',
        'product_id',
        'bonus_product_id',
        'bonus_quantity',
        'bonus_value',
        'order_total',
        'trigger_quantity',
        'user_email',
        'user_name',
    ];

    protected $casts = [
        'bonus_value' => 'decimal:2',
        'order_total' => 'decimal:2',
    ];

    public function bonification(): BelongsTo
    {
        return $this->belongsTo(Bonification::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bonusProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bonus_product_id');
    }

    /**
     * Get bonification performance analytics
     */
    public static function getBonificationPerformance($startDate = null, $endDate = null)
    {
        $query = self::with(['bonification', 'product', 'bonusProduct']);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->selectRaw('
                bonification_id,
                COUNT(*) as usage_count,
                SUM(bonus_quantity) as total_bonus_quantity,
                SUM(bonus_value) as total_bonus_value,
                AVG(bonus_value) as avg_bonus_value,
                SUM(order_total) as total_order_value,
                AVG(order_total) as avg_order_value,
                COUNT(DISTINCT user_id) as unique_users
            ')
            ->groupBy('bonification_id')
            ->orderBy('total_bonus_value', 'desc')
            ->get();
    }

    /**
     * Get most popular bonus products
     */
    public static function getPopularBonusProducts($limit = 10, $startDate = null, $endDate = null)
    {
        $query = self::with('bonusProduct');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->selectRaw('
                bonus_product_id,
                COUNT(*) as times_given,
                SUM(bonus_quantity) as total_quantity_given,
                SUM(bonus_value) as total_value_given
            ')
            ->groupBy('bonus_product_id')
            ->orderBy('times_given', 'desc')
            ->limit($limit)
            ->get();
    }
}
