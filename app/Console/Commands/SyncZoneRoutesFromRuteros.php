<?php

namespace App\Console\Commands;

use App\Services\RuteroZoneSyncService;
use Illuminate\Console\Command;

class SyncZoneRoutesFromRuteros extends Command
{
    protected $signature = 'zone-routes:sync-from-ruteros
                            {--zone=* : Limit sync to one or more zone codes}
                            {--catalog-only : Only populate zone_routes, skip client zone row updates}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Populate zone_routes and client zone relationships from getRuteros for all known zones';

    public function handle(RuteroZoneSyncService $syncService): int
    {
        $zones = collect($this->option('zone'))
            ->map(fn ($zone) => trim((string) $zone))
            ->filter()
            ->values()
            ->all();

        $zoneCodes = $syncService->discoverZoneCodes($zones !== [] ? $zones : null);

        if ($zoneCodes === []) {
            $this->warn('No zones found to sync.');

            return self::SUCCESS;
        }

        $this->info('Syncing ruteros for '.count($zoneCodes).' zone(s): '.implode(', ', $zoneCodes));

        if ($this->option('dry-run')) {
            $this->comment('Dry run — no database changes will be made.');
        }

        $summary = $syncService->syncFromRuteros(
            $zones !== [] ? $zones : null,
            updateClients: ! $this->option('catalog-only'),
            dryRun: (bool) $this->option('dry-run'),
        );

        $this->table(
            ['Metric', 'Count'],
            collect($summary)->map(fn ($value, $key) => [str_replace('_', ' ', $key), $value])->values()->all()
        );

        if ($summary['zones_failed'] > 0) {
            $this->warn('Some zones failed. Check logs for details.');
        }

        return self::SUCCESS;
    }
}
