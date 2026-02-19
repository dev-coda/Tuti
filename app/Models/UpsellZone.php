<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UpsellZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'display_title',
        'active',
        'position',
        'max_products',
        'context',
    ];

    protected $casts = [
        'active' => 'boolean',
        'position' => 'integer',
        'max_products' => 'integer',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'upsell_zone_products')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    public function rules()
    {
        return $this->belongsToMany(UpsellRule::class, 'upsell_zone_rules')
            ->withPivot('priority', 'active', 'config')
            ->orderByPivot('priority', 'desc');
    }

    public function activeRules()
    {
        return $this->rules()->wherePivot('active', true);
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->where('active', true)->first();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($zone) {
            if (empty($zone->slug)) {
                $zone->slug = Str::slug($zone->name);
            }
        });
    }
}
