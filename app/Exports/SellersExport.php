<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SellersExport implements FromQuery, WithMapping, WithHeadings
{
    public function query()
    {
        return User::query()->whereRelation('roles', 'name', 'seller');
    }

    public function map($user): array
    {
        $isActive = $user->status_id == User::ACTIVE;
        return [
            $user->name,
            $user->email,
            $user->zone,
        ];
    }

    public function headings(): array
    {
        return [
            'Nombre',
            'Email',
            'Zona',
        ];
    }
}
