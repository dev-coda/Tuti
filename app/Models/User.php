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
}
