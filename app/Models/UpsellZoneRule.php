<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpsellZoneRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'upsell_zone_id',
        'upsell_rule_id',
        'priority',
        'active',
        'config',
    ];

    protected $casts = [
        'priority' => 'integer',
        'active' => 'boolean',
        'config' => 'array',
    ];

    public function zone()
    {
        return $this->belongsTo(UpsellZone::class, 'upsell_zone_id');
    }

    public function rule()
    {
        return $this->belongsTo(UpsellRule::class, 'upsell_rule_id');
    }
}
