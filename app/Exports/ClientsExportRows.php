<?php

namespace App\Exports;

use App\Models\User;

/**
 * Shared row layout for the clients export (CSV / Excel).
 *
 * Kept free of Maatwebsite interfaces so a streaming job can reuse the same
 * columns without loading PhpSpreadsheet for ~100k rows.
 */
class ClientsExportRows
{
    public static function headings(): array
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

    public static function map(User $user): array
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

    /**
     * Base query for store clients (no staff roles), with relations needed by map().
     */
    public static function clientsQuery()
    {
        return User::query()
            ->whereDoesntHave('roles')
            ->with([
                'city:id,name',
                'zones:id,user_id,zone,route,day,address,code,zip_code,dane_code,fulfillment_provider_48h',
            ]);
    }
}
