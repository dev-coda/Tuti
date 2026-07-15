<?php

namespace App\Services\Shipping;

use App\Models\Order;
use RuntimeException;

class CoordinadoraGuideService
{
    public function __construct(
        private readonly CoordinadoraAuthService $authService,
        private readonly PackageAssignmentService $packageAssignmentService
    ) {
    }

    public function createGuide(Order $order): array
    {
        $order->loadMissing(['user', 'zone', 'products.product']);
        if (!$order->zone) {
            throw new RuntimeException('Order has no zone for Coordinadora guide creation.');
        }

        $packages = $this->packageAssignmentService->assignForOrder($order);

        $payload = [
            'idProceso' => config('services.coordinadora.id_proceso'),
            'orderId' => (string) $order->id,
            'zipCode' => $order->zone->zip_code,
            'direccion' => $order->zone->address,
            'destinatario' => $order->user?->name,
            'identificacion' => $order->user?->document,
            'telefono' => $order->user?->mobile_phone ?? $order->user?->phone,
            'valorDeclarado' => (float) $order->total,
            'detalle' => collect($order->products)->map(function ($orderProduct) {
                return [
                    'sku' => $orderProduct->product?->sku,
                    'cantidad' => (int) $orderProduct->quantity,
                    'peso' => (float) ($orderProduct->product?->coordinadora_weight_kg ?? 0),
                    'alto' => (float) ($orderProduct->product?->coordinadora_height_cm ?? 0),
                    'ancho' => (float) ($orderProduct->product?->coordinadora_width_cm ?? 0),
                    'largo' => (float) ($orderProduct->product?->coordinadora_length_cm ?? 0),
                ];
            })->values()->all(),
            'empaques' => collect($packages)->map(function (array $package) {
                return [
                    'codigo' => $package['code'],
                    'nombre' => $package['name'],
                    'cantidad' => $package['count'],
                ];
            })->values()->all(),
        ];

        $baseUrl = rtrim((string) config('services.coordinadora.base_url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('Coordinadora base URL is not configured.');
        }

        $response = \Illuminate\Support\Facades\Http::timeout(30)
            ->acceptJson()
            ->withToken($this->authService->getAccessToken())
            ->post($baseUrl . '/guides', $payload)
            ->throw()
            ->json();

        $guideNumber = (string) (
            data_get($response, 'guide_number')
            ?? data_get($response, 'numero_guia')
            ?? data_get($response, 'data.guia')
            ?? ''
        );

        return [
            'success' => $guideNumber !== '',
            'guide_number' => $guideNumber,
            'status_code' => (string) (data_get($response, 'status_code') ?? 'CREATED'),
            'status_text' => (string) (data_get($response, 'status_text') ?? 'Guia creada'),
            'packages' => $packages,
            'request_payload' => $payload,
            'response_payload' => is_array($response) ? $response : [],
        ];
    }
}
