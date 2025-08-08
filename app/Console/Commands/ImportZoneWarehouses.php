<?php

namespace App\Console\Commands;

use App\Models\ZoneWarehouse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportZoneWarehouses extends Command
{
    protected $signature = 'app:import-zone-warehouses {--path=} {--truncate}';
    protected $description = 'Import zone to bodega mapping from config by default, or from a semicolon-separated CSV when --path is provided';

    public function handle(): int
    {
        $path = $this->option('path');
        $truncate = (bool) $this->option('truncate');

        if ($truncate) {
            ZoneWarehouse::truncate();
            $this->info('zone_warehouses table truncated.');
        }

        // If a file path was provided, import from CSV
        if ($path) {
            $fullPath = base_path($path);
            if (!file_exists($fullPath)) {
                $this->error("File not found: {$fullPath}");
                return self::FAILURE;
            }

            $rows = file($fullPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($rows === false) {
                $this->error('Failed to read CSV file.');
                return self::FAILURE;
            }

            $count = 0;
            foreach ($rows as $i => $row) {
                if ($i === 0) { // skip header row if present
                    // Heuristic: if row contains non-alphanumeric and not semicolon, still skip just in case
                    // Leave as-is to ensure no header inserted
                    continue;
                }
                $parts = array_map('trim', explode(';', $row));
                if (count($parts) < 2) {
                    continue;
                }
                $zone = $parts[0] ?? null;
                $bodega = $parts[1] ?? null;
                if (!$zone || !$bodega) {
                    continue;
                }

                ZoneWarehouse::updateOrCreate([
                    'zone_code' => $zone,
                    'bodega_code' => $bodega,
                ], []);
                $count++;
            }
            $this->info("Imported/updated {$count} mappings from CSV.");
            return self::SUCCESS;
        }

        // Default: import from config/zone_warehouses.php
        $mappings = (array) config('zone_warehouses.mappings', []);
        if (empty($mappings)) {
            $this->warn('No mappings found in config/zone_warehouses.php. Provide --path to a CSV or configure the mappings.');
            return self::SUCCESS; // not a failure; nothing to import
        }

        $count = 0;
        foreach ($mappings as $zone => $bodegas) {
            // allow single string or array of bodegas
            $bodegaList = is_array($bodegas) ? $bodegas : [$bodegas];
            foreach ($bodegaList as $bodega) {
                if (!$zone || !$bodega) {
                    continue;
                }
                ZoneWarehouse::updateOrCreate([
                    'zone_code' => trim((string) $zone),
                    'bodega_code' => trim((string) $bodega),
                ], []);
                $count++;
            }
        }

        $this->info("Imported/updated {$count} mappings from config.");
        return self::SUCCESS;
    }
}
