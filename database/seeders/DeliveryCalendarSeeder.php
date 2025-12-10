<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DeliveryCalendar;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class DeliveryCalendarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('../docs/calendar.csv');
        
        if (!File::exists($csvPath)) {
            $this->command->error("CSV file not found: {$csvPath}");
            return;
        }

        $lines = File::lines($csvPath);
        $count = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $parts = explode(',', $line);
            if (count($parts) < 6) {
                continue;
            }

            $year = trim($parts[0]);
            $month = trim($parts[1]);
            $weekNumber = (int) trim($parts[2]);
            $startDateStr = trim($parts[3]);
            $endDateStr = trim($parts[4]);
            $cycle = strtoupper(trim($parts[5]));

            // Skip if cycle is not A, B, or C
            if (!in_array($cycle, ['A', 'B', 'C'])) {
                continue;
            }

            // Parse dates (format: d/m/Y)
            try {
                $startDate = Carbon::createFromFormat('d/m/Y', $startDateStr);
                $endDate = Carbon::createFromFormat('d/m/Y', $endDateStr);
            } catch (\Exception $e) {
                $this->command->warn("Invalid date format for line: {$line}");
                continue;
            }

            DeliveryCalendar::updateOrCreate(
                [
                    'year' => $year,
                    'week_number' => $weekNumber,
                    'cycle' => $cycle,
                ],
                [
                    'month' => $month,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                ]
            );

            $count++;
        }

        $this->command->info("Seeded {$count} delivery calendar entries");
    }
}
