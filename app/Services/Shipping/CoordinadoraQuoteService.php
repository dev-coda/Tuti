<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\Product;
use App\Models\Zone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client for Coordinadora's national quote API (docs/COORDINADORA/API Cotizador
 * Nacional). Colombian quotes are located by DANE codes: `origen` is the
 * configured dispatch city and `destino` is resolved from the delivery zone.
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
            $product = Product::find($row['product_id']);
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

        return $this->quoteFromItems($items, $zone);
    }

    public function quoteFromOrder(Order $order): array
    {
        $order->loadMissing(['products.product', 'zone']);
        if (!$order->zone) {
            throw new RuntimeException('Order has no delivery zone for Coordinadora quote.');
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
            throw new RuntimeException(
                'Zone ' . $zone->id . ' has no DANE destination code for Coordinadora quote.'
            );
        }

        $config = config('services.coordinadora');

        $origen = DaneCodeService::normalize($config['origin_dane'] ?? null);
        if ($origen === null) {
            throw new RuntimeException('Coordinadora origin DANE code is not configured.');
        }

        $valoracion = $declaredValue ?? (float) $items->sum(fn (array $item) => (float) ($item['declared_value'] ?? 0));

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

        $response = $this->request('/cotizador/nacional', $payload);

        if ((bool) data_get($response, 'isError', false)) {
            throw new RuntimeException(
                'Coordinadora quote returned an error: '
                . json_encode(data_get($response, 'error') ?? $response)
            );
        }

        $price = (float) (
            data_get($response, 'data.valor_envio')
            ?? data_get($response, 'data.flete_total')
            ?? 0
        );

        $deliveryDays = data_get($response, 'data.dias_entrega');

        return [
            'success' => $price > 0,
            'shipping_cost' => round($price, 2),
            'currency' => 'COP',
            'delivery_estimate' => $deliveryDays !== null ? (int) $deliveryDays : null,
            'request_payload' => $payload,
            'response_payload' => $response,
        ];
    }

    private function request(string $path, array $payload): array
    {
        $baseUrl = rtrim((string) config('services.coordinadora.base_url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('Coordinadora base URL is not configured.');
        }

        $response = Http::timeout(25)
            ->acceptJson()
            ->withToken($this->authService->getAccessToken())
            ->post($baseUrl . $path, $payload)
            ->throw()
            ->json();

        return is_array($response) ? $response : [];
    }
}
