<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Zone assigned to a supervisor so they can monitor every order placed in that
 * zona (any ruta) from the "Mis Zonas" tab in Mi Cuenta.
 */
class SupervisorRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'zone',
        'route',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return 'Zona '.$this->zone;
    }
}
