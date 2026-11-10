<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ZoneWarehouse;
use App\Repositories\OrderRepository;
use App\Services\MicrosoftTokenService;
use Illuminate\Support\Collection;
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
        // FV often lives on a different Dynamics host than Tronex SOAP
        // (e.g. uattrx.sandbox vs a separate FV host). Mint a token whose audience
        // matches the FV endpoint or CreateSalesOrder returns opaque 500s.
        $tokenResource = $this->resolveTokenResource($endpoint);
        $token = MicrosoftTokenService::currentOrRefresh($tokenResource);

        Log::channel('soap')->info('Sending FV CreateSalesOrder request', [
            'order_id' => $order->id,
            'endpoint' => $endpoint,
            'token_resource' => $tokenResource,
        ]);

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml;charset=UTF-8',
            'SOAPAction' => $soapAction,
            'Authorization' => 'Bearer ' . $token,
        ])
            ->timeout(90)
            ->connectTimeout(10)
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
     * Azure AD resource (audience) for the FV host. Prefer explicit
     * FV_TOKEN_RESOURCE; otherwise derive from the SOAP endpoint host.
     */
    public function resolveTokenResource(?string $endpoint = null): string
    {
        $configured = trim((string) config('services.fv.token_resource'));
        if ($configured !== '') {
            return MicrosoftTokenService::normalizeResource($configured);
        }

        return MicrosoftTokenService::normalizeResource($endpoint ?: $this->resolveEndpoint());
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
        // external order number (alphanumeric consecutive, e.g. TEST123 in stage).
        $soapConsecutive = $order->soapConsecutive();
        $observationsCust = sprintf(
            'Pedido Tuti %s - ConsecVta %s-%s - %s',
            $soapConsecutive,
            $zonePayload['zone'] !== '' ? $zonePayload['zone'] : '0',
            $soapConsecutive,
            ($order->created_at ?? now())->format('Y/m/d')
        );

        $observationInternal = $zonePayload['code'] !== '' ? $zonePayload['code'] : self::EMPTY_VALUE;

        $linesXml = $this->buildLinesXml($order, $warehouse);

        // Element names and order match the Dynamics CreateSalesOrder data contract.
        // transportadora sits after third (before vendor). Order/user data wins
        // when present; otherwise the contract placeholders (., 9999, NEXT, etc.).
        // numInvoice stays NEXT. SOAP_ORDER_CONSECUTIVE_PREFIX applies to detail
        // and the third token of observationsCust, not to numInvoice.
        // TRO_E_obsequio stays 0. This invoice never opens a separate obsequio
        // document. When the order's city allows bonificaciones, those units are
        // merged onto the sales lines and the approved merchandise net is prorated.
        // The presales integration still sends gifts as a separate document with flag 1.
        $fields = [
            'TRO_E_obsequio' => '0',
            'approval' => (string) config('services.fv.approval'),
            'businessUnit' => (string) config('services.fv.business_unit'),
            'costCenter' => (string) config('services.fv.cost_center'),
            'custId' => $custId,
            'dateInvoice' => ($order->created_at ?? now())->format('Y-m-d'),
            'deliveryMode' => (string) config('services.fv.delivery_mode'),
            'detail' => $soapConsecutive,
            'docType' => (string) config('services.fv.doc_type'),
            'drive' => $this->valueOrDefault(config('services.fv.drive'), '9000'),
            'locationInvoice' => (string) config('services.fv.location_invoice'),
            'name' => $this->valueOrPlaceholder($user?->name),
            'numInvoice' => 'NEXT',
            'numSequenceGroup' => (string) config('services.fv.num_sequence_group'),
            // observationInternal = ID Rutero (zone sucursal code) per docs/fv.pdf.
            'observationInternal' => $observationInternal,
            'observationsCust' => $observationsCust,
            'orderType' => (string) config('services.fv.order_type'),
            'origenventa' => (string) config('services.fv.origen_venta'),
            'phone' => $this->valueOrPlaceholder($user?->mobile_phone ?: $user?->phone),
            'resource' => $this->valueOrDefault(config('services.fv.resource'), '9999'),
            'salesResponsible' => $this->valueOrDefault(
                $order->seller?->document,
                (string) config('services.fv.sales_responsible', '9999')
            ),
            'shapeDispatch' => '',
            'supervisor' => $this->valueOrDefault(config('services.fv.supervisor'), '9999'),
            'third' => $custId,
            'transportadora' => $this->resolveTransportadora($order),
            'vendor' => $this->valueOrDefault(config('services.fv.vendor'), '9999'),
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
                    <dat:Language></dat:Language>
                    <dat:MessageId></dat:MessageId>
                    <dat:PartitionKey></dat:PartitionKey>
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
        $order->loadMissing('bonifications');
        $bonificationRows = $order->bonifications;
        $includeBonifications = $bonificationRows->isNotEmpty()
            && Setting::isFvBonificacionesEnabledForOrder($order);

        if ($bonificationRows->isNotEmpty()) {
            Log::channel('soap')->info('FV bonificaciones city gate', [
                'order_id' => $order->id,
                'city_key' => Setting::fvCityKeyForOrder($order),
                'included' => $includeBonifications,
            ]);
        }

        $lineSources = $includeBonifications ? $products->concat($bonificationRows) : $products;
        $productIds = $lineSources->pluck('product_id')->filter()->unique()->all();
        $productsData = Product::whereIn('id', $productIds)->with(['brand.vendor', 'tax'])->get()->keyBy('id');

        $variationIds = $lineSources->pluck('variation_item_id')->filter()->all();
        $variationSkus = [];
        $variationPrices = [];
        if (! empty($variationIds)) {
            $variationSkuData = DB::table('product_item_variation')
                ->whereIn('variation_item_id', $variationIds)
                ->whereIn('product_id', $lineSources->whereNotNull('variation_item_id')->pluck('product_id')->unique()->all())
                ->select('product_id', 'variation_item_id', 'sku', 'price')
                ->get();
            foreach ($variationSkuData as $item) {
                $variationKey = $item->product_id.'-'.$item->variation_item_id;
                $variationSkus[$variationKey] = $item->sku;
                $variationPrices[$variationKey] = (float) $item->price;
            }
        }

        if ($includeBonifications) {
            $proratedXml = $this->buildProratedProductLinesXml($order, $warehouse, $products, $productsData, $variationSkus, $variationPrices);
        } else {
            $proratedXml = null;
        }

        $linesXml = $proratedXml ?? $this->buildStandardPaidLinesXml($order, $warehouse, $products, $productsData, $variationSkus);
        $linesXml .= $this->buildShippingLineXml($order, $warehouse);

        if ($linesXml === '') {
            throw new RuntimeException("Order {$order->id} has no valid lines for FV creation.");
        }

        return $linesXml;
    }

    /**
     * Paid lines only, with the discount and unit price the presale already stored.
     * Used when the city does not include bonificaciones, and when a city is on
     * but every gift row was skipped (missing product or SKU).
     *
     * @param  Collection<int, mixed>  $products
     * @param  Collection<int, Product>  $productsData
     * @param  array<string, string>  $variationSkus
     */
    private function buildStandardPaidLinesXml(Order $order, string $warehouse, $products, $productsData, array $variationSkus): string
    {
        $linesXml = '';

        foreach ($products as $orderProduct) {
            $productData = $productsData[$orderProduct->product_id] ?? null;
            if (! $productData) {
                Log::channel('soap')->warning('FV line skipped - product not found', [
                    'order_id' => $order->id,
                    'product_id' => $orderProduct->product_id,
                ]);

                continue;
            }

            $sku = $this->resolveLineSku($orderProduct, $productData, $variationSkus);
            if ($sku === null) {
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

        return $linesXml;
    }

    /**
     * One line per SKU (paid quantity + bonificación quantity) priced so the
     * merchandise net stays the total the client approved. Returns null when
     * there is no sendable gift, so the caller keeps the standard paid lines.
     *
     * @param  Collection<int, mixed>  $products
     * @param  Collection<int, Product>  $productsData
     * @param  array<string, string>  $variationSkus
     * @param  array<string, float>  $variationPrices
     */
    private function buildProratedProductLinesXml(
        Order $order,
        string $warehouse,
        $products,
        $productsData,
        array $variationSkus,
        array $variationPrices
    ): ?string {
        $components = [];

        foreach ($products as $orderProduct) {
            $resolved = $this->resolvePricedComponent($order, $orderProduct, $productsData, $variationSkus);
            if ($resolved === null) {
                continue;
            }

            $components[] = $resolved;
        }

        $paidReferenceBySku = [];
        foreach ($components as $component) {
            if ($component['reference_unit_cents'] > 0 && ! isset($paidReferenceBySku[$component['sku']])) {
                $paidReferenceBySku[$component['sku']] = $component['reference_unit_cents'];
            }
        }

        $sawGift = false;
        foreach ($order->bonifications as $gift) {
            $productData = $productsData[$gift->product_id] ?? null;
            if (! $productData) {
                Log::channel('soap')->warning('FV bonificación skipped - product not found', [
                    'order_id' => $order->id,
                    'product_id' => $gift->product_id,
                ]);

                continue;
            }

            $sku = $this->resolveLineSku($gift, $productData, $variationSkus);
            if ($sku === null) {
                Log::channel('soap')->warning('FV bonificación skipped - product without SKU', [
                    'order_id' => $order->id,
                    'product_id' => $gift->product_id,
                ]);

                continue;
            }

            $qty = (float) $gift->quantity;
            if ($qty == 0.0) {
                continue;
            }

            $sawGift = true;
            $wholeQty = $this->requireWholeQuantity($order, $qty, $sku);
            $catalogCents = $this->bonificationCatalogUnitCents($productData, $gift->variation_item_id ? (int) $gift->variation_item_id : null, $variationPrices);
            $referenceCents = FvLineProration::resolveReferenceUnitCents($catalogCents, $paidReferenceBySku[$sku] ?? null);
            if ($referenceCents <= 0) {
                $this->refuseProration(
                    $order,
                    "FV proration refused: SKU {$sku} has no usable reference price."
                );
            }

            if ($catalogCents <= 0) {
                Log::channel('soap')->info('FV bonificación reference price fell back to the paid line for the same SKU', [
                    'order_id' => $order->id,
                    'sku' => $sku,
                    'reference_unit_cents' => $referenceCents,
                ]);
            }

            $components[] = [
                'sku' => $sku,
                'qty' => $wholeQty,
                'reference_unit_cents' => $referenceCents,
                'tax_rate' => (float) ($productData->tax->tax ?? 0),
                'net_cents' => 0,
            ];
        }

        if (! $sawGift) {
            return null;
        }

        $approvedCents = 0;
        $merged = [];
        $skuOrder = [];

        foreach ($components as $component) {
            $sku = $component['sku'];
            if (! isset($merged[$sku])) {
                $skuOrder[] = $sku;
                $merged[$sku] = [
                    'sku' => $sku,
                    'qty' => 0,
                    'weight_cents' => 0,
                    'tax_rate' => $component['tax_rate'],
                ];
            } elseif (abs($merged[$sku]['tax_rate'] - $component['tax_rate']) > 0.0001) {
                $this->refuseProration(
                    $order,
                    "FV proration refused: SKU {$sku} has conflicting tax rates."
                );
            }

            if ($component['reference_unit_cents'] <= 0) {
                $this->refuseProration(
                    $order,
                    "FV proration refused: SKU {$sku} has no usable reference price."
                );
            }

            $merged[$sku]['qty'] += $component['qty'];
            $merged[$sku]['weight_cents'] += $component['reference_unit_cents'] * $component['qty'];
            $approvedCents += $component['net_cents'];
        }

        $lines = [];
        foreach ($skuOrder as $sku) {
            $row = $merged[$sku];
            $cap = intdiv($row['weight_cents'], $row['qty']);
            if ($cap < 1) {
                $this->refuseProration(
                    $order,
                    "FV proration refused: SKU {$sku} reference unit price rounds below 0.01."
                );
            }

            $lines[] = [
                'sku' => $sku,
                'qty' => $row['qty'],
                'weight_cents' => $row['weight_cents'],
                'cap_unit_cents' => $cap,
                'tax_rate' => $row['tax_rate'],
            ];
        }

        try {
            $allocated = FvLineProration::allocate($approvedCents, $lines);
        } catch (RuntimeException $e) {
            $this->refuseProration($order, $e->getMessage());
        }

        Log::channel('soap')->info('FV bonificaciones prorated into sales lines', [
            'order_id' => $order->id,
            'approved_merchandise' => number_format($approvedCents / 100, 2, '.', ''),
            'lines' => array_map(static function (array $line): array {
                return [
                    'sku' => $line['sku'],
                    'qty' => $line['qty'],
                    'unit_price' => number_format($line['unit_cents'] / 100, 2, '.', ''),
                ];
            }, $allocated),
        ]);

        $linesXml = '';
        foreach ($allocated as $line) {
            $linesXml .= $this->buildLineXml(
                warehouse: $warehouse,
                discountPercent: '0',
                itemId: $line['sku'],
                qty: (string) $line['qty'],
                taxValue: $line['tax_value'],
                unitPrice: parseCurrency($line['unit_cents'] / 100)
            );
        }

        return $linesXml;
    }

    /**
     * @param  Collection<int, Product>  $productsData
     * @param  array<string, string>  $variationSkus
     * @return array{sku: string, qty: int, reference_unit_cents: int, tax_rate: float, net_cents: int}|null
     */
    private function resolvePricedComponent(Order $order, object $orderProduct, $productsData, array $variationSkus): ?array
    {
        $productData = $productsData[$orderProduct->product_id] ?? null;
        if (! $productData) {
            Log::channel('soap')->warning('FV line skipped - product not found', [
                'order_id' => $order->id,
                'product_id' => $orderProduct->product_id,
            ]);

            return null;
        }

        $sku = $this->resolveLineSku($orderProduct, $productData, $variationSkus);
        if ($sku === null) {
            Log::channel('soap')->warning('FV line skipped - product without SKU', [
                'order_id' => $order->id,
                'product_id' => $orderProduct->product_id,
            ]);

            return null;
        }

        $pricing = OrderRepository::resolveXmlPricing($orderProduct, $productData, 0, (int) $order->id, true);
        $unitCents = (int) round(((float) $pricing['unit_price']) * 100);
        $packageQty = $orderProduct->package_quantity ?? 1;
        $rawQty = $productData->calculate_package_price
            ? (float) $orderProduct->quantity * (float) $packageQty
            : (float) $orderProduct->quantity;

        if ($rawQty == 0.0) {
            return null;
        }

        $qty = $this->requireWholeQuantity($order, $rawQty, $sku);
        $discount = (int) $pricing['discount_percentage'];

        return [
            'sku' => $sku,
            'qty' => $qty,
            'reference_unit_cents' => $unitCents,
            'tax_rate' => (float) ($productData->tax->tax ?? 0),
            'net_cents' => FvLineProration::paidLineNetCents($qty, $unitCents, $discount),
        ];
    }

    /**
     * @param  array<string, float>  $variationPrices
     */
    private function bonificationCatalogUnitCents(Product $product, ?int $variationItemId, array $variationPrices): int
    {
        $price = null;
        $variationKey = $product->id.'-'.$variationItemId;
        if ($variationItemId && isset($variationPrices[$variationKey]) && $variationPrices[$variationKey] > 0) {
            $price = $variationPrices[$variationKey];
        } elseif ((float) $product->price > 0) {
            $price = (float) $product->price;
        }

        if ($price === null) {
            return 0;
        }

        return FvLineProration::referenceUnitCents(
            $price,
            (bool) $product->calculate_package_price,
            (int) ($product->package_quantity ?: 1)
        );
    }

    private function requireWholeQuantity(Order $order, float $qty, string $sku): int
    {
        if ($qty <= 0 || abs($qty - round($qty)) > 0.0000001) {
            $this->refuseProration(
                $order,
                "FV proration refused: SKU {$sku} quantity must be a positive whole number."
            );
        }

        return (int) round($qty);
    }

    private function refuseProration(Order $order, string $reason): never
    {
        Log::channel('soap')->error('FV bonificacion proration refused', [
            'order_id' => $order->id,
            'reason' => $reason,
        ]);

        throw new RuntimeException('Order '.$order->id.': '.$reason);
    }

    /**
     * @param  object  $line  Order product or bonificación row (product_id, variation_item_id)
     * @param  array<string, string>  $variationSkus
     */
    private function resolveLineSku(object $line, Product $productData, array $variationSkus): ?string
    {
        $sku = $productData->sku;
        $variationKey = $line->product_id . '-' . $line->variation_item_id;
        if ($line->variation_item_id && isset($variationSkus[$variationKey])) {
            $sku = $variationSkus[$variationKey];
        }

        $sku = trim((string) $sku);

        return $sku !== '' ? $sku : null;
    }

    private function buildShippingLineXml(Order $order, string $warehouse): string
    {
        $shippingAmount = (float) ($order->shipping_quote_amount ?? 0);
        if ($shippingAmount <= 0) {
            return '';
        }

        // FV flete line SKU (default FL00001). Empty FV_SHIPPING_ITEM_ID
        // omits the line (shipping still stored on the Tuti order / Coordinadora quote).
        $itemId = trim((string) config('services.fv.shipping_item_id', 'FL00001'));
        if ($itemId === '') {
            Log::channel('soap')->warning('FV shipping line omitted — FV_SHIPPING_ITEM_ID is empty', [
                'order_id' => $order->id,
                'shipping_quote_amount' => $shippingAmount,
            ]);

            return '';
        }

        return $this->buildLineXml(
            warehouse: $warehouse,
            discountPercent: '0',
            itemId: $itemId,
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

    /**
     * COORDINADORA only when this order is actually shipped by that carrier.
     * Any other provider leaves the element present but empty.
     */
    private function resolveTransportadora(Order $order): string
    {
        if ($order->shipping_provider !== Order::SHIPPING_PROVIDER_COORDINADORA) {
            return '';
        }

        return $this->valueOrDefault(config('services.fv.transportadora'), 'COORDINADORA');
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

    /**
     * Prefer a real value; fall back to the Dynamics demo default when blank.
     * Do not use "." for financial dimensions — Dynamics rejects it.
     */
    private function valueOrDefault($value, string $default): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $default;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
