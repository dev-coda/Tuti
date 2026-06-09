<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromQuery, WithMapping, WithHeadings, WithChunkReading, ShouldQueue
{
    use Exportable;

    public function query()
    {
        // Only clients (users without admin/seller roles), matching the "Clientes" listing.
        return User::query()
            ->whereDoesntHave('roles')
            ->with('zones:id,user_id')
            ->orderBy('name');
    }

    public function map($user): array
    {
        $isActive = $user->status_id == User::ACTIVE;
        $routes = $user->zones->pluck('id')->implode(', ');

        return [
            $user->name,
            $user->document,
            $user->email,
            $user->zone,
            $routes,
            $isActive ? 'Activo' : 'Inactivo',
        ];
    }

    public function headings(): array
    {
        return [
            'Nombre',
            'Documento',
            'Email',
            'Zona',
            'Ruta',
            'Puede Comprar',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
