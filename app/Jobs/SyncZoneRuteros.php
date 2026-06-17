<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Services\RuteroZoneSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Weekly sync: pulls every rutero per zone from Dynamics (getRuteros without
 * document filter) and refreshes zone_routes plus clients' zona/ruta/día on
 * their zone rows, matched by CustRuteroID (zones.code).
 */
class SyncZoneRuteros implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 7200; // 2 hours

    /**
     * @param  array<int, string>  $zoneCodes
     */
    public function __construct(
        protected array $zoneCodes,
        protected string $sessionId,
        protected bool $updateClients = true,
    ) {}

    public function handle(RuteroZoneSyncService $syncService): void
    {
        Log::info('Zone rutero sync started', [
            'session_id' => $this->sessionId,
            'zones' => count($this->zoneCodes),
        ]);

        $summary = $syncService->syncFromRuteros(
            $this->zoneCodes,
            updateClients: $this->updateClients,
        );

        Setting::updateOrCreate(
            ['key' => 'last_zone_rutero_sync_at'],
            ['name' => 'Última sincronización rutero por zona', 'value' => now()->toIso8601String(), 'show' => false]
        );
        Setting::updateOrCreate(
            ['key' => 'last_zone_rutero_sync_session'],
            ['name' => 'Sesión última sync rutero por zona', 'value' => $this->sessionId, 'show' => false]
        );

        Log::info('Zone rutero sync completed', array_merge(['session_id' => $this->sessionId], $summary));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Zone rutero sync job failed', [
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
