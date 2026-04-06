<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'route',
        'zone',
        'day',
        'address',
        'code',
        'tax_group',
        'zip_code',
        'fulfillment_provider_48h',
        'user_id',
    ];

    public const FULFILLMENT_PROVIDER_COORDINADORA = 'coordinadora';
    public const FULFILLMENT_PROVIDER_TRONEX = 'tronex';

    public function usesCoordinadoraFor48h(): bool
    {
        return ($this->fulfillment_provider_48h ?? self::FULFILLMENT_PROVIDER_COORDINADORA) === self::FULFILLMENT_PROVIDER_COORDINADORA;
    }

    public function user()
    {

        return $this->belongsTo(User::class);

    }
}
