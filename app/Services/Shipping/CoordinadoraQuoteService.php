<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\Product;
use App\Models\Zone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client for Coordinadora's national quote API (docs/COORDINADORA/API Cotizador
 * Nacional). Colombian quotes are located by DANE codes: `origen` is the
 * configured dispatch city and `destino` is resolved from the delivery zone.
 *
 * Every failure throws CoordinadoraQuoteException with the real, classified
 * cause — never a bare generic error — so the cart can log an exact
 * explanation and only ask the shopper to update their data when their
 * delivery data is actually what is missing/invalid.
 */
class CoordinadoraQuoteService
{
    public function __construct(
        private readonly CoordinadoraAuthService $authService
    ) {
    }

    public function quoteFromCart(Collection $cart, Zone $zone): array
    {
        $items = $cart->map(function ($row) {
            $product = Product::find($row['product_id'] ?? null);
            if (!$product) {
                return null;
            }

            $quantity = (int) ($row['quantity'] ?? 0);
            if ($quantity <= 0) {
                return null;
            }

            return [
                'sku' => $product->sku,
                'quantity' => $quantity,
                'weight_kg' => (float) ($product->coordinadora_weight_kg ?? 0),
                'height_cm' => (float) ($product->coordinadora_height_cm ?? 0),
                'width_cm' => (float) ($product->coordinadora_width_cm ?? 0),
                'length_cm' => (float) ($product->coordinadora_length_cm ?? 0),
                'declared_value' => (float) $product->price * $quantity,
            ];
        })->filter()->values();

        if ($items->isEmpty() && $cart->isNotEmpty()) {
            throw new CoordinadoraQuoteException(
                'El carrito tiene productos, pero ninguno se pudo cotizar: los productos ya no existen '
                . 'en el catálogo o tienen cantidad 0. Product IDs en el carrito: '
                . $cart->pluck('product_id')->filter()->implode(', ') . '.',
                errorCode: 'no_quotable_items',
                failureCategory: 'product_data',
                diagnostics: [
                    'cart_product_ids' => $cart->pluck('product_id')->values()->all(),
                    'zone_id' => $zone->id,
                ],
                userMessage: 'No se pudo cotizar el envío: algunos productos del carrito ya no están disponibles.',
            );
        }

        return $this->quoteFromItems($items, $zone);
    }

    public function quoteFromOrder(Order $order): array
    {
        $order->loadMissing(['products.product', 'zone']);
        if (!$order->zone) {
            throw new CoordinadoraQuoteException(
                'La orden #' . $order->id . ' no tiene zona de entrega, así que no hay destino para cotizar con Coordinadora.',
                errorCode: 'order_without_zone',
                failureCategory: 'user_data',
                requiresUserDataUpdate: true,
                diagnostics: ['order_id' => $order->id],
                userMessage: 'No se pudo cotizar el envío: la orden no tiene dirección/zona de entrega.',
            );
        }

        $items = collect($order->products)->map(function ($orderProduct) {
            $product = $orderProduct->product;
            if (!$product) {
                return null;
            }

            return [
                'sku' => $product->sku,
                'quantity' => (int) $orderProduct->quantity,
                'weight_kg' => (float) ($product->coordinadora_weight_kg ?? 0),
                'height_cm' => (float) ($product->coordinadora_height_cm ?? 0),
                'width_cm' => (float) ($product->coordinadora_width_cm ?? 0),
                'length_cm' => (float) ($product->coordinadora_length_cm ?? 0),
                'declared_value' => (float) $orderProduct->price * (int) $orderProduct->quantity,
            ];
        })->filter()->values();

        return $this->quoteFromItems($items, $order->zone, (float) $order->total);
    }

