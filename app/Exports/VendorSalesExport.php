<?php

namespace App\Exports;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderProductBonification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class VendorSalesExport implements WithMultipleSheets
{
    public function __construct(
        private readonly string $dateFrom,
        private readonly string $dateTo,
        private readonly array $vendorIds,
    ) {
    }

    public function sheets(): array
    {
        return [
            new VendorSalesLinesSheet($this->dateFrom, $this->dateTo, $this->vendorIds),
            new VendorSalesBonificationsSheet($this->dateFrom, $this->dateTo, $this->vendorIds),
            new VendorSalesFullOrdersSheet($this->dateFrom, $this->dateTo, $this->vendorIds),
            new VendorSalesSummarySheet($this->dateFrom, $this->dateTo, $this->vendorIds),
        ];
    }

    public function salesQuery(): Builder
    {
        return (new VendorSalesLinesSheet($this->dateFrom, $this->dateTo, $this->vendorIds))->query();
    }

    public function bonificationsQuery(): Builder
    {
        return (new VendorSalesBonificationsSheet($this->dateFrom, $this->dateTo, $this->vendorIds))->query();
    }

    public function summary(): Collection
    {
        return (new VendorSalesSummarySheet($this->dateFrom, $this->dateTo, $this->vendorIds))->collection();
    }

