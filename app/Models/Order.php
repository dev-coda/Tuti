<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\MailingService;

class Order extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::updating(function ($order) {
            // Check if status is being changed
            if ($order->isDirty('status_id') && $order->status_id != $order->getOriginal('status_id')) {
                $oldStatus = $order->getOriginal('status_id');
                $newStatus = $order->status_id;

                // Send status change email
                $mailingService = app(MailingService::class);
                $mailingService->sendOrderStatusEmail($order, static::getStatusSlug($newStatus));
            }
        });

        static::created(function ($order) {
            // Send order confirmation email when order is created
            $mailingService = app(MailingService::class);
            $mailingService->sendOrderConfirmationEmail($order);
        });
    }

    protected $fillable = [
        'user_id',
        'total',
        'discount',
        'status_id',
        'request',
        'response',
        'zone_id',
        'seller_id',
        'delivery_date',
        'observations',
        'coupon_id',
        'coupon_code',
        'coupon_discount'
    ];


    const STATUS_PENDING = 0;
    const STATUS_PROCESSED = 1;
    const STATUS_SHIPPED = 4;
    const STATUS_DELIVERED = 5;
    const STATUS_CANCELLED = 6;
    const STATUS_ERROR = 2;
    const STATUS_ERROR_WEBSERVICE = 3;

    /**
     * Get status slug from status ID
     */
    public static function getStatusSlug($statusId)
    {
        $statusMap = [
            self::STATUS_PENDING => 'pending',
            self::STATUS_PROCESSED => 'processed',
            self::STATUS_SHIPPED => 'shipped',
            self::STATUS_DELIVERED => 'delivered',
            self::STATUS_CANCELLED => 'cancelled',
            self::STATUS_ERROR => 'error',
            self::STATUS_ERROR_WEBSERVICE => 'error',
        ];

        return $statusMap[$statusId] ?? 'unknown';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(OrderProduct::class);
        // return $this->belongsToMany(Product::class)->withPivot(["quantity","price", "discount", "variation_id", 'is_bonification']);
    }


    public function bonifications()
    {
        return $this->hasMany(OrderProductBonification::class);
    }


    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function couponUsages()
    {
        return $this->hasMany(CouponUsage::class);
    }
}
