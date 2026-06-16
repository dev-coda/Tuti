<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDataUpdateRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'zone_id',
        'submitted_by',
        'document',
        'name',
        'business_name',
        'email',
        'phone',
        'mobile_phone',
        'whatsapp',
        'address',
        'city_name',
        'zone_code',
        'route',
        'day',
        'seller_notes',
        'previous_data',
        'read_at',
    ];

    protected $casts = [
        'previous_data' => 'array',
        'read_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