    /**
     * Orders that contain at least one paid line from the selected vendors.
     *
     * @return array<int, int>
     */
    public function qualifyingOrderIds(): array
    {
        if ($this->vendorIds === []) {
            return [];
        }

        return $this->salesQuery()
            ->reorder()
            ->select('order_products.order_id')
            ->distinct()
            ->pluck('order_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function fullOrderLines(): Collection
    {
        return (new VendorSalesFullOrdersSheet($this->dateFrom, $this->dateTo, $this->vendorIds))->collection();
    }

    public static function completedStatuses(): array
    {
        return [
            Order::STATUS_PROCESSED,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
        ];
    }

    public static function reportTimezone(): string
    {
        return (string) config('app.seller_dashboard_timezone', 'America/Bogota');
    }

    /**
     * Calendar dates are business days in Colombia, stored timestamps are UTC.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function utcWindow(string $dateFrom, string $dateTo): array
    {
        $tz = self::reportTimezone();

        return [
            Carbon::createFromFormat('Y-m-d', $dateFrom, $tz)->startOfDay()->utc(),
            Carbon::createFromFormat('Y-m-d', $dateTo, $tz)->endOfDay()->utc(),
        ];
    }

    public static function applyOrderWindow(Builder $query, string $dateFrom, string $dateTo, string $ordersTable = 'orders'): Builder
    {
        [$fromUtc, $toUtc] = self::utcWindow($dateFrom, $dateTo);

        return $query
            ->whereBetween($ordersTable.'.created_at', [$fromUtc, $toUtc])
            ->whereIn($ordersTable.'.status_id', self::completedStatuses());
    }

    /**
     * Net merchandise for a paid line before IVA, matching order line pricing.
     */
    public static function lineNet(OrderProduct $line): float
    {
        $price = (float) $line->price;
        $qty = (float) $line->quantity;
        $pkg = max(1.0, (float) ($line->package_quantity ?: 1));
        $product = $line->product;

        if (($line->discount_type ?? 'percentage') === 'fixed_amount') {
            $flat = (float) ($line->flat_discount_amount ?? 0);
            if ($product?->calculate_package_price) {
                $unitPrice = $pkg > 1 ? ($price / $pkg) : $price;

                return round(max(0, $unitPrice - $flat) * $pkg * $qty, 2);
            }

            return round(max(0, $price - $flat) * $qty * $pkg, 2);
        }

        $pct = max(0.0, min(100.0, (float) ($line->percentage ?? 0)));
        $gross = $product?->calculate_package_price
            ? ($price * $qty)
            : ($price * $qty * $pkg);

        return round($gross * (1 - ($pct / 100)), 2);
    }

    /**
     * Shipped units, matching SOAP quantity:
     * package-priced products expand by package size; others are sold as the line quantity.
     * Bonification gifts are already stored as individual units and must not be multiplied.
     */
    public static function units(OrderProduct $line): float
    {
        $qty = (float) $line->quantity;
        $pkg = max(1.0, (float) ($line->package_quantity ?: 1));

        if ($line->product?->calculate_package_price) {
            return $qty * $pkg;
        }

        return $qty;
    }

    public static function lineNetWithTax(OrderProduct $line): float
    {
        $taxPct = (float) (optional($line->product?->tax)->tax ?? 0);

        return amount_with_tax(self::lineNet($line), $taxPct);
    }

    public static function formatTimestamp($timestamp): ?string
    {
        if (! $timestamp) {
            return null;
        }

        return Carbon::parse($timestamp)->timezone(self::reportTimezone())->format('Y-m-d H:i:s');
    }
}

class VendorSalesLinesSheet implements FromQuery, WithMapping, WithHeadings, WithTitle, WithChunkReading
{
    public function __construct(
        private readonly string $dateFrom,
        private readonly string $dateTo,
        private readonly array $vendorIds,
    ) {
    }

    public function title(): string
    {
        return 'Ventas';
    }

    public function chunkSize(): int
    {
        return 200;
    }

    public function query(): Builder
    {
        $query = OrderProduct::query()
            ->with([
                'order.user',
                'order.seller',
                'order.zone',
                'product.brand.vendor',
                'product.tax',
                'product.items',
                'variationItem',
            ])
            ->where(function (Builder $q) {
                $q->where('is_bonification', false)->orWhereNull('is_bonification');
            })
            ->whereHas('product.brand', function (Builder $q) {
                $q->whereIn('vendor_id', $this->vendorIds);
            })
            ->when($this->vendorIds === [], function (Builder $q) {
                $q->whereRaw('1 = 0');
            })
            ->whereHas('order', function (Builder $q) {
                VendorSalesExport::applyOrderWindow($q, $this->dateFrom, $this->dateTo);
            });

        return $query->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Pedido',
            'Estado',
            'Cliente',
            'Documento',
            'Vendedor',
            'Zona',
            'Ruta',
            'Proveedor',
            'Marca',
            'SKU',
            'Producto',
            'Variación',
            'Tipo',
            'Cantidad',
            'Unidades',
            'Precio',
            'Descuento %',
            'Valor neto antes de IVA',
            'IVA %',
            'Valor con IVA',
        ];
    }

    public function map($line): array
    {
        $order = $line->order;
        $product = $line->product;
        $status = $this->statusName($order?->status_id);

        return [
            VendorSalesExport::formatTimestamp($order?->created_at),
            $order?->id,
            $status,
            $order?->user?->name,
            $order?->user?->document,
            $order?->seller?->name,
            $order?->zone?->zone,
            $order?->zone?->route,
            $product?->brand?->vendor?->name,
            $product?->brand?->name,
            $product?->selectedVariationSku($line->variation_item_id) ?: $product?->sku,
            $product?->name,
            $line->variationItem?->name,
            'Venta',
            (float) $line->quantity,
            VendorSalesExport::units($line),
            (float) $line->price,
            (float) ($line->percentage ?? 0),
            VendorSalesExport::lineNet($line),
            (float) (optional($product?->tax)->tax ?? 0),
            VendorSalesExport::lineNetWithTax($line),
        ];
    }

    private function statusName(?int $statusId): string
    {
        return match ($statusId) {
            Order::STATUS_PROCESSED => 'Procesado',
            Order::STATUS_SHIPPED => 'Enviado',
            Order::STATUS_DELIVERED => 'Entregado',
            default => (string) $statusId,
        };
    }
}

class VendorSalesBonificationsSheet implements FromQuery, WithMapping, WithHeadings, WithTitle, WithChunkReading
{
    public function __construct(
        private readonly string $dateFrom,
        private readonly string $dateTo,
        private readonly array $vendorIds,
    ) {
    }

    public function title(): string
    {
        return 'Bonificaciones';
    }

    public function chunkSize(): int
    {
        return 200;
    }

    public function query(): Builder
    {
        return OrderProductBonification::query()
            ->with([
                'product.brand.vendor',
                'bonification',
                'orderProduct.product',
                'order.user',
                'order.seller',
                'order.zone',
            ])
            ->whereHas('product.brand', function (Builder $q) {
                $q->whereIn('vendor_id', $this->vendorIds);
            })
            ->when($this->vendorIds === [], function (Builder $q) {
                $q->whereRaw('1 = 0');
            })
            ->whereHas('order', function (Builder $q) {
                VendorSalesExport::applyOrderWindow($q, $this->dateFrom, $this->dateTo);
            })
            ->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Pedido',
            'Estado',
            'Cliente',
            'Documento',
            'Vendedor',
            'Zona',
            'Ruta',
            'Proveedor',
            'Marca',
            'SKU',
            'Producto bonificado',
            'Tipo',
            'Cantidad',
            'Bonificación',
            'Producto que la activó',
            'SKU activador',
        ];
    }

