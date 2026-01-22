<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\Exportable;

class OrdersDailyAuditExport implements FromQuery, WithMapping, WithHeadings, WithChunkReading, WithBatchInserts
{
    use Exportable;

    private $date;

    public function __construct(string $date = null)
    {
        // Default to yesterday if no date provided
        $this->date = $date ?? now()->subDay()->format('Y-m-d');
    }

    public function query()
    {
        return Order::query()
            ->with(['products.product', 'user', 'seller', 'zone'])
            ->whereDate('created_at', $this->date)
            ->orderBy('id', 'asc');
    }

    public function map($order): array
    {
        // Check if order has products with package_quantity
        $hasPackageQuantity = $order->products->contains(function ($product) {
            return !empty($product->package_quantity) && $product->package_quantity > 0;
        });

        // Check if order has bonification products
        $hasBonification = $order->products->contains(function ($product) {
            return $product->is_bonification == 1;
        });

        // Check if order has suspicious pricing (below 500)
        $hasSuspiciousPricing = $order->products->contains(function ($product) {
            return $product->price < 500;
        });

        // Get details about suspicious products if any
        $suspiciousProducts = [];
        if ($hasSuspiciousPricing) {
            foreach ($order->products as $product) {
                if ($product->price < 500) {
                    $suspiciousProducts[] = sprintf(
                        '%s ($%s)',
                        $product->product->name ?? 'N/A',
                        number_format($product->price, 2)
                    );
                }
            }
        }

        return [
            $order->id,
            $order->created_at->format('Y-m-d H:i:s'),
            $order->user->name ?? 'N/A',
            $order->user->email ?? 'N/A',
            $this->getStatusName($order->status_id),
            $order->total,
            $order->discount,
            $order->products->count(),
            $hasPackageQuantity ? 'SÍ' : 'NO',
            $hasBonification ? 'SÍ' : 'NO',
            $hasSuspiciousPricing ? 'SÍ' : 'NO',
            $hasSuspiciousPricing ? implode('; ', $suspiciousProducts) : '',
            $order->seller?->name ?? 'N/A',
            $order->zone?->zone ?? 'N/A',
            $order->zone?->route ?? 'N/A',
        ];
    }

    public function headings(): array
    {
        return [
            'ID Pedido',
            'Fecha Creación',
            'Cliente',
            'Email Cliente',
            'Estado',
            'Total',
            'Descuento',
            'Cant. Productos',
            'Tiene Package Quantity',
            'Tiene Bonificación',
            'Precio Sospechoso (<$500)',
            'Productos Sospechosos',
            'Vendedor',
            'Zona',
            'Ruta',
        ];
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function batchSize(): int
    {
        return 100;
    }

    private function getStatusName($statusId): string
    {
        $statuses = [
            Order::STATUS_PENDING => 'Pendiente',
            Order::STATUS_PROCESSED => 'Procesado',
            Order::STATUS_SHIPPED => 'Enviado',
            Order::STATUS_DELIVERED => 'Entregado',
            Order::STATUS_CANCELLED => 'Cancelado',
            Order::STATUS_ERROR => 'Error',
            Order::STATUS_ERROR_WEBSERVICE => 'Error WebService',
            Order::STATUS_WAITING => 'En Espera',
        ];

        return $statuses[$statusId] ?? 'Desconocido';
    }
}
