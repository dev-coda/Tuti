<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bonification extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'buy',
        'get',
        'product_id',
        'max',
        'sum_products',
        'allow_discounts',
        'gift_invoice_unit_value',
        'gift_proration_scope',
    ];

    public const PRORATION_CART = 'cart';

    public const PRORATION_TRIGGER = 'trigger';

    protected $casts = [
        'allow_discounts' => 'boolean',
        'sum_products' => 'boolean',
        'gift_invoice_unit_value' => 'decimal:2',
    ];


    public function product(){
        return $this->belongsTo(Product::class);
    }

    public function products(){
        return $this->belongsToMany(Product::class)->orderBy('name');
    }

    /**
     * Valor neto minimo (sin IVA) por unidad con el que se factura el obsequio en pedidos Express.
     */
    public function giftInvoiceUnitValue(): float
    {
        $configured = (float) ($this->gift_invoice_unit_value ?? 0);

        return $configured > 0
            ? $configured
            : (float) config('express_gifts.default_min_unit_value', 1.0);
    }

    /**
     * cart: el valor se reparte en todos los productos sin descuento.
     * trigger: solo en los productos que activan esta bonificacion.
     */
    /** Las unidades de los productos asociados se suman para alcanzar la cantidad comprada. */
    public function sumsProducts(): bool
    {
        return (bool) $this->sum_products;
    }

    public function giftProrationScope(): string
    {
        return $this->gift_proration_scope === self::PRORATION_TRIGGER
            ? self::PRORATION_TRIGGER
            : self::PRORATION_CART;
    }
}
