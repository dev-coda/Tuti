<?php

namespace App\Exports;

use App\Models\Contact;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ContactsExport implements FromQuery, WithMapping, WithHeadings
{
    public function query()
    {
        return Contact::query();
    }

    public function map($contact): array
    {
        return [
            $contact->name,
            $contact->email,
            $contact->phone,
            $contact->business_name,
            $contact->city,
            $contact->state,
            $contact->nit
        ];
    }

    public function headings(): array
    {
        return [
            'Nombre',
            'Email',
            'Celular',
            'Tienda',
            'Ciudad',
            'Estado',
            'Nit/Cedula'
        ];
    }
}
