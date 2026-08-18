<?php

namespace App\Services\Shipping;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Express / Coordinadora order transmission.
 *
 * Today this creates the FV (sales order) in Dynamics. Physical guide creation
 * is handled outside Tuti by the Activity integration, so we do not call
 * CoordinadoraGuideService here unless services.coordinadora.create_guides is
 * explicitly enabled.
 */
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

        $fvResult = null;

        // An order that already carries an FV number got one from a previous
        // attempt. Re-posting would only return YA_CREADO, so skip CreateSalesOrder.
        if (trim((string) $order->fv_number) === '') {
            $fvResult = $this->fvService->createFv($order);

            $order->update([
                'fv_number' => $fvResult['fv_number'] ?? null,
                'fv_request_payload' => isset($fvResult['request_payload']) ? json_encode($fvResult['request_payload']) : null,
                'fv_response_payload' => isset($fvResult['response_payload']) ? json_encode($fvResult['response_payload']) : null,
            ]);
        } else {
            Log::info('Reusing existing FV number, skipping CreateSalesOrder', [
                'order_id' => $order->id,
                'fv_number' => $order->fv_number,
            ]);
        }

        $guideResult = null;
        $updates = [
            'status_id' => Order::STATUS_PROCESSED,
        ];

        // Guide creation is optional. Activity owns fulfillment outside Tuti;
        // leave create_guides off unless Coordinadora guide API is wired again.
        if (config('services.coordinadora.create_guides')) {
            $guideResult = $this->guideService->createGuide($order);
            $updates = array_merge($updates, [
                'coordinadora_guide_number' => $guideResult['guide_number'] ?? null,
                'coordinadora_status_code' => $guideResult['status_code'] ?? 'CREATED',
                'coordinadora_status_text' => $guideResult['status_text'] ?? 'Guia creada',
                'coordinadora_status_at' => now(),
                'coordinadora_request_payload' => isset($guideResult['request_payload']) ? json_encode($guideResult['request_payload']) : null,
                'coordinadora_response_payload' => isset($guideResult['response_payload']) ? json_encode($guideResult['response_payload']) : null,
                'coordinadora_packages' => $guideResult['packages'] ?? null,
            ]);
        } else {
            $updates['coordinadora_status_code'] = $order->coordinadora_status_code ?: 'FV_ONLY';
            $updates['coordinadora_status_text'] = $order->coordinadora_status_text ?: 'FV creada; guía gestionada por Activity';
            $updates['coordinadora_status_at'] = now();
        }

        $order->update($updates);

        Log::info('Coordinadora workflow completed', [
            'order_id' => $order->id,
            'fv_number' => $order->fv_number,
            'guide_number' => $order->coordinadora_guide_number,
            'create_guides' => (bool) config('services.coordinadora.create_guides'),
        ]);

        return [
            'fv' => $fvResult,
            'guide' => $guideResult,
        ];
    }
}
