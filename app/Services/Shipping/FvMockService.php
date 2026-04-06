<?php

namespace App\Services\Shipping;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FvMockService
{
    public function createFv(Order $order): array
    {
        $order->loadMissing(['user', 'zone', 'products.product']);

        $payload = [
            'order_id' => $order->id,
            'customer' => [
                'id' => $order->user_id,
                'name' => $order->user?->name,
                'document' => $order->user?->document,
            ],
            'delivery' => [
                'zone_id' => $order->zone_id,
                'zip_code' => $order->zone?->zip_code,
                'address' => $order->zone?->address,
            ],
            'items' => collect($order->products)->map(function ($orderProduct) {
                return [
                    'product_id' => $orderProduct->product_id,
                    'sku' => $orderProduct->product?->sku,
                    'quantity' => (int) $orderProduct->quantity,
                    'unit_price' => (float) $orderProduct->price,
                    'discount_percentage' => (float) $orderProduct->percentage,
                    'discount_type' => $orderProduct->discount_type,
                    'flat_discount_amount' => (float) $orderProduct->flat_discount_amount,
                ];
            })->values()->all(),
        ];

        $endpoint = (string) config('services.fv_mock.endpoint');
        if ($endpoint === '') {
            throw new RuntimeException('FV mock endpoint is not configured.');
        }

        if (!str_starts_with($endpoint, 'http://') && !str_starts_with($endpoint, 'https://')) {
            return [
                'success' => true,
                'fv_number' => 'FV-MOCK-' . $order->id,
                'request_payload' => $payload,
                'response_payload' => [
                    'success' => true,
                    'fv_number' => 'FV-MOCK-' . $order->id,
                    'mode' => 'internal-mock',
                ],
            ];
        }

        $response = Http::timeout(20)
            ->acceptJson()
            ->withHeaders([
                'X-FV-MOCK-TOKEN' => (string) config('services.fv_mock.token'),
            ])
            ->post($endpoint, $payload)
            ->throw()
            ->json();

        $fvNumber = (string) (data_get($response, 'fv_number') ?? '');
        if ($fvNumber === '') {
            throw new RuntimeException('FV mock endpoint did not return fv_number.');
        }

        return [
            'success' => true,
            'fv_number' => $fvNumber,
            'request_payload' => $payload,
            'response_payload' => is_array($response) ? $response : [],
        ];
    }
}
