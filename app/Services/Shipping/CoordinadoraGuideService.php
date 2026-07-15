<?php

namespace App\Services\Shipping;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client for Coordinadora's standard guide creation API (docs/COORDINADORA/
 * Documentacion Creacion de Guía Estándar y RCE). Colombian shipments locate
 * sender and recipient by DANE city codes (codigoCiudadRemitente/Destinatario).
 */
class CoordinadoraGuideService
{
    public function __construct(
        private readonly CoordinadoraAuthService $authService
    ) {
    }

    public function createGuide(Order $order): array
    {
        $order->loadMissing(['user', 'zone', 'products.product']);
        if (!$order->zone) {
            throw new RuntimeException('Order has no zone for Coordinadora guide creation.');
        }

        $destinoDane = $order->zone->coordinadoraDaneCode();
        if ($destinoDane === null) {
            throw new RuntimeException(
                'Zone ' . $order->zone->id . ' has no DANE destination code for Coordinadora guide creation.'
            );
        }

        $config = config('services.coordinadora');

        $origenDane = DaneCodeService::normalize($config['origin_dane'] ?? null);
        if ($origenDane === null) {
            throw new RuntimeException('Coordinadora origin DANE code is not configured.');
        }

        $payload = [
            'identificacion' => (string) ($config['nit'] ?? ''),
            'idProceso' => (int) ($config['id_proceso'] ?? 0),
            'divisionCliente' => (string) ($config['div'] ?? '01'),
            'codigoPais' => 170,
            'valoracion' => (string) (float) $order->total,
            'tipoCuenta' => (int) ($config['tipo_cuenta'] ?? 1),
            'contenido' => 'Pedido Tuti #' . $order->id,
            'nivelServicio' => 1,
            'referenciaGuia' => (string) $order->id,
            'detalle' => collect($order->products)->map(function ($orderProduct) {
                return [
                    'pesoReal' => (string) (float) ($orderProduct->product?->coordinadora_weight_kg ?? 0),
                    'largo' => (string) (float) ($orderProduct->product?->coordinadora_length_cm ?? 0),
                    'ancho' => (string) (float) ($orderProduct->product?->coordinadora_width_cm ?? 0),
                    'alto' => (string) (float) ($orderProduct->product?->coordinadora_height_cm ?? 0),
                    'unidades' => (int) $orderProduct->quantity,
                    'ubl' => 0,
                    'referencia' => (string) ($orderProduct->product?->sku ?? ''),
                    'valorDeclarado' => (float) $orderProduct->price * (int) $orderProduct->quantity,
                ];
            })->values()->all(),
            'datosRemitente' => [
                'nombreRemitente' => (string) ($config['origin_name'] ?? ''),
                'direccionRemitente' => (string) ($config['origin_address'] ?? ''),
                'codigoCiudadRemitente' => $origenDane,
                'celularRemitente' => (string) ($config['origin_phone'] ?? ''),
            ],
            'datosDestinatario' => [
                'identificacionDestinatario' => (string) ($order->user?->document ?? ''),
                'nombreDestinatario' => (string) ($order->user?->business_name ?? $order->user?->name ?? ''),
                'direccionDestinatario' => (string) $order->zone->address,
                'codigoCiudadDestinatario' => $destinoDane,
                'celularDestinatario' => (string) ($order->user?->mobile_phone ?? $order->user?->phone ?? ''),
                'correoDestinatario' => (string) ($order->user?->email ?? ''),
            ],
            'tipoGuia' => 1,
            'usuario' => (string) ($config['usuario'] ?? ''),
            'fuente' => 'integracion',
            'quienPagaEnvio' => '1',
            'tipoEnvioEspecial' => false,
        ];

        $baseUrl = rtrim((string) config('services.coordinadora.base_url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('Coordinadora base URL is not configured.');
        }

        $guidesPath = '/' . ltrim((string) ($config['guides_path'] ?? '/guias'), '/');

        $response = Http::timeout(30)
            ->acceptJson()
            ->withToken($this->authService->getAccessToken())
            ->post($baseUrl . $guidesPath, $payload)
            ->throw()
            ->json();

        $guideNumber = (string) (
            data_get($response, 'data.numero_guia')
            ?? data_get($response, 'numero_guia')
            ?? data_get($response, 'data.guia')
            ?? data_get($response, 'guide_number')
            ?? ''
        );

        return [
            'success' => $guideNumber !== '',
            'guide_number' => $guideNumber,
            'status_code' => (string) (data_get($response, 'status_code') ?? 'CREATED'),
            'status_text' => (string) (data_get($response, 'status_text') ?? 'Guia creada'),
            'request_payload' => $payload,
            'response_payload' => is_array($response) ? $response : [],
        ];
    }
}
