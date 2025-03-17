<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromQuery, WithMapping, WithHeadings
{
    public function query()
    {
        return User::query();
    }

    public function map($user): array
    {
        $isActive = $user->status_id == User::ACTIVE;
        $routes = $user->zones->map(function ($zone) {
            return $zone->id;
        });
        return [
            $user->name,
            $user->document,
            $user->email,
            $user->zone,
            $user->$routes,
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
}
