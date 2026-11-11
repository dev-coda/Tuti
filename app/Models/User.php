<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Notifications\ResetPasswordNotification;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use HasRoles;

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */


    protected $fillable = [
        'name',
        'email',
        'city_id',
        'password',
        'must_change_password',
        'document',
        'phone',
        'status_id',
        'tronex_migration_pending',
        'terms_accepted',
        'zone',
        'covers_all_zones',
        // New fields from getRuteros API
        'mobile_phone',
        'whatsapp',
        'business_name',
        'account_num',
        'city_code',
        'county_id',
        'customer_type',
        'price_group',
        'tax_group',
        'line_discount',
        'balance',
        'quota_value',
        'customer_status',
        'is_locked',
        'order_sequence',
        'rutero_synced_at',
        'client_status',
    ];

    const PENDING = 1;
    const ACTIVE = 2;

    const CLIENT_STATUS_PROSPECTO = 'prospecto';
    const CLIENT_STATUS_PENDIENTE = 'pendiente';
    const CLIENT_STATUS_CLIENTE = 'cliente';
    const CLIENT_STATUS_RECHAZADO = 'rechazado';

    private const INTERNAL_EMAIL_SUFFIXES = [
        '@tuti',
        '@tuti.com',
        '@tuti.com.co',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'must_change_password' => 'boolean',
        'tronex_migration_pending' => 'boolean',
        'rutero_synced_at' => 'datetime',
        'is_locked' => 'boolean',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    /**
     * Zones assigned to this user when they are a supervisor.
     */
    public function supervisorRoutes()
    {
        return $this->hasMany(SupervisorRoute::class)->orderBy('zone')->orderBy('route');
    }

    public const STAFF_SELLER_ROLES = ['seller', 'supervisor', 'supernumerario'];

    public function resolvedCity(): ?City
    {
        // Prod adaptation: stage maps city_code (DANE) via City::findIdByDaneCode,
        // which does not exist on master. city_id relation is the prod signal.
        return $this->city;
    }

    public function isStaffSeller(): bool
    {
        return $this->hasAnyRole(self::STAFF_SELLER_ROLES);
    }

    public function coversAllZones(): bool
    {
        if (! $this->hasRole('supernumerario')) {
            return false;
        }

        // Missing attribute keeps the historical behavior: the role covers every zone.
        $flag = $this->getAttributes()['covers_all_zones'] ?? null;
        if ($flag === null || $flag === '') {
            return true;
        }

        return filter_var($flag, FILTER_VALIDATE_BOOLEAN);
    }

    public function canServeClient(User $client): bool
    {
        if (! $this->isStaffSeller()) {
            return false;
        }

        if ($this->coversAllZones()) {
            return true;
        }

        $client->loadMissing('zones');

        foreach ($client->zones as $zone) {
            $zoneCode = trim((string) $zone->zone);
            $route = trim((string) $zone->route);
            foreach ($this->supervisedCoverages() as $coverage) {
                if ($coverage['zone'] !== $zoneCode) {
                    continue;
                }
                if ($coverage['route'] === null || $coverage['route'] === '' || $coverage['route'] === $route) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Warehouse whose stock this user should see in the catalog.
     * A mapped sales zone that is not Medellín wins; Cúcuta (CU00 zones
     * 409/411/413) must not fall through to the Medellín MDTAT default.
     */
    public function catalogBodegaCode(): ?string
    {
        $candidates = [];

        if ($this->hasRole('supervisor')) {
            $candidates[] = $this->bodegaFromZoneCodes($this->supervisorRoutes()->pluck('zone')->all());
        }

        if (! $this->isStaffSeller()) {
            $candidates[] = $this->bodegaFromZoneCodes($this->zones()->pluck('zone')->all());
        }

        $ownZone = trim((string) $this->zone);
        if ($ownZone !== '') {
            $candidates[] = ZoneWarehouse::getBodegaForZone($ownZone);
        }

        foreach ($candidates as $bodega) {
            if (is_string($bodega) && $bodega !== '' && $bodega !== 'MDTAT') {
                return $bodega;
            }
        }

        $cityBodega = ZoneWarehouse::bodegaForCity($this->resolvedCity());
        if (is_string($cityBodega) && $cityBodega !== '' && $cityBodega !== 'MDTAT') {
            return $cityBodega;
        }

        foreach ($candidates as $bodega) {
            if (is_string($bodega) && $bodega !== '') {
                return $bodega;
            }
        }

        return $cityBodega;
    }

    /**
     * @param  array<int, mixed>  $zoneCodes
     */
    private function bodegaFromZoneCodes(array $zoneCodes): ?string
    {
        $bodegas = [];
        foreach ($zoneCodes as $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }
            $bodega = ZoneWarehouse::getBodegaForZone($code);
            if (is_string($bodega) && $bodega !== '') {
                $bodegas[] = $bodega;
            }
        }

        $bodegas = array_values(array_unique($bodegas));
        if ($bodegas === []) {
            return null;
        }

        $cityBodega = ZoneWarehouse::bodegaForCity($this->resolvedCity());
        if ($cityBodega && in_array($cityBodega, $bodegas, true)) {
            return $cityBodega;
        }

        foreach ($bodegas as $bodega) {
            if ($bodega !== 'MDTAT') {
                return $bodega;
            }
        }

        return $bodegas[0];
    }

    /**
     * @return array<int, array{zone: string, route: ?string}>
     */
    private function allZoneCoverages(): array
    {
        return ZoneRoute::query()
            ->whereNotNull('zone')
            ->where('zone', '!=', '')
            ->distinct()
            ->pluck('zone')
            ->merge(
                Zone::query()
                    ->whereNotNull('zone')
                    ->where('zone', '!=', '')
                    ->distinct()
                    ->pluck('zone')
            )
            ->map(fn ($zone) => trim((string) $zone))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->map(fn ($zone) => ['zone' => $zone, 'route' => null])
            ->all();
    }

    /**
     * Zone/route pairs this staff user covers.
     *
     * `route` is null when the assignment (or the legacy users.zone value)
     * covers every ruta in that zona. A zone-wide row subsumes specific
     * routes in the same zona.
     *
     * @return array<int, array{zone: string, route: ?string}>
     */
    public function supervisedCoverages(): array
    {
        if ($this->coversAllZones()) {
            return $this->allZoneCoverages();
        }

        $coverages = [];
        $assignments = $this->supervisorRoutes;
        $legacyZone = trim((string) $this->zone);

        // users.zone is zone-wide, but must not override route-specific
        // assignments in the same zona (admin "zona principal" vs locked rutas).
        $legacyOverlapsSpecificRoute = $legacyZone !== '' && $assignments->contains(
            fn ($assignment) => trim((string) $assignment->zone) === $legacyZone
                && $assignment->resolvedRoute() !== null
        );

        if ($legacyZone !== '' && ! $legacyOverlapsSpecificRoute) {
            $coverages[] = ['zone' => $legacyZone, 'route' => null];
        }

        foreach ($assignments as $assignment) {
            $zone = trim((string) $assignment->zone);
            if ($zone === '') {
                continue;
            }

            $coverages[] = [
                'zone' => $zone,
                'route' => $assignment->resolvedRoute(),
            ];
        }

        $zoneWide = [];
        foreach ($coverages as $coverage) {
            if ($coverage['route'] === null) {
                $zoneWide[$coverage['zone']] = true;
            }
        }

        $deduped = [];
        $seen = [];
        foreach ($coverages as $coverage) {
            if (isset($zoneWide[$coverage['zone']])) {
                $key = $coverage['zone'].'|';
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $deduped[] = ['zone' => $coverage['zone'], 'route' => null];

                continue;
            }

            $key = $coverage['zone'].'|'.$coverage['route'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $coverage;
        }

        return $deduped;
    }

    /**
     * Every zona this staff user covers: the legacy single users.zone value
     * plus the zones of their supervisor assignments.
     *
     * @return array<int, string>
     */
    public function supervisedZones(): array
    {
        return collect($this->supervisedCoverages())
            ->pluck('zone')
            ->unique()
            ->values()
            ->all();
    }

    public function favoriteProducts()
    {
        return $this->belongsToMany(Product::class, 'user_favorite_products')
            ->withTimestamps();
    }

    public function isPendingClient(): bool
    {
        return ($this->client_status ?? self::CLIENT_STATUS_CLIENTE) === self::CLIENT_STATUS_PENDIENTE;
    }

    public function isProspectClient(): bool
    {
        return ($this->client_status ?? self::CLIENT_STATUS_CLIENTE) === self::CLIENT_STATUS_PROSPECTO;
    }

    public function isCliente(): bool
    {
        return ($this->client_status ?? self::CLIENT_STATUS_CLIENTE) === self::CLIENT_STATUS_CLIENTE;
    }

    public function isRejectedClient(): bool
    {
        return ($this->client_status ?? self::CLIENT_STATUS_CLIENTE) === self::CLIENT_STATUS_RECHAZADO;
    }

    public function hasValidRuteroCode(): bool
    {
        return $this->zones()
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->exists();
    }

    public static function defaultPassword(): string
    {
        return (string) config('auth.default_user_password', 'Tendero2026');
    }

    /**
     * Match an email regardless of how it was capitalized when stored or typed,
     * so logins are not case sensitive on any database driver.
     */
    public function scopeWhereEmailCaseInsensitive($query, ?string $email)
    {
        return $query->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $email), 'UTF-8')]);
    }

    public static function isInvalidClientEmail(?string $email): bool
    {
        if (!is_string($email) || trim($email) === '') {
            return true;
        }

        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        foreach (self::INTERNAL_EMAIL_SUFFIXES as $suffix) {
            if (str_ends_with($email, $suffix)) {
                return true;
            }
        }

        return false;
    }

    public function requiresClientEmailUpdate(): bool
    {
        if ($this->hasAnyRole(['admin', 'seller', 'supervisor'])) {
            return false;
        }

        return self::isInvalidClientEmail($this->email);
    }

    public function clientDisplayEmail(): ?string
    {
        if (self::isInvalidClientEmail($this->email)) {
            return null;
        }

        return $this->email;
    }

    public function flagDefaultPasswordIfUsed(string $plainPassword): void
    {
        if ($this->hasRole('admin') || $plainPassword !== self::defaultPassword()) {
            return;
        }

        if (!$this->must_change_password) {
            $this->forceFill(['must_change_password' => true])->save();
        }
    }
}
