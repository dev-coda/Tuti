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
