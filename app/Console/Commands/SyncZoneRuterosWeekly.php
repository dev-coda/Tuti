<?php

namespace App\Console\Commands;

use App\Jobs\SyncZoneRuteros;
use App\Models\Setting;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncZoneRuterosWeekly extends Command
{
    protected $signature = 'clients:sync-zone-ruteros';

    protected $description = 'Queue Dynamics (getRuteros) sync per zone to refresh clients zona/ruta/día (weekly job)';

    public function handle(): int
    {
        $enabled = Setting::getByKeyWithDefault('weekly_zone_rutero_sync_enabled', '1');
        if ($enabled !== '1' && $enabled !== 1 && $enabled !== true) {
            $this->info('Weekly zone rutero sync is disabled (setting weekly_zone_rutero_sync_enabled).');

            return self::SUCCESS;
        }

        // Possible zones: seller zones plus every zone already present on client zone rows.
        $zoneCodes = User::query()
            ->whereRelation('roles', 'name', 'seller')
            ->whereNotNull('zone')
            ->where('zone', '!=', '')
            ->pluck('zone')
            ->merge(
                Zone::query()
                    ->whereNotNull('zone')
                    ->where('zone', '!=', '')
                    ->distinct()
                    ->pluck('zone')
            )
            ->map(fn ($zone) => trim((string) $zone))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

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
