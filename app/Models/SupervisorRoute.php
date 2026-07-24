<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Zone/route pair assigned to a supervisor so they can monitor every order
 * placed on that route from the "Mis Rutas" tab in Mi Cuenta.
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
        return 'Zona ' . $this->zone . ' — Ruta ' . $this->route;
    }
}
