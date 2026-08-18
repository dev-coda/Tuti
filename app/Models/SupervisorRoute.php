<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Zone and/or route assigned to a supervisor so they can monitor orders
 * placed in that coverage from the "Mis Zonas" tab in Mi Cuenta.
 *
 * An empty route means the whole zona (any ruta). A filled route locks
 * visibility to that zona+ruta pair.
 */
class SupervisorRoute extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'zone',
        'route',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $assignment) {
            $assignment->route = trim((string) ($assignment->route ?? ''));
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Assigned ruta, or null when this row covers every route in the zona.
     */
    public function resolvedRoute(): ?string
    {
        $route = trim((string) ($this->route ?? ''));

        return $route === '' ? null : $route;
    }

    public function isZoneWide(): bool
    {
        return $this->resolvedRoute() === null;
    }

    public function label(): string
    {
        $route = $this->resolvedRoute();

        return $route === null
            ? 'Zona '.$this->zone
            : 'Zona '.$this->zone.' — Ruta '.$route;
    }
}
