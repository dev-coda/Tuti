<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image',
        'banner',
        'slug',
        'minimum_purchase',
        'minimum_discount_amount',
        'active',
        'discount',
        'discount_type',
        'first_purchase_only',
        'vendor_type',
    ];

    public function brands()
    {
        return $this->hasMany(Brand::class);
    }

    /**
     * Percentage discount a linked brand should inherit from this proveedor.
     * A higher percentage already set on the brand is left in place.
     * Fixed-amount proveedor discounts are not copied onto brands.
     *
     * @return array{discount: mixed, discount_type: string, first_purchase_only: bool}|null
     */
    public function inheritedDiscountAttributes(?string $brandDiscountType, mixed $brandDiscount): ?array
    {
        if ((float) $this->discount <= 0) {
            return null;
        }

        if (($this->discount_type ?: 'percentage') !== 'percentage') {
            return null;
        }

        $brandDiscount = (float) $brandDiscount;
        $brandType = $brandDiscountType ?: 'percentage';
        $inherits = $brandDiscount <= 0
            || ($brandType === 'percentage' && $brandDiscount <= (float) $this->discount);

        if (! $inherits) {
            return null;
        }

        return [
            'discount' => $this->discount,
            'discount_type' => 'percentage',
            'first_purchase_only' => (bool) $this->first_purchase_only,
        ];
    }

    /**
     * Copy this proveedor's percentage discount onto its linked brands.
     */
    public function propagateDiscountToBrands(): int
    {
        $updated = 0;

        foreach ($this->brands()->get() as $brand) {
            $attributes = $this->inheritedDiscountAttributes($brand->discount_type, $brand->discount);
            if ($attributes === null) {
                continue;
            }

            $unchanged = (float) $brand->discount === (float) $attributes['discount']
                && ($brand->discount_type ?: 'percentage') === $attributes['discount_type']
                && (bool) $brand->first_purchase_only === $attributes['first_purchase_only'];

            if ($unchanged) {
                continue;
            }

            $brand->update($attributes);
            $updated++;
        }

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function applyInheritedDiscount(array $attributes): array
    {
        $vendor = static::query()->find($attributes['vendor_id'] ?? null);
        if (! $vendor) {
            return $attributes;
        }

        $inherited = $vendor->inheritedDiscountAttributes(
            $attributes['discount_type'] ?? 'percentage',
            $attributes['discount'] ?? 0
        );

        if ($inherited === null) {
            return $attributes;
        }

        return array_merge($attributes, $inherited);
    }
}
