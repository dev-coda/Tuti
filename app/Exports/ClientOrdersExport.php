<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ClientOrdersExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
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

    public function chunkSize(): int
    {
        return 500;
    }
}
