<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RouteCycle;

class RouteCycleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        RouteCycle::truncate();
        
        $csvPath = base_path('docs/Hoja de cálculo sin título - Hoja 1.csv');
        
        if (!file_exists($csvPath)) {
            $this->command->error('Route cycles CSV file not found at: ' . $csvPath);
            return;
        }
        
        $lines = file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        $imported = 0;
        $uniqueRoutes = [];
        
        foreach ($lines as $line) {
            $parts = str_getcsv($line);
            
            if (count($parts) < 2) {
                continue;
            }
            
            $route = trim($parts[0]);
            $cycle = trim($parts[1]);
            
            // Skip if we've already imported this route (there are duplicates in the file)
            if (isset($uniqueRoutes[$route])) {
                continue;
            }
            
            // Validate cycle
            if (!in_array($cycle, ['A', 'B', 'C'])) {
                continue;
            }
            
            RouteCycle::create([
                'route' => $route,
                'cycle' => $cycle,
            ]);
            
            $uniqueRoutes[$route] = true;
            $imported++;
        }
        
        $this->command->info('Imported ' . $imported . ' route cycle entries');
    }
}
