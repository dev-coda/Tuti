<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Services\MailingService;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Contact extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::created(function ($contact) {
            // Always send in-process. Interesado alerts are rare and must not depend on
            // Horizon/workers listening to the emails queue.
            $contactId = $contact->id;

            $send = function () use ($contactId) {
                try {
                    $fresh = static::query()->find($contactId);
                    if (!$fresh) {
                        return;
                    }

                    app(MailingService::class)->sendContactFormNotification($fresh);
                } catch (\Throwable $e) {
                    \Log::error("Failed to send contact form email for contact {$contactId}: " . $e->getMessage(), [
                        'contact_id' => $contactId,
                        'exception' => $e,
                    ]);
                }
            };

            // Prefer afterResponse during HTTP requests so the form can return first.
            // In console/tests (no response cycle) send immediately.
            if (! app()->runningInConsole()) {
                dispatch($send)->afterResponse();

                return;
            }

            $send();
        });
    }

    protected $fillable = [
        'name', 'email', 'phone', 'business_name', 'read', 'city', 'city_id',
        'nit', 'terms_accepted', 'address', 'person_type', 'department', 'status', 'documents',
        'new_client_mode', 'new_client_payload', 'external_client_id', 'external_client_code', 'external_submitted_at',
    ];

    protected $casts = [
        'terms_accepted' => 'boolean',
        'read' => 'boolean',
        'documents' => 'array',
        'new_client_payload' => 'array',
        'external_submitted_at' => 'datetime',
    ];

    protected $appends = ['state', 'workflow_status_label', 'workflow_status_color', 'transmit_status_label', 'transmit_status_color'];

    public const STATUSES = [
        'interesado'     => 'Interesado',
        'en_validacion'  => 'En validación',
        'creado'         => 'Creado',
        'contactado'     => 'Contactado',
    ];

    public const STATUS_COLORS = [
        'interesado'     => 'bg-yellow-100 text-yellow-800',
        'en_validacion'  => 'bg-blue-100 text-blue-800',
        'creado'         => 'bg-green-100 text-green-800',
        'contactado'     => 'bg-purple-100 text-purple-800',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Resolve a displayable city name.
     *
     * contacts.city is a legacy string column that shadows the city() relation,
     * so $contact->city may be a string (Cliente Nuevo) or null with city_id set
     * (legacy /formulario). Never call $contact->city->name directly.
     */
    public function cityName(): string
    {
        $raw = $this->getAttributes()['city'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            return trim($raw);
        }

        if ($this->city_id) {
            $relatedName = City::query()->whereKey($this->city_id)->value('name');

            if (is_string($relatedName) && $relatedName !== '') {
                return $relatedName;
            }
        }

        return 'No especificada';
    }

    public function clientUser()
    {
        return $this->hasOne(User::class, 'document', 'nit');
    }

    public function scopeUnRead($query)
    {
        return $query->where('read', false);
    }

    public function scopeTotalUnRead()
    {
        return $this->unRead()->count();
    }

    public static function emailExistsAsUser(string $email): bool
    {
        return User::where('email', $email)->exists();
    }

    public static function nitExists(string $nit): bool
    {
        return User::where('document', $nit)->exists();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status ?? 'interesado');
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    protected function state(): Attribute
    {
        return Attribute::get(function () {
            $existe = User::where('email', $this->email)->exists();
            return $existe ? 'Existente' : 'Nuevo';
        });
    }

    public function getWorkflowStatusLabelAttribute(): string
    {
        $status = $this->resolveClientStatus();
        return match ($status) {
            User::CLIENT_STATUS_PENDIENTE => 'Pendiente',
            User::CLIENT_STATUS_CLIENTE => 'Cliente',
            User::CLIENT_STATUS_RECHAZADO => 'Rechazado',
            default => 'Prospecto',
        };
    }

    public function getWorkflowStatusColorAttribute(): string
    {
        $status = $this->resolveClientStatus();
        return match ($status) {
            User::CLIENT_STATUS_CLIENTE => 'bg-green-100 text-green-800',
            User::CLIENT_STATUS_PENDIENTE => 'bg-amber-100 text-amber-800',
            User::CLIENT_STATUS_RECHAZADO => 'bg-red-100 text-red-800',
            default => 'bg-indigo-100 text-indigo-800',
        };
    }

    public function getTransmitStatusLabelAttribute(): string
    {
        return $this->isReadyToTransmit() ? 'OK' : 'Pendiente';
    }

    public function getTransmitStatusColorAttribute(): string
    {
        return $this->isReadyToTransmit()
            ? 'bg-green-100 text-green-800'
            : 'bg-amber-100 text-amber-800';
    }

    public function isReadyToTransmit(): bool
    {
        $status = $this->resolveClientStatus();
        if (! in_array($status, [User::CLIENT_STATUS_PENDIENTE, User::CLIENT_STATUS_CLIENTE], true)) {
            return false;
        }

        $payload = is_array($this->new_client_payload) ? $this->new_client_payload : [];
        $requiredPayloadKeys = ['Zona', 'RutaZonaVentas', 'DiaRecorrido', 'Posicion'];
        foreach ($requiredPayloadKeys as $key) {
            if (empty($payload[$key])) {
                return false;
            }
        }

        if (empty($this->nit) || empty($this->name) || empty($this->business_name) || empty($this->phone) || empty($this->address)) {
            return false;
        }

        $docs = is_array($this->documents) ? $this->documents : [];
        return count($docs) > 0;
    }

    public function resolveLinkedClient(): ?User
    {
        if ($this->relationLoaded('clientUser')) {
            $linked = $this->getRelation('clientUser');
            if ($linked) {
                return $linked;
            }
        }

        if (! empty($this->nit)) {
            $byDocument = User::where('document', $this->nit)->first();
            if ($byDocument) {
                return $byDocument;
            }
        }

        if (! empty($this->email)) {
            return User::where('email', $this->email)->first();
        }

        return null;
    }

    public function resolveClientStatus(): string
    {
        $user = $this->resolveLinkedClient();
        return $user?->client_status ?? User::CLIENT_STATUS_PROSPECTO;
    }
}
