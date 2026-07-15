<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\Product;
use App\Models\ZoneWarehouse;
use App\Repositories\OrderRepository;
use App\Services\MicrosoftTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Creates FV documents (sales orders) in Dynamics 365 F&O through the
 * CreateSalesOrder SOAP webservice (DYNPRODWSSalesForceGroup).
 *
 * Documented in docs/fv.pdf ("Desarrollo Integración pedidos de venta fase 1",
 * Dynamics Pro Consulting, v1.0).
 */
class FvDynamicsService
{
    private const ENDPOINT_PATH = '/soap/services/DYNPRODWSSalesForceGroup';

    /** Placeholder Dynamics expects for empty string fields (see docs/fv.pdf request example). */
    private const EMPTY_VALUE = '.';

    public function createFv(Order $order): array
    {
        $order->loadMissing(['user', 'zone', 'seller', 'products.product']);

        $endpoint = $this->resolveEndpoint();
        $soapAction = (string) config('services.fv.soap_action');
        $body = $this->buildRequestXml($order);

        Log::channel('soap')->info('Sending FV CreateSalesOrder request', [
            'order_id' => $order->id,
            'endpoint' => $endpoint,
        ]);

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml;charset=UTF-8',
            'SOAPAction' => $soapAction,
            'Authorization' => 'Bearer ' . MicrosoftTokenService::currentOrRefresh(),
        ])
            ->timeout(30)
            ->connectTimeout(5)
            ->withOptions(['verify' => false, 'http_errors' => false])
            ->send('POST', $endpoint, ['body' => $body]);

        $responseBody = $response->body();

        if (!$response->successful()) {
            Log::channel('soap')->error('FV CreateSalesOrder HTTP error', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body_preview' => substr($responseBody, 0, 1000),
            ]);

            throw new RuntimeException(
                'FV CreateSalesOrder failed with HTTP ' . $response->status() . ': ' . substr($responseBody, 0, 300)
            );
        }

        $result = $this->parseResponse($responseBody);

        // Duplicate submissions return YA_CREADO with the previously created
        // sales order, which is a success for retry idempotency.
        $isDuplicate = str_contains(strtoupper($result['message'] ?? ''), 'YA_CREADO')
            || str_contains(strtoupper($result['document_status'] ?? ''), 'YA_CREADO');

        if (!$result['success'] && !$isDuplicate) {
            Log::channel('soap')->error('FV CreateSalesOrder rejected', [
                'order_id' => $order->id,
                'result' => $result,
            ]);

            throw new RuntimeException(
                'FV CreateSalesOrder rejected: ' . ($result['message'] ?: 'unknown error')
            );
        }

        if ($result['sales_order_number'] === '') {
            throw new RuntimeException('FV CreateSalesOrder did not return salesOrderNumber.');
        }

        Log::channel('soap')->info('FV CreateSalesOrder completed', [
            'order_id' => $order->id,
            'sales_order_number' => $result['sales_order_number'],
            'document_status' => $result['document_status'],
            'duplicate' => $isDuplicate,
        ]);

        return [
            'success' => true,
            'fv_number' => $result['sales_order_number'],
            'request_payload' => [
                'endpoint' => $endpoint,
                'soap_action' => $soapAction,
                'xml' => $body,
            ],
            'response_payload' => $result,
        ];
    }

    public function resolveEndpoint(): string
    {
        $endpoint = trim((string) config('services.fv.endpoint'));
        if ($endpoint !== '') {
            return $endpoint;
        }

        $resource = rtrim(trim((string) config('microsoft.resource')), '/');
        if ($resource === '') {
            throw new RuntimeException('FV endpoint is not configured (set FV_SOAP_ENDPOINT or MICROSOFT_RESOURCE_URL).');
        }

        return $resource . self::ENDPOINT_PATH;
    }

    /**
     * Build the CreateSalesOrder SOAP envelope. Element order follows the
     * request example in docs/fv.pdf (Dynamics AX data contracts require it).
     */
    private function buildRequestXml(Order $order): string
    {
        $user = $order->user;
        $zonePayload = $this->resolveZonePayload($order);
        $warehouse = $this->resolveWarehouse($zonePayload['zone']);

        $custId = trim((string) ($user?->account_num ?: $user?->document));
        if ($custId === '') {
            throw new RuntimeException("Order {$order->id} has no customer account (account_num/document) for FV creation.");
        }

        // "El número externo del pedido se toma de la posición 3" (docs/fv.pdf):
        // the third whitespace-separated token of observationsCust must be the
        // external order number.
        $observationsCust = sprintf(
            'Pedido Tuti %d - ConsecVta %s-%d - %s',
            $order->id,
            $zonePayload['zone'] !== '' ? $zonePayload['zone'] : '0',
            $order->id,
            ($order->created_at ?? now())->format('Y/m/d')
        );

        $observationInternal = $zonePayload['code'] !== '' ? $zonePayload['code'] : self::EMPTY_VALUE;

        $linesXml = $this->buildLinesXml($order, $warehouse);

        $fields = [
            'TRO_E_obsequio' => '0',
            'approval' => (string) config('services.fv.approval'),
            'businessUnit' => (string) config('services.fv.business_unit'),
            'costCenter' => (string) config('services.fv.cost_center'),
            'custId' => $custId,
            'dateInvoice' => now()->format('Y-m-d'),
            'deliveryMode' => (string) config('services.fv.delivery_mode'),
            'detail' => (string) $order->id,
            'docType' => (string) config('services.fv.doc_type'),
            'drive' => $this->valueOrPlaceholder(config('services.fv.drive')),
            'locationInvoice' => (string) config('services.fv.location_invoice'),
            'name' => $this->valueOrPlaceholder($user?->name),
            'numInvoice' => 'NEXT',
            'numSequenceGroup' => (string) config('services.fv.num_sequence_group'),
            'observationInternal' => $observationInternal,
            'observationsCust' => $observationsCust,
            'orderType' => (string) config('services.fv.order_type'),
            'origenventa' => (string) config('services.fv.origen_venta'),
            'phone' => $this->valueOrPlaceholder($user?->mobile_phone ?: $user?->phone),
            'resource' => $this->valueOrPlaceholder(config('services.fv.resource')),
            'salesResponsible' => $this->valueOrPlaceholder($order->seller?->document),
            'shapeDispatch' => '',
            'supervisor' => $this->valueOrPlaceholder(config('services.fv.supervisor')),
            'third' => $custId,
            'vendor' => $this->valueOrPlaceholder(config('services.fv.vendor')),
            'warehouse' => $warehouse,
        ];

        $fieldsXml = '';
        foreach ($fields as $tag => $value) {
            $fieldsXml .= '<dyn:' . $tag . '>' . $this->xmlEscape($value) . '</dyn:' . $tag . '>';
        }

        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dat="http://schemas.microsoft.com/dynamics/2013/01/datacontracts" xmlns:tem="http://tempuri.org" xmlns:dyn="http://schemas.datacontract.org/2004/07/Dynamics.AX.Application">
            <soapenv:Header>
                <dat:CallContext>
                    <dat:Company>' . $this->xmlEscape((string) config('services.fv.company')) . '</dat:Company>
                </dat:CallContext>
            </soapenv:Header>
            <soapenv:Body>
                <tem:CreateSalesOrder>
                    <tem:_request>
                        <dyn:salesOrder>
                            <dyn:LinesDetails>' . $linesXml . '</dyn:LinesDetails>
                            ' . $fieldsXml . '
                        </dyn:salesOrder>
                    </tem:_request>
                </tem:CreateSalesOrder>
            </soapenv:Body>
        </soapenv:Envelope>';
    }

    private function buildLinesXml(Order $order, string $warehouse): string
    {
        $products = $order->products;

        $productIds = $products->pluck('product_id')->all();
        $productsData = Product::whereIn('id', $productIds)->with(['brand.vendor', 'tax'])->get()->keyBy('id');

        $variationIds = $products->pluck('variation_item_id')->filter()->all();
        $variationSkus = [];
        if (!empty($variationIds)) {
            $variationSkuData = DB::table('product_item_variation')
                ->whereIn('variation_item_id', $variationIds)
                ->whereIn('product_id', $products->whereNotNull('variation_item_id')->pluck('product_id')->unique()->all())
                ->select('product_id', 'variation_item_id', 'sku')
                ->get();
            foreach ($variationSkuData as $item) {
                $variationSkus[$item->product_id . '-' . $item->variation_item_id] = $item->sku;
            }
        }

        $linesXml = '';

        foreach ($products as $orderProduct) {
            $productData = $productsData[$orderProduct->product_id] ?? null;
            if (!$productData) {
                Log::channel('soap')->warning('FV line skipped - product not found', [
                    'order_id' => $order->id,
                    'product_id' => $orderProduct->product_id,
                ]);
                continue;
            }

            $sku = $productData->sku;
            $variationKey = $orderProduct->product_id . '-' . $orderProduct->variation_item_id;
            if ($orderProduct->variation_item_id && isset($variationSkus[$variationKey])) {
                $sku = $variationSkus[$variationKey];
            }
            if (empty($sku)) {
                Log::channel('soap')->warning('FV line skipped - product without SKU', [
                    'order_id' => $order->id,
                    'product_id' => $orderProduct->product_id,
                ]);
                continue;
            }

            $pricing = OrderRepository::resolveXmlPricing($orderProduct, $productData, 0, (int) $order->id, true);
            $unitPrice = $pricing['unit_price'];
            $discountPercent = $pricing['discount_percentage'];

            $packageQty = $orderProduct->package_quantity ?? 1;
            $qty = $productData->calculate_package_price
                ? $orderProduct->quantity * $packageQty
                : $orderProduct->quantity;

            $taxRate = (float) ($productData->tax->tax ?? 0);
            $taxValue = round($qty * (float) $unitPrice * (1 - $discountPercent / 100) * $taxRate / 100, 2);

            $linesXml .= $this->buildLineXml(
                warehouse: $warehouse,
                discountPercent: (string) $discountPercent,
                itemId: $sku,
                qty: (string) $qty,
                taxValue: parseCurrency($taxValue),
                unitPrice: (string) $unitPrice
            );
        }

        $linesXml .= $this->buildShippingLineXml($order, $warehouse);

        if ($linesXml === '') {
            throw new RuntimeException("Order {$order->id} has no valid lines for FV creation.");
        }

        return $linesXml;
    }

    private function buildShippingLineXml(Order $order, string $warehouse): string
    {
        $shippingAmount = (float) ($order->shipping_quote_amount ?? 0);
        if ($shippingAmount <= 0) {
            return '';
        }

        return $this->buildLineXml(
            warehouse: $warehouse,
            discountPercent: '0',
            itemId: 'FL0001',
            qty: '1',
            taxValue: '0',
            unitPrice: parseCurrency($shippingAmount)
        );
    }

    private function buildLineXml(
        string $warehouse,
        string $discountPercent,
        string $itemId,
        string $qty,
        string $taxValue,
        string $unitPrice
    ): string {
        return '<dyn:LinesDetails>
                            <dyn:almacen>' . $this->xmlEscape($warehouse) . '</dyn:almacen>
                            <dyn:discountPercent>' . $discountPercent . '</dyn:discountPercent>
                            <dyn:itemId>' . $this->xmlEscape($itemId) . '</dyn:itemId>
                            <dyn:lote>' . self::EMPTY_VALUE . '</dyn:lote>
                            <dyn:qty>' . $qty . '</dyn:qty>
                            <dyn:serial>' . self::EMPTY_VALUE . '</dyn:serial>
                            <dyn:taxCode>' . self::EMPTY_VALUE . '</dyn:taxCode>
                            <dyn:taxGroup>' . self::EMPTY_VALUE . '</dyn:taxGroup>
                            <dyn:taxValue>' . $taxValue . '</dyn:taxValue>
                            <dyn:unitPrice>' . $unitPrice . '</dyn:unitPrice>
                        </dyn:LinesDetails>';
    }

    /**
     * @return array{code: string, zone: string}
     */
    private function resolveZonePayload(Order $order): array
    {
        $snapshot = is_array($order->zone_snapshot) ? $order->zone_snapshot : [];

        $code = trim((string) ($snapshot['code'] ?? ''));
        $zone = trim((string) ($snapshot['zone'] ?? ''));

        if ($code === '') {
            $code = trim((string) ($order->zone?->code ?? ''));
        }
        if ($zone === '') {
            $zone = trim((string) ($order->zone?->zone ?? ''));
        }

        return ['code' => $code, 'zone' => $zone];
    }

    private function resolveWarehouse(string $zoneNumber): string
    {
        $warehouse = $zoneNumber !== '' ? ZoneWarehouse::getBodegaForZone($zoneNumber) : null;
        $warehouse = $warehouse ?: trim((string) config('services.fv.default_warehouse'));

        if ($warehouse === '') {
            throw new RuntimeException(
                "No warehouse mapping found for zone '{$zoneNumber}' and FV_DEFAULT_WAREHOUSE is not set."
            );
        }

        return $warehouse;
    }

    /**
     * @return array{success: bool, sales_order_number: string, document_status: string, released_status: string, sales_status: string, message: string, audit_id: string, warehouse_wms: bool, timestamp: string, raw: string}
     */
    private function parseResponse(string $responseBody): array
    {
        $xml = @simplexml_load_string($responseBody);
        if ($xml === false) {
            throw new RuntimeException('FV CreateSalesOrder returned invalid XML: ' . substr($responseBody, 0, 300));
        }

        $xml->registerXPathNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('t', 'http://tempuri.org');
        $xml->registerXPathNamespace('a', 'http://schemas.datacontract.org/2004/07/Dynamics.AX.Application');

        $value = function (string $field) use ($xml): string {
            $nodes = $xml->xpath('//a:' . $field);
            return $nodes ? trim((string) $nodes[0]) : '';
        };

        return [
            'success' => strtolower($value('success')) === 'true',
            'sales_order_number' => $value('salesOrderNumber'),
            'document_status' => $value('documentStatus'),
            'released_status' => $value('releasedStatus'),
            'sales_status' => $value('salesStatus'),
            'message' => $value('message'),
            'audit_id' => $value('auditId'),
            'warehouse_wms' => strtolower($value('warehouseWMS')) === 'true',
            'timestamp' => $value('timestamp'),
            'raw' => $responseBody,
        ];
    }

    private function valueOrPlaceholder($value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : self::EMPTY_VALUE;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
