<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Exportable;

class OrdersExport implements FromQuery, WithMapping, WithHeadings, withChunkReading, withBatchInserts
{
    use Exportable;

    private $from_date;
    private $to_date;
    private $brand_id;
    private $vendor_id;

    public function __construct(string $from_date = null, string $to_date = null, $brand_id = null, $vendor_id = null)
    {
        $this->from_date = $from_date;
        $this->to_date = $to_date;
        $this->brand_id = $brand_id;
        $this->vendor_id = $vendor_id;
    }

    public function query()
    {
        $query = Order::query();
        if ($this->from_date == null || $this->to_date == null) {
            $query->whereBetween('created_at', [now()->subDays(2), now()]);
        } else {
            $query->whereBetween('created_at', [$this->from_date, $this->to_date]);
        }

        // Filter by status to match KPI section (only include processed, shipped, and delivered orders)
        $query->whereIn('status_id', [Order::STATUS_PROCESSED, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED]);

        if (!empty($this->brand_id)) {
            $brandId = (int) $this->brand_id;
            $query->whereHas('products.product', function ($q) use ($brandId) {
                $q->where('brand_id', $brandId);
            });
        }

        if (!empty($this->vendor_id)) {
            $vendorId = (int) $this->vendor_id;
            $query->whereHas('products.product.brand', function ($q) use ($vendorId) {
                $q->where('vendor_id', $vendorId);
            });
        }

        return $query;
    }

    public function map($order): array
    {
        /* $orderStatuisId = $order->status_id;
        $orderStatus = '';
        if ($orderStatuisId == Order::STATUS_PENDING) {
            $orderStatus = 'Pendiente';
        } elseif ($orderStatuisId == Order::STATUS_PROCESED) {
            $orderStatus = 'Procesado';
        } elseif ($orderStatuisId == Order::STATUS_ERROR) {
            $orderStatus = 'Error';
        } elseif ($orderStatuisId == Order::STATUS_ERROR_WEBSERVICE) {
            $orderStatus = 'Error WebService';
        }

        $orderProductArray = '';
        foreach ($order->products as $product) {
            $orderProductArray .= $product->product->name . ' x' . $product->quantity . ' - c/u $' . $product->price . ', ';
        } */


        return [
            $order->id,
            $order->created_at,
            $order->user->name,
            $order->status_id,
            $order->total,
            $order->discount,
            $order->products->count(),
            $order->seller?->name,
            $order->zone?->zone,
            $order->zone?->route,

        ];
    }

    public function headings(): array
    {
        return [
            'ID',
            'Fecha',
            'Cliente',
            'Estado',
            'Total',
            'Descuento',
            'Cantidad de Productos',
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
}
