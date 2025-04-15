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
        'user_id',
    ];

    public function user()
    {

        return $this->belongsTo(User::class);

    }
}
