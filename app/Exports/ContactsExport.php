<?php

namespace App\Exports;

use App\Models\Contact;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ContactsExport implements FromQuery, WithMapping, WithHeadings
{
    protected $dateFrom;
    protected $dateTo;

    public function __construct($dateFrom = null, $dateTo = null)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function query()
    {
        return Contact::query()
            ->when($this->dateFrom, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($this->dateTo, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->orderByDesc('id');
    }

    public function map($contact): array
    {
        return [
            $contact->id,
            $contact->name,
            $contact->email,
            $contact->phone,
            $contact->business_name,
            $contact->city,
            $contact->nit,
            $contact->terms_accepted ? 'Sí' : 'No',
            $contact->read ? 'Sí' : 'No',
            $contact->state,
            $contact->created_at ? $contact->created_at->format('Y-m-d H:i:s') : '',
            $contact->updated_at ? $contact->updated_at->format('Y-m-d H:i:s') : '',
        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nombre',
            'Email',
            'Celular',
            'Tienda',
            'Ciudad',
            'Nit/Cédula',
            'Términos Aceptados',
            'Leído',
            'Estado',
            'Fecha de Creación',
            'Última Actualización',
        ];
    }
}
