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

    private $fromDate;

    public function __construct(string $fromDate = null)
    {
        // Default to yesterday if no date provided
        $this->fromDate = $fromDate ?? now()->subDay()->format('Y-m-d');
    }

    public function query()
    {
        return Order::query()
            ->with(['products.product', 'user', 'seller', 'zone'])
            ->where('created_at', '>=', $this->fromDate . ' 00:00:00')
            ->where('created_at', '<=', now())
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
                // Cast to float to ensure proper comparison
                $unitPrice = (float) $soapProduct['unitPrice'];
                
                if ($unitPrice < 500) {
                    $hasSuspiciousPricing = true;
                    $suspiciousProducts[] = sprintf(
                        'SKU: %s ($%s x %s)',
                        $soapProduct['sku'] ?: 'N/A',
                        number_format($unitPrice, 2),
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
            
            // Extract all listDetails elements - try with namespace first
            $listDetails = $xml->xpath('//dyn:listDetails');
            
            // Fallback: Try without namespace if namespaced query returns nothing
            if (!$listDetails || count($listDetails) === 0) {
                $listDetails = $xml->xpath('//listDetails');
            }
            
            if (!$listDetails || count($listDetails) === 0) {
                return [];
            }

            foreach ($listDetails as $detail) {
                // Try to get values with namespace first, then without
                $sku = '';
                $unitPrice = 0;
                $qty = 0;
                $discount = 0;
                
                // Try with namespace
                $detail->registerXPathNamespace('dyn', 'http://schemas.datacontract.org/2004/07/Dynamics.AX.Application');
                $skuNodes = $detail->xpath('dyn:itemId');
                $priceNodes = $detail->xpath('dyn:unitPrice');
                $qtyNodes = $detail->xpath('dyn:qty');
                $discountNodes = $detail->xpath('dyn:discount');
                
                // Fallback to without namespace
                if (empty($skuNodes)) $skuNodes = $detail->xpath('itemId');
                if (empty($priceNodes)) $priceNodes = $detail->xpath('unitPrice');
                if (empty($qtyNodes)) $qtyNodes = $detail->xpath('qty');
                if (empty($discountNodes)) $discountNodes = $detail->xpath('discount');
                
                $sku = (string)($skuNodes[0] ?? '');
                $unitPrice = (float)($priceNodes[0] ?? 0);
                $qty = (float)($qtyNodes[0] ?? 0);
                $discount = (float)($discountNodes[0] ?? 0);
                
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
