<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpsellRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'config',
        'priority',
        'active',
    ];

    protected $casts = [
        'config' => 'array',
        'priority' => 'integer',
        'active' => 'boolean',
    ];

    /**
     * Scope: only active rules
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function zones()
    {
        return $this->belongsToMany(UpsellZone::class, 'upsell_zone_rules')
            ->withPivot('priority', 'active', 'config');
    }

    public static function getRuleTypes(): array
    {
        return [
            'recent_orders' => 'Productos de pedidos recientes',
            'favorite_products' => 'Productos favoritos',
            'same_category' => 'Misma categoría',
            'same_brand' => 'Misma marca',
            'best_selling' => 'Más vendidos',
            'manual' => 'Manual (productos asignados)',
            'related_products' => 'Productos relacionados (tabla product_related)',
        ];
    }
}
