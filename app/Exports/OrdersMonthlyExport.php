<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Exportable;
use Carbon\Carbon;

class OrdersMonthlyExport implements FromQuery, WithMapping, WithHeadings, WithChunkReading, ShouldQueue
{
    use Exportable;

    private $year;
    private $month;
    private $userId;

    public function __construct(int $year, int $month, int $userId)
    {
        $this->year = $year;
        $this->month = $month;
        $this->userId = $userId;
    }

    public function query()
    {
        $startDate = Carbon::createFromDate($this->year, $this->month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($this->year, $this->month, 1)->endOfMonth();

        return Order::query()
            ->with(['user', 'seller', 'zone', 'products.product'])
            ->whereBetween('created_at', [$startDate, $endDate])
            // Filter by status to match KPI section (only include processed, shipped, and delivered orders)
            ->whereIn('status_id', [Order::STATUS_PROCESSED, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])
            ->orderBy('created_at', 'desc');
    }

    public function map($order): array
    {
        return [
            $order->id,
            $order->created_at->format('Y-m-d H:i:s'),
            $order->user->name ?? '',
            $order->user->email ?? '',
            $order->user->document ?? '',
            $order->user->phone ?? '',
            $this->getStatusText($order->status_id),
            number_format($order->total, 2),
            number_format($order->discount, 2),
            number_format($order->total - $order->discount, 2),
            $order->products->count(),
            $order->seller?->name ?? '',
            $order->zone?->zone ?? '',
            $order->zone?->route ?? '',
            $order->delivery_date ?? '',
            $order->delivery_method ?? '',
            $this->getProductsSummary($order),
        ];
    }

    public function headings(): array
    {
        return [
            'ID Pedido',
            'Fecha',
            'Cliente',
            'Email',
            'Documento',
            'Teléfono',
            'Estado',
            'Total',
            'Descuento',
            'Total Neto',
            'Cantidad de Productos',
            'Vendedor',
            'Zona',
            'Ruta',
            'Fecha de Entrega',
            'Método de Entrega',
            'Productos',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }

    private function getStatusText($statusId): string
    {
        $statuses = [
            Order::STATUS_PENDING => 'Pendiente',
            Order::STATUS_PROCESSED => 'Procesado',
            Order::STATUS_SHIPPED => 'Enviado',
            Order::STATUS_DELIVERED => 'Entregado',
            Order::STATUS_ERROR => 'Error',
            Order::STATUS_ERROR_WEBSERVICE => 'Error WebService',
        ];

        return $statuses[$statusId] ?? 'Desconocido';
    }

    private function getProductsSummary($order): string
    {
        return $order->products->map(function ($orderProduct) {
            $productName = $orderProduct->product->name ?? 'Producto no disponible';
            $quantity = $orderProduct->quantity;
            $price = $orderProduct->price;
            return "{$productName} x{$quantity} (\${$price})";
        })->join('; ');
    }
}
