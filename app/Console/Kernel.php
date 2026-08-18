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

        // Nightly inventory sync: refresh Microsoft token in CLI, then queue the job.
        // Guards (inventory_sync_enabled / inventory_enabled) live in inventory:sync.
        $schedule->command('inventory:sync')
            ->dailyAt('02:30')
            ->withoutOverlapping()
            ->runInBackground();

        // Nightly product dimension sync from Dynamics ObtenerArticulos
        // (guarded inside the job by setting dimension_sync_enabled)
        $schedule->call(function () {
            $queueConnection = config('queue.default');
            if ($queueConnection === 'sync') {
                $queueConnection = 'redis';
            }

            \App\Jobs\SyncProductDimensions::dispatch()
                ->onConnection($queueConnection)
                ->onQueue('inventory');
        })->dailyAt('03:00');

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

        // Client profiles from Dynamics (getRuteros): phones, balances, email, zones — guarded by setting
        $schedule->command('clients:sync-rutero-daily')
            ->dailyAt('03:20')
            ->withoutOverlapping()
            ->runInBackground();

        // Promote pending clients after rutero sync and transmit draft orders
        $schedule->command('clients:reconcile-pending-drafts')
            ->dailyAt('03:45')
            ->withoutOverlapping()
            ->runInBackground();

        // Periodic per-zone rutero sync (getRuteros by zone): refreshes zona/ruta/día and
        // provisions missing Dynamics clients/sucursales by CustRuteroID — guarded by setting
        $schedule->command('clients:sync-zone-ruteros')
            ->dailyAt('04:15')
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