    public function quoteFromItems(Collection $items, Zone $zone, ?float $declaredValue = null): array
    {
        $destino = $zone->coordinadoraDaneCode();
        if ($destino === null) {
            $owner = $zone->user;

            throw new CoordinadoraQuoteException(
                'La zona de entrega #' . $zone->id . ' ("' . (string) $zone->address . '") no tiene código DANE de destino, '
                . 'por eso Coordinadora no puede cotizar y NO se le envió ninguna solicitud. Se revisó, en orden: '
                . '(1) zones.dane_code = ' . var_export($zone->dane_code, true) . ', '
                . '(2) zones.zip_code = ' . var_export($zone->zip_code, true) . ' (solo sirve si es un DANE de 5 u 8 dígitos; '
                . 'un código postal de 6 dígitos no sirve), '
                . '(3) city_code del usuario dueño de la zona (#' . ($owner?->id ?? 'sin usuario') . ') = '
                . var_export($owner?->city_code, true) . ', '
                . '(4) catálogo DANE por ciudad/departamento del usuario = ciudad '
                . var_export($owner?->city?->name ?? $owner?->city_code, true) . ', departamento '
                . var_export($owner?->city?->state?->name, true) . ' (sin coincidencia). '
                . 'Corrección: actualizar los datos de entrega del cliente (ciudad/dirección) o cargar el dane_code de la zona.',
                errorCode: 'missing_destination_dane',
                failureCategory: 'user_data',
                requiresUserDataUpdate: true,
                diagnostics: [
                    'zone_id' => $zone->id,
                    'zone_address' => $zone->address,
                    'zone_dane_code' => $zone->dane_code,
                    'zone_zip_code' => $zone->zip_code,
                    'zone_owner_user_id' => $owner?->id,
                    'zone_owner_city_code' => $owner?->city_code,
                    'zone_owner_city' => $owner?->city?->name,
                    'zone_owner_state' => $owner?->city?->state?->name,
                ],
                userMessage: 'No se pudo cotizar el envío: tu dirección de entrega no tiene una ciudad válida para envío 48H.',
            );
        }

        $config = config('services.coordinadora');

        $origen = DaneCodeService::normalize($config['origin_dane'] ?? null);
        if ($origen === null) {
            throw new CoordinadoraQuoteException(
                'COORDINADORA_ORIGIN_DANE no está configurado o no es un código DANE válido en este ambiente '
                . '(valor actual: ' . var_export($config['origin_dane'] ?? null, true) . '; se esperan 5 u 8 dígitos, '
                . 'ej. 05001000 para Medellín). Esto es configuración del servidor (env de stage), NO un dato del usuario, '
                . 'y Coordinadora no fue contactada.',
                errorCode: 'missing_origin_dane',
                failureCategory: 'server_configuration',
                diagnostics: ['origin_dane_configured' => !empty($config['origin_dane'])],
                userMessage: 'No se pudo cotizar el envío en este momento.',
            );
        }

        if ($items->isEmpty()) {
            throw new CoordinadoraQuoteException(
                'No hay ítems para cotizar: el detalle enviado a Coordinadora quedó vacío (sin productos con cantidad > 0).',
                errorCode: 'empty_quote_items',
                failureCategory: 'cart',
                diagnostics: ['zone_id' => $zone->id, 'destino_dane' => $destino],
                userMessage: 'No se pudo cotizar el envío: el carrito está vacío.',
            );
        }

        $valoracion = $declaredValue ?? (float) $items->sum(fn (array $item) => (float) ($item['declared_value'] ?? 0));
        if ($valoracion <= 0) {
            throw new CoordinadoraQuoteException(
                'El valor declarado (valoracion) para Coordinadora es $0: la suma de precio × cantidad de los productos '
                . 'del carrito es 0, y el cotizador rechaza envíos sin valor declarado.',
                errorCode: 'invalid_declared_value',
                failureCategory: 'product_data',
                diagnostics: [
                    'zone_id' => $zone->id,
                    'item_skus' => $items->pluck('sku')->values()->all(),
                ],
                userMessage: 'No se pudo cotizar el envío: los productos del carrito no tienen precio válido.',
            );
        }

        // Products without weight/dimensions are sent as 0 and are the most
        // common reason Coordinadora rejects an otherwise valid quote. They
        // don't block the attempt (some SKUs quote fine), but they travel in
        // every failure's diagnostics so the console names them exactly.
        $incompleteItems = $items
            ->filter(fn (array $item) => (float) ($item['weight_kg'] ?? 0) <= 0
                || (float) ($item['height_cm'] ?? 0) <= 0
                || (float) ($item['width_cm'] ?? 0) <= 0
                || (float) ($item['length_cm'] ?? 0) <= 0)
            ->map(fn (array $item) => (string) ($item['sku'] ?? 'sin-sku'))
            ->values()
            ->all();

        $diagnostics = [
            'zone_id' => $zone->id,
            'origen_dane' => $origen,
            'destino_dane' => $destino,
            'valoracion' => round($valoracion, 2),
            'item_skus' => $items->pluck('sku')->values()->all(),
            'items_with_missing_weight_or_dimensions' => $incompleteItems,
        ];

        $payload = [
            'nit' => (string) ($config['nit'] ?? ''),
            'div' => (string) ($config['div'] ?? '01'),
            'cuenta' => (string) ($config['cuenta'] ?? '1'),
            'producto' => (string) ($config['producto'] ?? '0'),
            // Postal code fields are for Mexico; Colombia quotes use DANE codes.
            'codigo_postal_origen' => '',
            'codigo_postal_destino' => '',
            'origen' => $origen,
            'destino' => $destino,
            'valoracion' => round($valoracion, 2),
            'nivel_servicio' => (string) ($config['nivel_servicio'] ?? ''),
            'detalle' => $items->map(function (array $item) {
                return [
                    'ubl' => '0',
                    'alto' => (string) (float) ($item['height_cm'] ?? 0),
                    'ancho' => (string) (float) ($item['width_cm'] ?? 0),
                    'largo' => (string) (float) ($item['length_cm'] ?? 0),
                    'peso' => (string) (float) ($item['weight_kg'] ?? 0),
                    'unidades' => (string) (int) ($item['quantity'] ?? 0),
                ];
            })->values()->all(),
        ];

        $response = $this->request('/cotizador/nacional', $payload, $diagnostics);

        if ((bool) data_get($response, 'isError', false)) {
            throw new CoordinadoraQuoteException(
                'Coordinadora respondió isError=true en /cotizador/nacional. Error devuelto por Coordinadora: '
                . json_encode(data_get($response, 'error') ?? data_get($response, 'message') ?? $response, JSON_UNESCAPED_UNICODE)
                . ($incompleteItems !== []
                    ? '. ATENCIÓN: estos productos van con peso/dimensiones en 0 y son la causa probable del rechazo: '
                        . implode(', ', $incompleteItems) . '.'
                    : '. Los productos sí tienen peso y dimensiones, así que la causa está en la respuesta de Coordinadora de arriba.'),
                errorCode: 'coordinadora_business_error',
                failureCategory: 'external_service',
                diagnostics: $diagnostics + [
                    'coordinadora_error' => data_get($response, 'error') ?? data_get($response, 'message'),
                ],
                userMessage: 'No se pudo cotizar el envío en este momento.',
            );
        }

        $price = (float) (
            data_get($response, 'data.valor_envio')
            ?? data_get($response, 'data.flete_total')
            ?? 0
        );

        if ($price <= 0) {
            throw new CoordinadoraQuoteException(
                'Coordinadora respondió sin error, pero sin precio: data.valor_envio y data.flete_total llegaron vacíos o en 0. '
                . 'Respuesta recibida: ' . mb_substr(json_encode($response, JSON_UNESCAPED_UNICODE) ?: '', 0, 500)
                . ($incompleteItems !== []
                    ? '. Productos con peso/dimensiones en 0 (causa probable): ' . implode(', ', $incompleteItems) . '.'
                    : ''),
                errorCode: 'coordinadora_zero_price',
                failureCategory: 'external_service',
                diagnostics: $diagnostics,
                userMessage: 'No se pudo cotizar el envío en este momento.',
            );
        }

        $deliveryDays = data_get($response, 'data.dias_entrega');

        return [
            'success' => true,
            'shipping_cost' => round($price, 2),
            'currency' => 'COP',
            'delivery_estimate' => $deliveryDays !== null ? (int) $deliveryDays : null,
            'request_payload' => $payload,
            'response_payload' => $response,
        ];
    }

