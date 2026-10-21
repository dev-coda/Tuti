<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentPlaceholderZone extends Model
{
    protected $fillable = [
        'state_id',
        'city_id',
        'zone',
        'route',
        'day',
        'dane_code',
        'address',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function isReady(): bool
    {
        return $this->enabled
            && trim((string) $this->zone) !== ''
            && trim((string) $this->route) !== '';
    }
}
