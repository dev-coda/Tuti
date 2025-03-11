<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrdersExport implements FromQuery, WithMapping, WithHeadings
{
    public function query()
    {
        return Order::query();
    }

    public function map($order): array
    {
        $orderStatuisId = $order->status_id;
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
        }


        return [
            $order->id,
            $order->created_at,
            $order->user->name,
            $order->$orderStatus,
            $order->total,
            $order->discount,
            $order->products->count(),
            $orderProductArray,
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
            'Productos',
        ];
    }
}