    public function map($row): array
    {
        $order = $row->order;
        $product = $row->product;
        $trigger = $row->orderProduct?->product;

        return [
            VendorSalesExport::formatTimestamp($order?->created_at),
            $order?->id,
            match ($order?->status_id) {
                Order::STATUS_PROCESSED => 'Procesado',
                Order::STATUS_SHIPPED => 'Enviado',
                Order::STATUS_DELIVERED => 'Entregado',
                default => (string) $order?->status_id,
            },
            $order?->user?->name,
            $order?->user?->document,
            $order?->seller?->name,
            $order?->zone?->zone,
            $order?->zone?->route,
            $product?->brand?->vendor?->name,
            $product?->brand?->name,
            $product?->sku,
            $product?->name,
            'Bonificación',
            (float) $row->quantity,
            $row->bonification?->name,
            $trigger?->name,
            $trigger?->sku,
        ];
    }
}

class VendorSalesFullOrdersSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $dateFrom,
        private readonly string $dateTo,
        private readonly array $vendorIds,
    ) {
    }

    public function title(): string
    {
        return 'Pedidos completos';
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Pedido',
            'Estado',
            'Cliente',
            'Documento',
            'Vendedor',
            'Zona',
            'Ruta',
            'Proveedor',
            'Marca',
            'SKU',
            'Producto',
            'Variación',
            'Tipo',
            'Proveedor seleccionado',
            'Cantidad',
            'Unidades',
            'Precio',
            'Descuento %',
            'Valor neto antes de IVA',
            'IVA %',
            'Valor con IVA',
        ];
    }

    public function collection(): Collection
    {
        $orderIds = (new VendorSalesExport($this->dateFrom, $this->dateTo, $this->vendorIds))->qualifyingOrderIds();
        if ($orderIds === []) {
            return collect();
        }

        $selected = array_flip($this->vendorIds);

        $lines = OrderProduct::query()
            ->with([
                'order.user',
                'order.seller',
                'order.zone',
                'product.brand.vendor',
                'product.tax',
                'product.items',
                'variationItem',
            ])
            ->whereIn('order_id', $orderIds)
            ->where(function (Builder $q) {
                $q->where('is_bonification', false)->orWhereNull('is_bonification');
            })
            ->orderBy('order_id')
            ->orderBy('id')
            ->get();

        $bonifications = OrderProductBonification::query()
            ->with([
                'product.brand.vendor',
                'product.tax',
                'order.user',
                'order.seller',
                'order.zone',
                'variationItem',
            ])
            ->whereIn('order_id', $orderIds)
            ->orderBy('order_id')
            ->orderBy('id')
            ->get();

        $rows = $lines->map(function (OrderProduct $line) use ($selected) {
            $product = $line->product;
            $vendorId = $product?->brand?->vendor_id;
            $order = $line->order;

            return [
                'order_id' => (int) $line->order_id,
                'sort' => 0,
                'id' => (int) $line->id,
                'row' => [
                    VendorSalesExport::formatTimestamp($order?->created_at),
                    $order?->id,
                    $this->statusName($order?->status_id),
                    $order?->user?->name,
                    $order?->user?->document,
                    $order?->seller?->name,
                    $order?->zone?->zone,
                    $order?->zone?->route,
                    $product?->brand?->vendor?->name,
                    $product?->brand?->name,
                    $product?->selectedVariationSku($line->variation_item_id) ?: $product?->sku,
                    $product?->name,
                    $line->variationItem?->name,
                    'Venta',
                    isset($selected[(int) $vendorId]) ? 'Sí' : 'No',
                    (float) $line->quantity,
                    VendorSalesExport::units($line),
                    (float) $line->price,
                    (float) ($line->percentage ?? 0),
                    VendorSalesExport::lineNet($line),
                    (float) (optional($product?->tax)->tax ?? 0),
                    VendorSalesExport::lineNetWithTax($line),
                ],
            ];
        });

        $giftRows = $bonifications->map(function (OrderProductBonification $bonus) use ($selected) {
            $product = $bonus->product;
            $vendorId = $product?->brand?->vendor_id;
            $order = $bonus->order;

            return [
                'order_id' => (int) $bonus->order_id,
                'sort' => 1,
                'id' => (int) $bonus->id,
                'row' => [
                    VendorSalesExport::formatTimestamp($order?->created_at),
                    $order?->id,
                    $this->statusName($order?->status_id),
                    $order?->user?->name,
                    $order?->user?->document,
                    $order?->seller?->name,
                    $order?->zone?->zone,
                    $order?->zone?->route,
                    $product?->brand?->vendor?->name,
                    $product?->brand?->name,
                    $product?->sku,
                    $product?->name,
                    $bonus->variationItem?->name,
                    'Bonificación',
                    isset($selected[(int) $vendorId]) ? 'Sí' : 'No',
                    (float) $bonus->quantity,
                    (float) $bonus->quantity,
                    0,
                    0,
                    0,
                    (float) (optional($product?->tax)->tax ?? 0),
                    0,
                ],
            ];
        });

        return $rows->concat($giftRows)
            ->sortBy([
                ['order_id', 'asc'],
                ['sort', 'asc'],
                ['id', 'asc'],
            ])
            ->map(fn (array $item) => $item['row'])
            ->values();
    }

    private function statusName(?int $statusId): string
    {
        return match ($statusId) {
            Order::STATUS_PROCESSED => 'Procesado',
            Order::STATUS_SHIPPED => 'Enviado',
            Order::STATUS_DELIVERED => 'Entregado',
            default => (string) $statusId,
        };
    }
}

class VendorSalesSummarySheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $dateFrom,
        private readonly string $dateTo,
        private readonly array $vendorIds,
    ) {
    }

    public function title(): string
    {
        return 'Resumen';
    }

    public function headings(): array
    {
        return [
            'Proveedor',
            'Marca',
            'SKU',
            'Producto',
            'Unidades vendidas',
            'Valor neto antes de IVA',
            'Valor con IVA',
            'Unidades bonificadas',
        ];
    }

    public function collection(): Collection
    {
        $sales = (new VendorSalesLinesSheet($this->dateFrom, $this->dateTo, $this->vendorIds))
            ->query()
            ->get();

        $bonifications = (new VendorSalesBonificationsSheet($this->dateFrom, $this->dateTo, $this->vendorIds))
            ->query()
            ->get();

        $rows = [];

        foreach ($sales as $line) {
            $product = $line->product;
            $key = (string) $line->product_id;
            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'vendor' => $product?->brand?->vendor?->name,
                    'brand' => $product?->brand?->name,
                    'sku' => $product?->sku,
                    'name' => $product?->name,
                    'sold_units' => 0.0,
                    'net' => 0.0,
                    'net_with_tax' => 0.0,
                    'bonus_units' => 0.0,
                ];
            }
            $rows[$key]['sold_units'] += VendorSalesExport::units($line);
            $rows[$key]['net'] += VendorSalesExport::lineNet($line);
            $rows[$key]['net_with_tax'] += VendorSalesExport::lineNetWithTax($line);
        }

        foreach ($bonifications as $bonus) {
            $product = $bonus->product;
            $key = (string) $bonus->product_id;
            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'vendor' => $product?->brand?->vendor?->name,
                    'brand' => $product?->brand?->name,
                    'sku' => $product?->sku,
                    'name' => $product?->name,
                    'sold_units' => 0.0,
                    'net' => 0.0,
                    'net_with_tax' => 0.0,
                    'bonus_units' => 0.0,
                ];
            }
            $rows[$key]['bonus_units'] += (float) $bonus->quantity;
        }

        return collect($rows)
            ->sortBy([
                ['vendor', 'asc'],
                ['name', 'asc'],
            ])
            ->map(function (array $row) {
                return [
                    $row['vendor'],
                    $row['brand'],
                    $row['sku'],
                    $row['name'],
                    round($row['sold_units'], 2),
                    round($row['net'], 2),
                    round($row['net_with_tax'], 2),
                    round($row['bonus_units'], 2),
                ];
            })
            ->values();
    }
}
