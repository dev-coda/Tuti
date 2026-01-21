<?php

namespace App\Exports;

use App\Models\Order;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Facades\DB;

class DailySalesExport implements WithMultipleSheets
{
    protected $dateFrom;
    protected $dateTo;
    protected $region;
    protected $seller;

    public function __construct($dateFrom = null, $dateTo = null, $region = null, $seller = null)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->region = $region;
        $this->seller = $seller;
    }

    public function sheets(): array
    {
        $sheets = [];
        
        // Main data sheet (BD)
        $sheets[] = new DailySalesDataSheet($this->dateFrom, $this->dateTo, $this->region, $this->seller);
        
        // Summary sheet by region
        $sheets[] = new DailySalesRegionSummarySheet($this->dateFrom, $this->dateTo, $this->region, $this->seller);
        
        // Summary sheet by seller
        $sheets[] = new DailySalesSellerSummarySheet($this->dateFrom, $this->dateTo, $this->region, $this->seller);
        
        return $sheets;
    }
}

/**
 * Main data sheet with all orders (like BD sheet)
 */
class DailySalesDataSheet implements FromQuery, WithMapping, WithHeadings, WithTitle
{
    protected $dateFrom;
    protected $dateTo;
    protected $region;
    protected $seller;

    public function __construct($dateFrom, $dateTo, $region, $seller)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->region = $region;
        $this->seller = $seller;
    }

    public function title(): string
    {
        return 'BD';
    }

    public function query()
    {
        return Order::query()
            ->with(['user', 'seller', 'zone'])
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('created_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('created_at', '<=', $this->dateTo);
            })
            ->when($this->seller, function ($query) {
                $query->where('seller_id', $this->seller);
            })
            ->where('status_id', Order::STATUS_PROCESSED)
            ->orderBy('created_at', 'desc');
    }

    public function map($order): array
    {
        $region = $this->getRegion($order->zone);
        $cliente = $order->user ? $order->user->name : 'N/A';
        $zoneCode = $order->zone ? $order->zone->zone : '';
        $vendedor = $order->seller ? "{$order->seller->name} {$zoneCode}" : 'N/A';
        $estado = 'Procesada';
        $zona = $order->zone ? $order->zone->zone : 'N/A';
        $ruta = $order->zone ? $order->zone->route : 'N/A';

        return [
            $region,
            $order->id,
            $order->created_at->format('d/m/Y'),
            $cliente,
            $vendedor,
            $estado,
            '$ ' . number_format($order->total, 2, ',', '.'),
            $zona,
            $ruta,
        ];
    }

    public function headings(): array
    {
        return [
            'REGIONAL',
            'PEDIDO TUTI',
            'FECHA Y HORA',
            'CLIENTE',
            'VENDEDOR',
            'ESTADO',
            'VALOR',
            'ZONA',
            'RUTA',
        ];
    }

    private function getRegion($zone): string
    {
        if (!$zone) return 'N/A';
        
        // Map zones to regions (simplified - adjust based on your actual zone mapping)
        $zoneCode = $zone->zone ?? '';
        
        // This is a simplified mapping - adjust based on your actual data
        if (str_starts_with($zoneCode, '1')) return 'MEDELLIN';
        if (str_starts_with($zoneCode, '4')) return 'CUCUTA';
        if (str_starts_with($zoneCode, '6')) return 'PEREIRA';
        if (str_starts_with($zoneCode, '7')) return 'BARRANQUILLA';
        if (str_starts_with($zoneCode, '5')) return 'MONTERIA';
        if (str_starts_with($zoneCode, '9')) return 'BOGOTA';
        if (str_starts_with($zoneCode, '62')) return 'CALI';
        
        return 'OTROS';
    }
}

/**
 * Summary by region sheet
 */
class DailySalesRegionSummarySheet implements FromQuery, WithMapping, WithHeadings, WithTitle
{
    protected $dateFrom;
    protected $dateTo;
    protected $region;
    protected $seller;

    public function __construct($dateFrom, $dateTo, $region, $seller)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->region = $region;
        $this->seller = $seller;
    }

    public function title(): string
    {
        return 'Resumen por Región';
    }

    public function query()
    {
        return Order::query()
            ->select(
                DB::raw('SUBSTRING(zones.zone, 1, 1) as region_code'),
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(orders.total) as total_sales'),
                DB::raw('AVG(orders.total) as avg_ticket')
            )
            ->leftJoin('zones', 'orders.zone_id', '=', 'zones.id')
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('orders.created_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('orders.created_at', '<=', $this->dateTo);
            })
            ->when($this->seller, function ($query) {
                $query->where('orders.seller_id', $this->seller);
            })
            ->where('orders.status_id', Order::STATUS_PROCESSED)
            ->groupBy('region_code')
            ->orderByDesc('total_sales');
    }

    public function map($summary): array
    {
        $region = $this->mapRegionCode($summary->region_code);
        
        return [
            $region,
            $summary->total_orders,
            '$ ' . number_format($summary->total_sales, 2, ',', '.'),
            '$ ' . number_format($summary->avg_ticket, 2, ',', '.'),
        ];
    }

    public function headings(): array
    {
        return [
            'REGIÓN',
            'TOTAL PEDIDOS',
            'VENTAS TOTALES',
            'TICKET PROMEDIO',
        ];
    }

    private function mapRegionCode($code): string
    {
        $mapping = [
            '1' => 'MEDELLIN',
            '4' => 'CUCUTA',
            '6' => 'PEREIRA',
            '7' => 'BARRANQUILLA',
            '5' => 'MONTERIA',
            '9' => 'BOGOTA',
        ];
        
        return $mapping[$code] ?? 'OTROS';
    }
}

/**
 * Summary by seller sheet
 */
class DailySalesSellerSummarySheet implements FromQuery, WithMapping, WithHeadings, WithTitle
{
    protected $dateFrom;
    protected $dateTo;
    protected $region;
    protected $seller;

    public function __construct($dateFrom, $dateTo, $region, $seller)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->region = $region;
        $this->seller = $seller;
    }

    public function title(): string
    {
        return 'Resumen por Vendedor';
    }

    public function query()
    {
        return Order::query()
            ->select(
                'users.name as seller_name',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(orders.total) as total_sales'),
                DB::raw('AVG(orders.total) as avg_ticket')
            )
            ->leftJoin('users', 'orders.seller_id', '=', 'users.id')
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('orders.created_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('orders.created_at', '<=', $this->dateTo);
            })
            ->when($this->seller, function ($query) {
                $query->where('orders.seller_id', $this->seller);
            })
            ->where('orders.status_id', Order::STATUS_PROCESSED)
            ->whereNotNull('orders.seller_id')
            ->groupBy('users.name')
            ->orderByDesc('total_sales');
    }

    public function map($summary): array
    {
        return [
            $summary->seller_name ?? 'Sin vendedor',
            $summary->total_orders,
            '$ ' . number_format($summary->total_sales, 2, ',', '.'),
            '$ ' . number_format($summary->avg_ticket, 2, ',', '.'),
        ];
    }

    public function headings(): array
    {
        return [
            'VENDEDOR',
            'TOTAL PEDIDOS',
            'VENTAS TOTALES',
            'TICKET PROMEDIO',
        ];
    }
}
