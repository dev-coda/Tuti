<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tamaño de empaque for Coordinadora shipments. Orders are assigned the
 * smallest package (or multiples of the largest) that fits their products.
 */
class PackageType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'max_weight_kg',
        'max_length_cm',
        'max_width_cm',
        'max_height_cm',
        'active',
        'position',
    ];

    protected $casts = [
        'max_weight_kg' => 'decimal:3',
        'max_length_cm' => 'decimal:2',
        'max_width_cm' => 'decimal:2',
        'max_height_cm' => 'decimal:2',
        'active' => 'boolean',
        'position' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function volumeCm3(): float
    {
        return (float) $this->max_length_cm * (float) $this->max_width_cm * (float) $this->max_height_cm;
    }

    /**
     * Whether a single item fits inside this package, comparing sorted
     * dimension triples so item orientation does not matter.
     */
    public function fitsItem(float $lengthCm, float $widthCm, float $heightCm): bool
    {
        $itemDims = [$lengthCm, $widthCm, $heightCm];
        sort($itemDims);

        $boxDims = [(float) $this->max_length_cm, (float) $this->max_width_cm, (float) $this->max_height_cm];
        sort($boxDims);

        return $itemDims[0] <= $boxDims[0]
            && $itemDims[1] <= $boxDims[1]
            && $itemDims[2] <= $boxDims[2];
    }
}
