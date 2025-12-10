<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RouteCycle;
use Illuminate\Support\Facades\File;

class RouteCycleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('../docs/Hoja de cálculo sin título - Hoja 1.csv');
        
        if (!File::exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");
            return;
        }

        $lines = File::lines($csvPath);
        $processed = [];
        $duplicates = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $parts = explode(',', $line);
            if (count($parts) < 2) {
                continue;
            }

            $route = trim($parts[0]);
            $cycle = strtoupper(trim($parts[1]));

            // Skip if cycle is not A, B, or C
            if (!in_array($cycle, ['A', 'B', 'C'])) {
                continue;
            }

            // Track duplicates
            if (isset($processed[$route])) {
                $duplicates++;
                $this->command->warn("Duplicate route found: {$route} (keeping first: {$processed[$route]})");
                continue;
            }

            RouteCycle::updateOrCreate(
                ['route' => $route],
                ['cycle' => $cycle]
            );

            $processed[$route] = $cycle;
        }

        $this->command->info("Seeded " . count($processed) . " route cycles");
        if ($duplicates > 0) {
            $this->command->warn("Skipped {$duplicates} duplicate routes");
        }
    }
}
