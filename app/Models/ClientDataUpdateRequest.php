<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDataUpdateRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'zone_id',
        'submitted_by',
        'document',
        'name',
        'business_name',
        'email',
        'phone',
        'mobile_phone',
        'whatsapp',
        'address',
        'city_name',
        'zone_code',
        'route',
        'day',
        'seller_notes',
        'previous_data',
        'read_at',
    ];

    protected $casts = [
        'previous_data' => 'array',
        'read_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public const FIELD_LABELS = [
        'document' => 'Cédula o NIT',
        'name' => 'Razón social / nombre',
        'business_name' => 'Nombre del negocio',
        'email' => 'Correo',
        'phone' => 'Teléfono',
        'mobile_phone' => 'Celular',
        'whatsapp' => 'WhatsApp',
        'address' => 'Dirección',
        'city_name' => 'Ciudad',
        'zone_code' => 'Zona',
        'route' => 'Ruta',
        'day' => 'Día de visita',
    ];

    /**
     * Only the fields whose requested value differs from the previously
     * registered data, so notifications can highlight what actually changed.
     *
     * @return array<string, array{label: string, old: ?string, new: ?string}>
     */
    public function changedFields(): array
    {
        $previous = $this->previous_data ?? [];
        $changes = [];

        foreach (self::FIELD_LABELS as $field => $label) {
            $new = trim((string) ($this->{$field} ?? ''));
            $old = trim((string) ($previous[$field] ?? ''));

            if ($new !== '' && mb_strtolower($new, 'UTF-8') !== mb_strtolower($old, 'UTF-8')) {
                $changes[$field] = ['label' => $label, 'old' => $old !== '' ? $old : null, 'new' => $new];
            }
        }

        return $changes;
    }
}
