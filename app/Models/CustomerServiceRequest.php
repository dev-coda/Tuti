<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerServiceRequest extends Model
{
    use HasFactory;

    public const REQUEST_TYPES = [
        'pregunta' => 'Pregunta',
        'queja' => 'Queja',
        'reclamo' => 'Reclamo',
    ];

    protected $fillable = [
        'full_name',
        'email',
        'city',
        'phone',
        'request_type',
        'subject',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function getRequestTypeLabelAttribute(): string
    {
        return self::REQUEST_TYPES[$this->request_type] ?? ucfirst($this->request_type);
    }
}
