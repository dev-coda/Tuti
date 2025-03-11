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
        return [
            $user->name,
            $user->document,
            $user->email,
            $isActive ? 'Activo' : 'Inactivo',
        ];
    }

    public function headings(): array
    {
        return [
            'Nombre',
            'Documento',
            'Email',
            'Puede Comprar',
        ];
    }
}
