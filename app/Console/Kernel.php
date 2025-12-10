<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\UpdateProductPrices;
use App\Jobs\SyncProductInventory;
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

        // Nightly inventory sync (guarded by setting inventory_sync_enabled)
        $schedule->call(function () {
            $syncEnabled = Setting::getByKeyWithDefault('inventory_sync_enabled', '1');
            $inventoryEnabled = Setting::getByKeyWithDefault('inventory_enabled', '1');
            if (($syncEnabled === '1' || $syncEnabled === 1 || $syncEnabled === true) && ($inventoryEnabled === '1' || $inventoryEnabled === 1 || $inventoryEnabled === true)) {
                $queueConnection = config('queue.default');
                // If queue is set to 'sync', use 'redis' instead to ensure async processing with Horizon
                if ($queueConnection === 'sync') {
                    $queueConnection = 'redis';
                }
                
                SyncProductInventory::dispatch()
                    ->onConnection($queueConnection)
                    ->onQueue('inventory');
            }
        })->dailyAt('02:30');

        // Retry stuck pending orders every hour
        // This catches orders that failed to process and ensures they get retried
        $schedule->command('orders:retry-pending --hours=2 --max=20')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        // Cleanup expired reports daily
        $schedule->command('reports:cleanup-expired')
            ->daily()
            ->withoutOverlapping();

        // Process waiting orders daily (orders scheduled for transmission)
        $schedule->command('orders:process-waiting')
            ->daily()
            ->withoutOverlapping()
            ->runInBackground();
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
