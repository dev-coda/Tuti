<?php

namespace App\Console\Commands;

use App\Jobs\SyncZoneRuteros;
use App\Models\Setting;
use App\Services\RuteroZoneSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncZoneRuterosWeekly extends Command
{
    protected $signature = 'clients:sync-zone-ruteros';

    protected $description = 'Queue Dynamics (getRuteros) sync per zone to refresh clients zona/ruta/día (weekly job)';

    public function handle(RuteroZoneSyncService $syncService): int
    {
        $enabled = Setting::getByKeyWithDefault('weekly_zone_rutero_sync_enabled', '1');
        if ($enabled !== '1' && $enabled !== 1 && $enabled !== true) {
            $this->info('Weekly zone rutero sync is disabled (setting weekly_zone_rutero_sync_enabled).');

            return self::SUCCESS;
        }

        $zoneCodes = $syncService->discoverZoneCodes();

        if ($zoneCodes === []) {
            $this->warn('No zones found to sync.');

            return self::SUCCESS;
        }

        $sessionId = 'zones-' . now()->format('Ymd-His') . '-' . Str::random(8);

        $queueConnection = config('queue.default');
        if ($queueConnection === 'sync') {
            $queueConnection = 'database';
        }

        SyncZoneRuteros::dispatch($zoneCodes, $sessionId)
            ->onConnection($queueConnection)
            ->onQueue('default');

        $this->info('Dispatched zone rutero sync for ' . count($zoneCodes) . ' zones. Session: ' . $sessionId);

        return self::SUCCESS;
    }
}
