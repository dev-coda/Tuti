<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SpendingGift extends Model
{
    protected $fillable = [
        'name',
        'minimum_amount',
        'product_id',
        'gift_quantity',
        'gift_invoice_unit_value',
        'gift_proration_scope',
        'active',
    ];

    protected $casts = [
        'minimum_amount' => 'float',
        'gift_invoice_unit_value' => 'float',
        'gift_quantity' => 'integer',
        'active' => 'boolean',
    ];

    public function giftProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** Productos cuya compra (con IVA) suma para alcanzar el monto mínimo. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'spending_gift_product');
    }

    public function giftInvoiceUnitValue(): float
    {
        $configured = (float) ($this->gift_invoice_unit_value ?? 0);

        return $configured > 0
            ? $configured
            : (float) config('express_gifts.default_min_unit_value', 1.0);
    }

    public function giftProrationScope(): string
    {
        return $this->gift_proration_scope === Bonification::PRORATION_TRIGGER
            ? Bonification::PRORATION_TRIGGER
            : Bonification::PRORATION_CART;
    }
}
