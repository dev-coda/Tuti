<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ClientOrdersExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $orders) {}

    public function collection(): Collection
    {
        return $this->orders;
    }

    public function headings(): array
    {
        return [
            'ID Pedido',
            'Fecha',
            'Cliente',
            'Estado',
            'Total',
            'Descuento',
            'Articulos',
        ];
    }

    public function map($order): array
    {
        $statusMap = [
            Order::STATUS_PENDING => 'Pendiente',
            Order::STATUS_PROCESSED => 'Procesado',
            Order::STATUS_ERROR => 'Error',
            Order::STATUS_ERROR_WEBSERVICE => 'Error WS',
            Order::STATUS_SHIPPED => 'Enviado',
            Order::STATUS_DELIVERED => 'Entregado',
        ];

        return [
            $order->id,
            optional($order->created_at)->format('Y-m-d H:i:s'),
            $order->user->name ?? '',
            $statusMap[$order->status_id] ?? (string) $order->status_id,
            (float) $order->total,
            (float) $order->discount,
            (int) ($order->products_sum_quantity ?? 0),
        ];
    }
}
