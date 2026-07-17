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
            ->whereIn('client_status', [
                User::CLIENT_STATUS_PENDIENTE,
                User::CLIENT_STATUS_PROSPECTO,
            ])
            ->whereNotNull('document')
            ->where('document', '!=', '')
            ->get();

        foreach ($pendingUsers as $user) {
            $stats['users_checked']++;
            $result = $this->syncUserFromRutero($user);

            if ($result['promoted']) {
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

    /**
     * @return array{drafts_attempted: int, drafts_queued: int, drafts_waiting: int, drafts_failed: int}
     */
    public function attemptTransmitDraftsForUser(User $user): array
    {
        $stats = [
            'drafts_attempted' => 0,
            'drafts_queued' => 0,
            'drafts_waiting' => 0,
            'drafts_failed' => 0,
        ];

        Order::query()
            ->where('user_id', $user->id)
            ->where('status_id', Order::STATUS_DRAFT)
            ->with(['user.zones', 'zone', 'products'])
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
        if ($user->isRejectedClient()) {
            return false;
        }

        // Self-registered users get client_status 'cliente' (column default) but stay
        // status_id PENDING, so activation must also run for clientes not yet ACTIVE.
        if ($user->isCliente() && (int) $user->status_id === User::ACTIVE) {
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
     * Sync one client from getRuteros and optionally promote pendiente/prospecto users.
     *
     * @return array{
     *     success: bool,
     *     synced: bool,
     *     promoted: bool,
     *     drafts: array{drafts_attempted: int, drafts_queued: int, drafts_waiting: int, drafts_failed: int},
     *     message: string,
     *     user: ?User
     * }
     */
    public function syncUserFromRutero(User $user, bool $promoteIfPossible = true, bool $transmitDrafts = false): array
    {
        $emptyDraftStats = [
            'drafts_attempted' => 0,
            'drafts_queued' => 0,
            'drafts_waiting' => 0,
            'drafts_failed' => 0,
        ];

        if (! $user->document) {
            return [
                'success' => false,
                'synced' => false,
                'promoted' => false,
                'drafts' => $emptyDraftStats,
                'message' => 'El usuario no tiene documento registrado.',
                'user' => $user,
            ];
        }

        if ($user->isRejectedClient()) {
            return [
                'success' => false,
                'synced' => false,
                'promoted' => false,
                'drafts' => $emptyDraftStats,
                'message' => 'El cliente está rechazado y no se sincroniza desde rutero.',
                'user' => $user,
            ];
        }

        $wasCliente = $user->isCliente();
        $synced = UserRepository::syncUserRuteroData($user);
        $user->refresh();
        $user->load('zones');

        $promoted = false;
        if ($promoteIfPossible && ! $wasCliente) {
            $promoted = $this->promoteUserIfReady($user);
            $user->refresh();
            $user->load('zones');
        }

        $drafts = $emptyDraftStats;
        if ($transmitDrafts && $user->isCliente()) {
            $drafts = $this->attemptTransmitDraftsForUser($user);
        }

        $draftMessage = $this->formatDraftTransmissionMessage($drafts);

        if ($synced && ($wasCliente || $user->isCliente())) {
            $message = $promoted
                ? 'Rutero sincronizado y cliente activado correctamente.'
                : 'Rutero sincronizado correctamente.';
            if ($draftMessage !== '') {
                $message .= ' '.$draftMessage;
            }

            return [
                'success' => true,
                'synced' => true,
                'promoted' => $promoted,
                'drafts' => $drafts,
                'message' => $message,
                'user' => $user,
            ];
        }

        if ($synced) {
            return [
                'success' => true,
                'synced' => true,
                'promoted' => false,
                'drafts' => $drafts,
                'message' => 'Rutero sincronizado, pero el cliente sigue pendiente (sin CustRuteroID válido).',
                'user' => $user,
            ];
        }

        return [
            'success' => false,
            'synced' => false,
            'promoted' => false,
            'drafts' => $drafts,
            'message' => 'No se encontró rutero en Dynamics para este documento.',
            'user' => $user->fresh(['zones']),
        ];
    }

    /**
     * @return array{success: bool, synced: bool, promoted: bool, drafts: array{drafts_attempted: int, drafts_queued: int, drafts_waiting: int, drafts_failed: int}, message: string, user: ?User}
     */
    public function syncByDocument(string $document, bool $promoteIfPossible = true, bool $transmitDrafts = true): array
    {
        $normalizedDocument = preg_replace('/\D+/', '', $document) ?: trim($document);

        if ($normalizedDocument === '') {
            return [
                'success' => false,
                'synced' => false,
                'promoted' => false,
                'drafts' => [
                    'drafts_attempted' => 0,
                    'drafts_queued' => 0,
                    'drafts_waiting' => 0,
                    'drafts_failed' => 0,
                ],
                'message' => 'Debes ingresar un documento válido.',
                'user' => null,
            ];
        }

        $user = User::query()
            ->whereDoesntHave('roles')
            ->where('document', $normalizedDocument)
            ->first();

        if (! $user) {
            return [
                'success' => false,
                'synced' => false,
                'promoted' => false,
                'drafts' => [
                    'drafts_attempted' => 0,
                    'drafts_queued' => 0,
                    'drafts_waiting' => 0,
                    'drafts_failed' => 0,
                ],
                'message' => "No hay un cliente local con documento {$normalizedDocument}.",
                'user' => null,
            ];
        }

        return $this->syncUserFromRutero($user, $promoteIfPossible, $transmitDrafts);
    }

    /**
     * @param  array{drafts_attempted: int, drafts_queued: int, drafts_waiting: int, drafts_failed: int}  $drafts
     */
    private function formatDraftTransmissionMessage(array $drafts): string
    {
        if ($drafts['drafts_attempted'] === 0) {
            return '';
        }

        $parts = [];
        if ($drafts['drafts_queued'] > 0) {
            $parts[] = "{$drafts['drafts_queued']} borrador(es) enviado(s) a transmisión";
        }
        if ($drafts['drafts_waiting'] > 0) {
            $parts[] = "{$drafts['drafts_waiting']} borrador(es) programado(s)";
        }
        if ($drafts['drafts_failed'] > 0) {
            $parts[] = "{$drafts['drafts_failed']} borrador(es) aún pendientes";
        }

        return $parts === [] ? '' : implode('; ', $parts).'.';
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

        if ($user->isProspectClient() || $user->isPendingClient()) {
            if (! $user->hasValidRuteroCode()) {
                $this->markDraftFailure(
                    $order,
                    $user->isProspectClient()
                        ? 'Cliente prospecto sin CustRuteroID válido.'
                        : 'Cliente aún pendiente: rutero no disponible.'
                );

                return 'drafts_failed';
            }

            $this->promoteUserIfReady($user->fresh(['zones']));
            $user = $user->fresh(['zones']);

            if (! $user->isCliente()) {
                $this->markDraftFailure($order, 'No se pudo activar el cliente antes de transmitir el borrador.');

                return 'drafts_failed';
            }
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
