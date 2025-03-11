<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductExport implements FromQuery, WithMapping, WithHeadings
{
    public function query()
    {
        return Product::query();
    }

    public function map($product): array
    {
        return [
            $product->name,
            $product->sku,
            $product->price,
            $product->tax->name,
            $product->active ? 'Si' : 'No',
        ];
    }

    public function headings(): array
    {
        return [
            'Nombre',
            'SKU',
            'Precio',
            'Impuesto',
            'Activo',
        ];
    }
}
