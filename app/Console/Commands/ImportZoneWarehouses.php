<?php

namespace App\Console\Commands;

use App\Models\ZoneWarehouse;
use Illuminate\Console\Command;

class ImportZoneWarehouses extends Command
{
    protected $signature = 'app:import-zone-warehouses {path=public/storage/Sheet1-Table 1.csv}';

    protected $description = 'Import zone to bodega mapping from a semicolon-separated CSV';

    public function handle(): int
    {
        $path = base_path($this->argument('path'));
        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $rows = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $count = 0;
        foreach ($rows as $i => $row) {
            if ($i === 0) continue; // skip header
            $parts = array_map('trim', explode(';', $row));
            if (count($parts) < 2) continue;
            $zone = $parts[0] ?? null;
            $bodega = $parts[1] ?? null;
            if (!$zone || !$bodega) continue;
            ZoneWarehouse::updateOrCreate([
                'zone_code' => $zone,
                'bodega_code' => $bodega,
            ], []);
            $count++;
        }
        $this->info("Imported/updated {$count} mappings.");
        return self::SUCCESS;
    }
}
