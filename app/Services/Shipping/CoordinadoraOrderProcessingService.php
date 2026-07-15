<?php

namespace App\Services\Shipping;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CoordinadoraOrderProcessingService
{
    public function __construct(
        private readonly FvDynamicsService $fvService,
        private readonly CoordinadoraGuideService $guideService
    ) {
    }

    public function process(Order $order): array
    {
        $order->loadMissing(['products.product', 'user', 'zone']);
        if (!$order->zone) {
            throw new RuntimeException('Order has no zone; cannot process Coordinadora workflow.');
        }

        $fvResult = $this->fvService->createFv($order);
        $guideResult = $this->guideService->createGuide($order);

        $order->update([
            'fv_number' => $fvResult['fv_number'] ?? null,
            'fv_request_payload' => isset($fvResult['request_payload']) ? json_encode($fvResult['request_payload']) : null,
            'fv_response_payload' => isset($fvResult['response_payload']) ? json_encode($fvResult['response_payload']) : null,
            'coordinadora_guide_number' => $guideResult['guide_number'] ?? null,
            'coordinadora_status_code' => $guideResult['status_code'] ?? 'CREATED',
            'coordinadora_status_text' => $guideResult['status_text'] ?? 'Guia creada',
            'coordinadora_status_at' => now(),
            'coordinadora_request_payload' => isset($guideResult['request_payload']) ? json_encode($guideResult['request_payload']) : null,
            'coordinadora_response_payload' => isset($guideResult['response_payload']) ? json_encode($guideResult['response_payload']) : null,
            'coordinadora_packages' => $guideResult['packages'] ?? null,
            'status_id' => Order::STATUS_PROCESSED,
        ]);

        Log::info('Coordinadora workflow completed', [
            'order_id' => $order->id,
            'fv_number' => $order->fv_number,
            'guide_number' => $order->coordinadora_guide_number,
        ]);

        return [
            'fv' => $fvResult,
            'guide' => $guideResult,
        ];
    }
}
