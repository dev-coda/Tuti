<?php

namespace App\Services;

use App\Jobs\ProcessOrderAsync;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Log;

class DraftOrderReconciliationService
{
    /**
     * @return array<string, int>
     */
    public function reconcileAll(): array
    {
        $stats = [
            'users_checked' => 0,
            'users_promoted' => 0,
            'drafts_attempted' => 0,
            'drafts_queued' => 0,
            'drafts_waiting' => 0,
            'drafts_failed' => 0,
        ];

        $pendingUsers = User::query()
            ->whereDoesntHave('roles')
            ->where('client_status', User::CLIENT_STATUS_PENDIENTE)
            ->whereNotNull('document')
            ->where('document', '!=', '')
            ->get();

        foreach ($pendingUsers as $user) {
            $stats['users_checked']++;
            UserRepository::syncUserRuteroData($user);
            $user->refresh();
            $user->load('zones');

            if ($this->promoteUserIfReady($user)) {
                $stats['users_promoted']++;
            }
        }

        Order::query()
            ->where('status_id', Order::STATUS_DRAFT)
            ->with(['user.zones', 'zone'])
            ->orderBy('id')
            ->chunkById(50, function ($orders) use (&$stats) {
                foreach ($orders as $order) {
                    $result = $this->attemptTransmitDraft($order);
                    $stats['drafts_attempted']++;
                    $stats[$result]++;
                }
            });

        return $stats;
    }

    public function promoteUserIfReady(User $user): bool
    {
        if (! $user->isPendingClient()) {
            return false;
        }

        if ($user->client_status === User::CLIENT_STATUS_CLIENTE) {
            return false;
        }

        if (! $user->hasValidRuteroCode()) {
            return false;
        }

        $user->update([
            'client_status' => User::CLIENT_STATUS_CLIENTE,
            'status_id' => User::ACTIVE,
        ]);

        Log::info('Pending client promoted to cliente after rutero sync', [
            'user_id' => $user->id,
            'document' => $user->document,
        ]);

        return true;
    }

    /**
     * @return 'drafts_queued'|'drafts_waiting'|'drafts_failed'
     */
    public function attemptTransmitDraft(Order $order): string
    {
        $order->refresh();
        $order->load(['user.zones', 'zone', 'products']);

        $user = $order->user;
        if (! $user) {
            $this->markDraftFailure($order, 'Pedido sin usuario asociado.');

            return 'drafts_failed';
        }

        if ($user->isProspectClient()) {
            $this->markDraftFailure($order, 'Cliente en estado Prospecto: requiere cambio manual a Pendiente antes de procesar borradores.');

            return 'drafts_failed';
        }

        if ($user->isPendingClient() && ! $user->hasValidRuteroCode()) {
            $this->markDraftFailure($order, 'Cliente aún pendiente: rutero no disponible.');

            return 'drafts_failed';
        }

        if ($user->is_locked) {
            $this->markDraftFailure($order, 'Cliente bloqueado en Dynamics.');

            return 'drafts_failed';
        }

        $zone = $this->resolveZoneForTransmission($order, $user);
        if (! $zone || ! $this->zoneHasRuteroCode($zone)) {
            $this->markDraftFailure($order, 'No hay sucursal con CustRuteroID válido para transmitir.');

            return 'drafts_failed';
        }

        if ($order->products->isEmpty()) {
            $this->markDraftFailure($order, 'Pedido sin líneas de producto.');

            return 'drafts_failed';
        }

        $order->update([
            'zone_id' => $zone->id,
            'zone_snapshot' => $this->buildZoneSnapshot($zone),
            'draft_reconciliation_note' => null,
            'draft_reconciliation_at' => now(),
        ]);

        $transmission = $this->resolveTransmissionStatus($order->fresh(), $zone);

        $order->update([
            'status_id' => $transmission['status_id'],
            'scheduled_transmission_date' => $transmission['scheduled_transmission_date'],
        ]);

        if ($transmission['status_id'] === Order::STATUS_WAITING) {
            Log::info('Draft order promoted to waiting for seller visit day', [
                'order_id' => $order->id,
                'scheduled_transmission_date' => $transmission['scheduled_transmission_date'],
            ]);

            return 'drafts_waiting';
        }

        $queueConnection = config('queue.default');
        if ($queueConnection === 'sync') {
            $queueConnection = 'database';
        }

        ProcessOrderAsync::dispatch($order->fresh())->onConnection($queueConnection);

        Log::info('Draft order queued for XML transmission', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'zone_id' => $zone->id,
            'zone_code' => $zone->code,
        ]);

        return 'drafts_queued';
    }

    private function resolveZoneForTransmission(Order $order, User $user): ?Zone
    {
        $order->loadMissing('zone');

        if ($order->zone && $this->zoneHasRuteroCode($order->zone)) {
            return $order->zone;
        }

        $snapshotCode = trim((string) ($order->zone_snapshot['code'] ?? ''));
        if ($snapshotCode !== '') {
            $fromSnapshot = $user->zones()->where('code', $snapshotCode)->first();
            if ($fromSnapshot && $this->zoneHasRuteroCode($fromSnapshot)) {
                return $fromSnapshot;
            }
        }

        return $user->zones()
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('id')
            ->first();
    }

    private function zoneHasRuteroCode(?Zone $zone): bool
    {
        if (! $zone) {
            return false;
        }

        return trim((string) $zone->code) !== '';
    }

    /**
     * @return array{id: int, code: ?string, route: ?string, zone: ?string, day: ?string, address: ?string}
     */
    private function buildZoneSnapshot(Zone $zone): array
    {
        return [
            'id' => $zone->id,
            'code' => $zone->code,
            'route' => $zone->route,
            'zone' => $zone->zone,
            'day' => $zone->day,
            'address' => $zone->address,
        ];
    }

    /**
     * @return array{status_id: int, scheduled_transmission_date: ?string}
     */
    private function resolveTransmissionStatus(Order $order, Zone $zone): array
    {
        $statusId = Order::STATUS_PENDING;
        $scheduledDate = null;

        $forceDeliveryDate = Setting::getByKey('force_delivery_date_enabled') == '1';

        if ($order->delivery_method === Order::DELIVERY_METHOD_TRONEX && ! $forceDeliveryDate) {
            $sellerVisitDate = OrderRepository::getTronexSellerVisitDate($zone);
            if ($sellerVisitDate) {
                $today = now();
                $isTodaySellerVisitDay = $today->format('Y-m-d') === $sellerVisitDate->format('Y-m-d');
                if (! $isTodaySellerVisitDay) {
                    $statusId = Order::STATUS_WAITING;
                    $scheduledDate = $sellerVisitDate->format('Y-m-d');
                }
            }
        }

        return [
            'status_id' => $statusId,
            'scheduled_transmission_date' => $scheduledDate,
        ];
    }

    private function markDraftFailure(Order $order, string $reason): void
    {
        $order->update([
            'draft_reconciliation_note' => $reason,
            'draft_reconciliation_at' => now(),
        ]);

        Log::warning('Draft order reconciliation deferred', [
            'order_id' => $order->id,
            'reason' => $reason,
        ]);
    }
}
