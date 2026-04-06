<?php

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\Product;
use App\Models\Zone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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
            ];
        })->filter()->values();

        return $this->quoteFromItems($items, $order->zone);
    }

    public function quoteFromItems(Collection $items, Zone $zone): array
    {
        $zipCode = trim((string) $zone->zip_code);
        if ($zipCode === '') {
            throw new RuntimeException('Zone zip code is required for Coordinadora quote.');
        }

        $payload = [
            'idProceso' => config('services.coordinadora.id_proceso'),
            'zipCode' => $zipCode,
            'nivelServicio' => '48H',
            'detalle' => $items->map(function (array $item) {
                return [
                    'sku' => $item['sku'] ?? null,
                    'cantidad' => (int) ($item['quantity'] ?? 0),
                    'peso' => (float) ($item['weight_kg'] ?? 0),
                    'alto' => (float) ($item['height_cm'] ?? 0),
                    'ancho' => (float) ($item['width_cm'] ?? 0),
                    'largo' => (float) ($item['length_cm'] ?? 0),
                ];
            })->values()->all(),
        ];

        $response = $this->request('/quote', $payload);

        $price = (float) (
            data_get($response, 'shipping_cost')
            ?? data_get($response, 'valor_flete')
            ?? data_get($response, 'data.shipping_cost')
            ?? 0
        );

        return [
            'success' => $price >= 0,
            'shipping_cost' => round($price, 2),
            'currency' => (string) (data_get($response, 'currency') ?? 'COP'),
            'delivery_estimate' => data_get($response, 'delivery_estimate'),
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
