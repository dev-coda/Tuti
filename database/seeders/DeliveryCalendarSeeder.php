<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DeliveryCalendar;
use Carbon\Carbon;

class DeliveryCalendarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DeliveryCalendar::truncate();
        
        $csvPath = base_path('docs/calendar.csv');
        
        if (!file_exists($csvPath)) {
            $this->command->error('Calendar CSV file not found at: ' . $csvPath);
            return;
        }
        
        $lines = file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            $parts = str_getcsv($line);
            
            if (count($parts) < 6) {
                continue;
            }
            
            // Parse the CSV format: year, month, week_number, start_date, end_date, cycle
            // Year format: "2.025" -> "2025"
            $year = str_replace('.', '', trim($parts[0]));
            $month = trim($parts[1]);
            $weekNumber = (int) trim($parts[2]);
            
            // Date format: d/m/Y
            $startDate = Carbon::createFromFormat('d/m/Y', trim($parts[3]));
            $endDate = Carbon::createFromFormat('d/m/Y', trim($parts[4]));
            $cycle = trim($parts[5]);
            
            DeliveryCalendar::create([
                'year' => $year,
                'month' => $month,
                'week_number' => $weekNumber,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'cycle' => $cycle,
            ]);
        }
        
        $this->command->info('Imported ' . DeliveryCalendar::count() . ' delivery calendar entries');
    }
}
