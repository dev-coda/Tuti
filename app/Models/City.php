<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'state_id',
        'active',
        'is_preferred',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_preferred' => 'boolean',
    ];

    // Query Scopes for safe city filtering
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopePreferred($query)
    {
        return $query->where('is_preferred', true);
    }

    public function scopeForRegistration($query)
    {
        return $query->active()->preferred();
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
