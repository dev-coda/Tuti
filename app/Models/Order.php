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
            // Email sending is now handled after XML transmission in OrderRepository
            // This prevents email issues from blocking order processing
        });

        static::created(function ($order) {
            // Email sending is now handled after XML transmission in OrderRepository
            // This prevents email issues from blocking order creation
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
        'delivery_method',
        'observations',
        'coupon_id',
        'coupon_code',
        'coupon_discount',
        'processing_attempts',
        'last_processing_attempt',
        'manually_retried',
        'scheduled_transmission_date',
    ];


    const STATUS_PENDING = 0;
    const STATUS_PROCESSED = 1;
    const STATUS_SHIPPED = 4;
    const STATUS_DELIVERED = 5;
    const STATUS_CANCELLED = 6;
    const STATUS_ERROR = 2;
    const STATUS_ERROR_WEBSERVICE = 3;
    const STATUS_WAITING = 7; // En espera - waiting for seller visit day

    const DELIVERY_METHOD_EXPRESS = 'express';
    const DELIVERY_METHOD_TRONEX = 'tronex';

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
            self::STATUS_WAITING => 'waiting',
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

    /**
     * Manually retry sending order confirmation email
     */
    public function retryConfirmationEmail()
    {
        try {
            $mailingService = app(MailingService::class);
            $result = $mailingService->sendOrderConfirmationEmail($this);

            if ($result) {
                \Log::info("Order confirmation email retry successful for order {$this->id}");
                return ['success' => true, 'message' => 'Email enviado correctamente'];
            } else {
                \Log::warning("Order confirmation email retry failed for order {$this->id}");
                return ['success' => false, 'message' => 'Error al enviar el email'];
            }
        } catch (\Exception $e) {
            \Log::error("Order confirmation email retry error for order {$this->id}: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Manually retry sending order status email
     */
    public function retryStatusEmail()
    {
        try {
            $mailingService = app(MailingService::class);
            $statusSlug = static::getStatusSlug($this->status_id);
            $result = $mailingService->sendOrderStatusEmail($this, $statusSlug);

            if ($result) {
                \Log::info("Order status email retry successful for order {$this->id}");
                return ['success' => true, 'message' => 'Email enviado correctamente'];
            } else {
                \Log::warning("Order status email retry failed for order {$this->id}");
                return ['success' => false, 'message' => 'Error al enviar el email'];
            }
        } catch (\Exception $e) {
            \Log::error("Order status email retry error for order {$this->id}: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function couponUsages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Increment the processing attempts counter
     */
    public function incrementProcessingAttempts(): void
    {
        $this->increment('processing_attempts');
        $this->update(['last_processing_attempt' => now()]);
    }

    /**
     * Check if order has exceeded maximum processing attempts
     */
    public function hasExceededMaxAttempts(int $maxAttempts = 3): bool
    {
        return $this->processing_attempts >= $maxAttempts;
    }

    /**
     * Check if order is stuck (pending for too long)
     */
    public function isStuck(int $hoursThreshold = 2): bool
    {
        return $this->status_id === self::STATUS_PENDING
            && $this->created_at->diffInHours(now()) >= $hoursThreshold;
    }

    /**
     * Mark order as manually retried
     */
    public function markAsManuallyRetried(): void
    {
        $this->update(['manually_retried' => true]);
    }
}
