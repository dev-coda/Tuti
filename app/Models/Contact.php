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
            // Dispatch asynchronously when a real queue worker exists; otherwise send synchronously
            // so interesados notifications are not lost in environments without workers.
            $queueConnection = config('queue.default');

            if ($queueConnection === 'sync') {
                try {
                    app(MailingService::class)->sendContactFormNotification($contact->fresh(['city']));
                } catch (\Throwable $e) {
                    \Log::error("Failed to send contact form email synchronously for contact {$contact->id}: " . $e->getMessage());
                }

                return;
            }

            try {
                \App\Jobs\SendContactFormEmail::dispatch($contact)
                    ->onConnection($queueConnection)
                    ->onQueue('emails');
            } catch (\Exception $e) {
                \Log::error("Failed to dispatch contact form email for contact {$contact->id}: " . $e->getMessage());
                // Last-resort fallback to avoid losing notifications.
                try {
                    app(MailingService::class)->sendContactFormNotification($contact->fresh(['city']));
                } catch (\Throwable $inner) {
                    \Log::error("Fallback contact form email failed for contact {$contact->id}: " . $inner->getMessage());
                }
            }
        });
    }

    protected $fillable = [
        'name', 'email', 'phone', 'business_name', 'read', 'city', 'city_id',
        'nit', 'terms_accepted', 'address', 'person_type', 'department', 'status', 'documents',
    ];

    protected $casts = [
        'terms_accepted' => 'boolean',
        'read' => 'boolean',
        'documents' => 'array',
    ];

    protected $appends = ['state'];

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
}
