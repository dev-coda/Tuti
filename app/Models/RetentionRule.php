<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetentionRule extends Model
{
    protected $fillable = [
        'tax_group',
        'product_type',
        'base_rte_fuente',
        'pct_rte_fuente',
        'base_rte_iva',
        'pct_rte_iva',
        'active',
    ];

    protected $casts = [
        'base_rte_fuente' => 'decimal:2',
        'pct_rte_fuente'  => 'decimal:4',
        'base_rte_iva'    => 'decimal:2',
        'pct_rte_iva'     => 'decimal:4',
        'active'          => 'boolean',
    ];

    public const TAX_GROUPS = [
        'C_NORETIE'  => 'C_NORETIE (Sin Retención)',
        'C_NAL'      => 'C_NAL (Nacional)',
        'C_NAL_GRC'  => 'C_NAL_GRC (Gran Contribuyente)',
    ];

    public const PRODUCT_TYPES = [
        'articulo' => 'Artículo',
        'flete'    => 'Flete',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForGroup($query, string $taxGroup)
    {
        return $query->where('tax_group', $taxGroup);
    }

    /**
     * Calculate ReteFuente for a given subtotal.
     * Applied when subtotal >= base_rte_fuente and pct > 0.
     */
    public function calculateReteFuente(float $subtotal): float
    {
        if ($this->pct_rte_fuente <= 0 || $this->base_rte_fuente <= 0) {
            return 0;
        }

        if ($subtotal < $this->base_rte_fuente) {
            return 0;
        }

        return round($subtotal * $this->pct_rte_fuente / 100, 2);
    }

    /**
     * Calculate ReteIVA for a given IVA amount.
     * Applied when IVA >= base_rte_iva and pct > 0.
     */
    public function calculateReteIva(float $ivaAmount): float
    {
        if ($this->pct_rte_iva <= 0 || $this->base_rte_iva <= 0) {
            return 0;
        }

        if ($ivaAmount < $this->base_rte_iva) {
            return 0;
        }

        return round($ivaAmount * $this->pct_rte_iva / 100, 2);
    }

    /**
     * Get all active rules for a tax group, keyed by product_type.
     */
    public static function getRulesForGroup(string $taxGroup): array
    {
        return static::active()
            ->forGroup($taxGroup)
            ->get()
            ->keyBy('product_type')
            ->toArray();
    }
}
