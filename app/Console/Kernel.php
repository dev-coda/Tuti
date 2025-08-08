<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\UpdateProductPrices;
use App\Models\Setting;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('inspire')->hourly();
        //comand every 20 minutes
        $schedule->command('app:get-token')->cron('*/20 * * * *');

        // Auto-updater (daily) guarded by settings toggle
        $schedule->call(function () {
            $enabled = Setting::getByKey('auto_updater_enabled');
            if ($enabled === '1' || $enabled === 1 || $enabled === true) {
                UpdateProductPrices::dispatch();
            }
        })->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
