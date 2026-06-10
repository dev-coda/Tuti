<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Models\Zone;
use App\Repositories\UserRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Weekly sync: pulls every rutero per zone from Dynamics (getRuteros without
 * document filter) and refreshes the clients' zona/ruta/día on their zone rows,
 * matched by CustRuteroID (zones.code).
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
    ) {}

    public function handle(): void
    {
        $summary = [
            'zones_processed' => 0,
            'zones_failed' => 0,
            'zones_empty' => 0,
            'ruteros_seen' => 0,
            'ruteros_without_code' => 0,
            'ruteros_unmatched' => 0,
            'zone_rows_updated' => 0,
        ];

        Log::info('Zone rutero sync started', [
            'session_id' => $this->sessionId,
            'zones' => count($this->zoneCodes),
        ]);

        foreach ($this->zoneCodes as $zoneCode) {
            try {
                $ruteros = UserRepository::getRuterosForZone($zoneCode);
            } catch (\Throwable $e) {
                $summary['zones_failed']++;
                Log::error('Zone rutero sync: fetch failed for zone', [
                    'session_id' => $this->sessionId,
                    'zone' => $zoneCode,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if ($ruteros === null || $ruteros->isEmpty()) {
                $summary['zones_empty']++;
                Log::warning('Zone rutero sync: no ruteros returned for zone', [
                    'session_id' => $this->sessionId,
                    'zone' => $zoneCode,
                ]);
                continue;
            }

            $summary['zones_processed']++;

            foreach ($ruteros as $rutero) {
                $summary['ruteros_seen']++;

                $code = trim((string) ($rutero['code'] ?? ''));
                if ($code === '') {
                    $summary['ruteros_without_code']++;
                    continue;
                }

                $zoneRows = Zone::query()->where('code', $code)->get();
                if ($zoneRows->isEmpty()) {
                    $summary['ruteros_unmatched']++;
                    continue;
                }

                foreach ($zoneRows as $zoneRow) {
                    $changes = [];
                    foreach (['zone', 'route', 'day'] as $field) {
                        $incoming = trim((string) ($rutero[$field] ?? ''));
                        if ($incoming !== '' && $incoming !== trim((string) $zoneRow->{$field})) {
                            $changes[$field] = $incoming;
                        }
                    }

                    if ($changes !== []) {
                        $zoneRow->update($changes);
                        $summary['zone_rows_updated']++;

                        Log::info('Zone rutero sync: client zone row updated', [
                            'session_id' => $this->sessionId,
                            'zone_row_id' => $zoneRow->id,
                            'user_id' => $zoneRow->user_id,
                            'code' => $code,
                            'changes' => $changes,
                        ]);
                    }
                }
            }
        }

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
