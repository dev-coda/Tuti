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

        // Parse SOAP XML to check actual prices sent
        $soapPrices = $this->parseSoapPrices($order->request);
        
        // Check if order has suspicious pricing in SOAP (below 500)
        $hasSuspiciousPricing = false;
        $suspiciousProducts = [];
        
        if (!empty($soapPrices)) {
            foreach ($soapPrices as $soapProduct) {
                if ($soapProduct['unitPrice'] < 500) {
                    $hasSuspiciousPricing = true;
                    $suspiciousProducts[] = sprintf(
                        'SKU: %s ($%s x %s)',
                        $soapProduct['sku'],
                        number_format($soapProduct['unitPrice'], 2),
                        $soapProduct['qty']
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

    /**
     * Parse SOAP XML to extract product prices
     */
    private function parseSoapPrices($soapXml): array
    {
        if (empty($soapXml)) {
            return [];
        }

        $products = [];
        
        try {
            // Load XML
            $xml = simplexml_load_string($soapXml);
            
            if ($xml === false) {
                return [];
            }

            // Register namespaces
            $xml->registerXPathNamespace('dyn', 'http://schemas.datacontract.org/2004/07/Dynamics.AX.Application');
            
            // Extract all listDetails elements
            $listDetails = $xml->xpath('//dyn:listDetails');
            
            if (!$listDetails) {
                return [];
            }

            foreach ($listDetails as $detail) {
                $detail->registerXPathNamespace('dyn', 'http://schemas.datacontract.org/2004/07/Dynamics.AX.Application');
                
                $sku = (string)$detail->xpath('dyn:itemId')[0] ?? '';
                $unitPrice = (float)($detail->xpath('dyn:unitPrice')[0] ?? 0);
                $qty = (float)($detail->xpath('dyn:qty')[0] ?? 0);
                $discount = (float)($detail->xpath('dyn:discount')[0] ?? 0);
                
                $products[] = [
                    'sku' => $sku,
                    'unitPrice' => $unitPrice,
                    'qty' => $qty,
                    'discount' => $discount,
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Error parsing SOAP XML for prices', [
                'error' => $e->getMessage(),
            ]);
        }

        return $products;
    }
}