    private function request(string $path, array $payload, array $diagnostics = []): array
    {
        $baseUrl = rtrim((string) config('services.coordinadora.base_url'), '/');
        if ($baseUrl === '') {
            throw new CoordinadoraQuoteException(
                'COORDINADORA_BASE_URL no está configurado en este ambiente (config/services.php → coordinadora.base_url). '
                . 'Es configuración del servidor (env de stage), NO un dato del usuario; Coordinadora no fue contactada.',
                errorCode: 'missing_base_url',
                failureCategory: 'server_configuration',
                diagnostics: $diagnostics,
                userMessage: 'No se pudo cotizar el envío en este momento.',
            );
        }

        try {
            $token = $this->authService->getAccessToken();
        } catch (RequestException $e) {
            throw new CoordinadoraQuoteException(
                'Coordinadora rechazó la autenticación OAuth (POST ' . (string) config('services.coordinadora.oauth_url') . '): '
                . 'HTTP ' . $e->response->status() . ' — ' . mb_substr($e->response->body(), 0, 500)
                . '. Revisa COORDINADORA_KEY / COORDINADORA_SECRET de este ambiente; no es un dato del usuario.',
                errorCode: 'coordinadora_auth_http_error',
                failureCategory: 'external_service',
                diagnostics: $diagnostics + ['http_status' => $e->response->status()],
                userMessage: 'No se pudo cotizar el envío en este momento.',
                previous: $e,
            );
        } catch (ConnectionException $e) {
            throw new CoordinadoraQuoteException(
                'No hubo conexión con el OAuth de Coordinadora (' . (string) config('services.coordinadora.oauth_url') . '): '
                . $e->getMessage() . '. Puede ser red/DNS/timeout del ambiente; no es un dato del usuario.',
                errorCode: 'coordinadora_auth_connection_error',
                failureCategory: 'external_service',
                diagnostics: $diagnostics,
                userMessage: 'No se pudo cotizar el envío en este momento.',
                previous: $e,
            );
        } catch (RuntimeException $e) {
            $isMissingCredentials = str_contains($e->getMessage(), 'credentials are not configured');

            throw new CoordinadoraQuoteException(
                $isMissingCredentials
                    ? 'Faltan las credenciales de Coordinadora en este ambiente: COORDINADORA_KEY y/o COORDINADORA_SECRET '
                        . '(y COORDINADORA_OAUTH_URL) no están en el env de stage. Es configuración del servidor, NO un dato del usuario.'
                    : 'La autenticación con Coordinadora falló antes de cotizar: ' . $e->getMessage(),
                errorCode: $isMissingCredentials ? 'coordinadora_credentials_not_configured' : 'coordinadora_auth_failed',
                failureCategory: $isMissingCredentials ? 'server_configuration' : 'external_service',
                diagnostics: $diagnostics,
                userMessage: 'No se pudo cotizar el envío en este momento.',
                previous: $e,
            );
        }

        try {
            $response = Http::timeout(25)
                ->acceptJson()
                ->withToken($token)
                ->post($baseUrl . $path, $payload)
                ->throw()
                ->json();
        } catch (RequestException $e) {
            throw new CoordinadoraQuoteException(
                'Coordinadora rechazó la cotización (POST ' . $baseUrl . $path . '): HTTP ' . $e->response->status()
                . ' — ' . mb_substr($e->response->body(), 0, 500)
                . (($diagnostics['items_with_missing_weight_or_dimensions'] ?? []) !== []
                    ? '. ATENCIÓN: estos productos van con peso/dimensiones en 0 y son la causa probable del rechazo: '
                        . implode(', ', $diagnostics['items_with_missing_weight_or_dimensions']) . '.'
                    : '. Origen ' . ($diagnostics['origen_dane'] ?? '?') . ' → destino ' . ($diagnostics['destino_dane'] ?? '?')
                        . ', valoración $' . number_format((float) ($diagnostics['valoracion'] ?? 0), 0, ',', '.') . '.'),
                errorCode: 'coordinadora_http_error',
                failureCategory: 'external_service',
                diagnostics: $diagnostics + [
                    'http_status' => $e->response->status(),
                    'response_body' => mb_substr($e->response->body(), 0, 500),
                ],
                userMessage: 'No se pudo cotizar el envío en este momento.',
                previous: $e,
            );
        } catch (ConnectionException $e) {
            throw new CoordinadoraQuoteException(
                'No hubo conexión con Coordinadora (POST ' . $baseUrl . $path . '): ' . $e->getMessage()
                . '. Puede ser timeout (25 s), DNS o red del ambiente; no es un dato del usuario.',
                errorCode: 'coordinadora_connection_error',
                failureCategory: 'external_service',
                diagnostics: $diagnostics,
                userMessage: 'No se pudo cotizar el envío en este momento.',
                previous: $e,
            );
        }

        return is_array($response) ? $response : [];
    }
}
