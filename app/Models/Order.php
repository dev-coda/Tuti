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
        'zone_snapshot',
        'seller_id',
        'delivery_date',
        'delivery_method',
        'shipping_provider',
        'shipping_quote_amount',
        'observations',
        'coupon_id',
        'coupon_code',
        'coupon_discount',
        'processing_attempts',
        'last_processing_attempt',
        'manually_retried',
        'scheduled_transmission_date',
        'draft_reconciliation_note',
        'draft_reconciliation_at',
        'coordinadora_guide_number',
        'coordinadora_status_code',
        'coordinadora_status_text',
        'coordinadora_status_at',
        'fv_number',
        'fv_request_payload',
        'fv_response_payload',
        'coordinadora_request_payload',
        'coordinadora_response_payload',
        'coordinadora_packages',
        'tax_group',
        'retention_fuente',
        'retention_iva',
        'retention_total',
    ];

    protected $casts = [
        'last_processing_attempt' => 'datetime',
        'coordinadora_status_at' => 'datetime',
        'shipping_quote_amount' => 'decimal:2',
        'zone_snapshot' => 'array',
        'coordinadora_packages' => 'array',
        'draft_reconciliation_at' => 'datetime',
        'retention_fuente' => 'decimal:2',
        'retention_iva' => 'decimal:2',
        'retention_total' => 'decimal:2',
    ];


    const STATUS_PENDING = 0;
    const STATUS_PROCESSED = 1;
    const STATUS_SHIPPED = 4;
    const STATUS_DELIVERED = 5;
    const STATUS_CANCELLED = 6;
    const STATUS_ERROR = 2;
    const STATUS_ERROR_WEBSERVICE = 3;
    const STATUS_WAITING = 7; // En espera - waiting for seller visit day
    const STATUS_DRAFT = 8; // Borrador interno - pending rutero / not transmitted

    const DELIVERY_METHOD_EXPRESS = 'express';
    const DELIVERY_METHOD_TRONEX = 'tronex';
    const SHIPPING_PROVIDER_COORDINADORA = 'coordinadora';
    const SHIPPING_PROVIDER_TRONEX = 'tronex';

    // Order origin: RUTA = placed by a seller on behalf of the client,
    // AUTONOMO = placed by the client on their own. Derived from seller_id,
    // which checkout only fills when a seller/supervisor processes the cart.
    const ORIGIN_RUTA = 'ruta';
    const ORIGIN_AUTONOMO = 'autonomo';

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
            self::STATUS_DRAFT => 'draft',
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

    /**
     * Whether the order was created by a seller (RUTA) or by the client (Autónomo).
     */
    public function getOriginAttribute(): string
    {
        return $this->seller_id !== null ? self::ORIGIN_RUTA : self::ORIGIN_AUTONOMO;
    }

    public function getOriginLabelAttribute(): string
    {
        return $this->origin === self::ORIGIN_RUTA ? 'RUTA' : 'Autónomo';
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Tax-inclusive merchandise total for display (matches cart / storefront).
     *
     * Prefers recomputing from line items + product tax so older orders stored
     * without IVA still show the correct total. Falls back to the stored total.
     */
    public function totalWithTax(): float
    {
        $fromLines = $this->sumLineTotalsWithTax();
        if ($fromLines !== null) {
            return $fromLines;
        }

        return round((float) $this->total, 2);
    }

    /**
     * Sum tax-inclusive totals for a query without loading every order at once.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Order>  $query
     */
    public static function sumTotalWithTaxForQuery($query, int $chunkSize = 200): float
    {
        $sum = 0.0;

        (clone $query)
            ->with(['products.product.tax'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($orders) use (&$sum) {
                foreach ($orders as $order) {
                    $sum += $order->totalWithTax();
                }
            });

        return round($sum, 2);
    }

    /**
     * Tax-inclusive discount amount paired with totalWithTax() for resumen math.
     */
    public function discountWithTax(): float
    {
        $fromLines = $this->sumLineDiscountsWithTax();
        if ($fromLines !== null) {
            return $fromLines;
        }

        return round((float) $this->discount, 2);
    }

    /**
     * @return float|null Null when line items / tax relations are unavailable
     */
    public function sumLineTotalsWithTax(): ?float
    {
        if (! $this->relationLoaded('products')) {
            $this->loadMissing('products.product.tax');
        } elseif ($this->products->isNotEmpty() && ! $this->products->first()?->relationLoaded('product')) {
            $this->loadMissing('products.product.tax');
        }

        if ($this->products->isEmpty()) {
            return null;
        }

        $sum = 0.0;
        foreach ($this->products as $line) {
            $exclusive = $this->lineExclusiveNet($line);
            if ($exclusive === null) {
                return null;
            }
            $taxPct = (float) (optional($line->product?->tax)->tax ?? 0);
            $sum += amount_with_tax($exclusive, $taxPct);
        }

        return round($sum, 2);
    }

    /**
     * @return float|null
     */
    public function sumLineDiscountsWithTax(): ?float
    {
        if (! $this->relationLoaded('products')) {
            $this->loadMissing('products.product.tax');
        } elseif ($this->products->isNotEmpty() && ! $this->products->first()?->relationLoaded('product')) {
            $this->loadMissing('products.product.tax');
        }

        if ($this->products->isEmpty()) {
            return null;
        }

        $sum = 0.0;
        foreach ($this->products as $line) {
            $exclusiveDiscount = $this->lineExclusiveDiscount($line);
            if ($exclusiveDiscount === null) {
                return null;
            }
            $taxPct = (float) (optional($line->product?->tax)->tax ?? 0);
            $sum += amount_with_tax($exclusiveDiscount, $taxPct);
        }

        return round($sum, 2);
    }

    /**
     * Net merchandise for a line before IVA (lista / SOAP basis).
     */
    private function lineExclusiveNet(OrderProduct $line): ?float
    {
        $price = (float) $line->price;
        $qty = (float) $line->quantity;
        $pkg = max(1.0, (float) ($line->package_quantity ?: 1));
        $product = $line->product;

        if (($line->discount_type ?? 'percentage') === 'fixed_amount') {
            $flat = (float) ($line->flat_discount_amount ?? 0);
            if ($product?->calculate_package_price) {
                $unitPrice = $pkg > 1 ? ($price / $pkg) : $price;

                return max(0, $unitPrice - $flat) * $pkg * $qty;
            }

            return max(0, $price - $flat) * $qty * $pkg;
        }

        $pct = max(0.0, min(100.0, (float) ($line->percentage ?? 0)));
        $gross = $product?->calculate_package_price
            ? ($price * $qty)
            : ($price * $qty * $pkg);

        return $gross * (1 - ($pct / 100));
    }

    /**
     * Discount for a line before IVA.
     */
    private function lineExclusiveDiscount(OrderProduct $line): ?float
    {
        $price = (float) $line->price;
        $qty = (float) $line->quantity;
        $pkg = max(1.0, (float) ($line->package_quantity ?: 1));
        $product = $line->product;

        if (($line->discount_type ?? 'percentage') === 'fixed_amount') {
            $flat = (float) ($line->flat_discount_amount ?? 0);
            if ($product?->calculate_package_price) {
                return max(0, $flat) * $pkg * $qty;
            }

            return max(0, $flat) * $qty * $pkg;
        }

        $pct = max(0.0, min(100.0, (float) ($line->percentage ?? 0)));
        if ($pct <= 0) {
            return 0.0;
        }

        $gross = $product?->calculate_package_price
            ? ($price * $qty)
            : ($price * $qty * $pkg);

        return $gross * ($pct / 100);
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
