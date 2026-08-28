<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Queued Excel export of all store clients (users without staff roles).
 *
 * Must stay async: the full client list is too large to build in a single
 * HTTP request. Each chunk is processed by the queue worker.
 */
class UsersExport implements FromQuery, WithMapping, WithHeadings, WithChunkReading, WithBatchInserts, ShouldQueue
{
    use Exportable;

    public function query()
    {
        // Only clients (users without admin/seller roles), matching the "Clientes" listing.
        return User::query()
            ->whereDoesntHave('roles')
            ->with([
                'city:id,name',
                'zones:id,user_id,zone,route,day,address,code,zip_code,dane_code,fulfillment_provider_48h',
            ])
            ->orderBy('name');
    }

    public function map($user): array
    {
        $zones = $user->zones;
        $join = function (string $column) use ($zones): string {
            return $zones
                ->pluck($column)
                ->map(fn ($value) => trim((string) $value))
                ->filter(fn ($value) => $value !== '')
                ->unique()
                ->implode(' | ');
        };

        $zoneNumbers = $join('zone');
        if ($zoneNumbers === '' && filled($user->zone)) {
            $zoneNumbers = (string) $user->zone;
        }

        return [
            $user->id,
            $user->name,
            $user->business_name,
            $user->document,
            $user->account_num,
            $user->email,
            $user->phone,
            $user->mobile_phone,
            $user->whatsapp,
            $user->city?->name,
            $user->city_code,
            $zoneNumbers,
            $join('route'),
            $join('day'),
            $join('address'),
            $join('code'),
            $join('zip_code'),
            $join('dane_code'),
            $join('fulfillment_provider_48h'),
            $user->status_id == User::ACTIVE ? 'Activo' : 'Inactivo',
            $user->client_status,
            $user->customer_type,
            $user->customer_status,
            $user->price_group,
            $user->tax_group,
            $user->line_discount,
            $user->balance,
            $user->quota_value,
            $user->is_locked ? 'Sí' : 'No',
            $user->order_sequence,
            optional($user->rutero_synced_at)?->format('Y-m-d H:i:s'),
            optional($user->created_at)?->format('Y-m-d H:i:s'),
            optional($user->updated_at)?->format('Y-m-d H:i:s'),
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Razón Social',
            'Documento',
            'Cuenta Dynamics',
            'Email',
            'Teléfono',
            'Celular',
            'WhatsApp',
            'Ciudad',
            'Código Ciudad',
            'Zona',
            'Ruta',
            'Día Visita',
            'Dirección',
            'Código Cliente',
            'Código Postal',
            'Código DANE',
            'Proveedor 48h',
            'Puede Comprar',
            'Estado Cliente',
            'Tipo Cliente',
            'Estado Dynamics',
            'Grupo Precio',
            'Grupo Impuesto',
            'Descuento Línea',
            'Saldo',
            'Cupo',
            'Bloqueado',
            'Secuencia Pedido',
            'Última Sync Rutero',
            'Fecha Creación',
            'Última Actualización',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 500;
    }
}
