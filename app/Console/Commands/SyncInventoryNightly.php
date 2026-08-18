<?php

namespace App\Console\Commands;

use App\Jobs\SyncProductInventory;
use App\Models\Setting;
use App\Services\MicrosoftTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncInventoryNightly extends Command
{
    protected $signature = 'inventory:sync
                            {--sync : Run the job synchronously instead of queueing}
                            {--force : Ignore inventory_sync_enabled and run anyway}';

    protected $description = 'Refresh Microsoft token and queue (or run) product inventory sync from Dynamics';

    public function handle(): int
    {
        $syncEnabled = Setting::getByKeyWithDefault('inventory_sync_enabled', '1');
        $inventoryEnabled = Setting::getByKeyWithDefault('inventory_enabled', '1');

        if (! $this->option('force') && ! $this->settingIsOn($syncEnabled)) {
            $this->info('Inventory autosync skipped (inventory_sync_enabled is off).');
            Log::info('Inventory autosync skipped - inventory_sync_enabled is off');

            return self::SUCCESS;
        }

        if (! $this->settingIsOn($inventoryEnabled)) {
            $this->info('Inventory autosync skipped (inventory_enabled is off).');
            Log::info('Inventory autosync skipped - inventory_enabled is off');

            return self::SUCCESS;
        }

        // Refresh in the scheduler/CLI process (same context as app:get-token).
        // Horizon workers historically failed OAuth with a different runtime context,
        // so the queued job only reads the stored token.
        try {
            MicrosoftTokenService::refresh();
            $this->info('Microsoft token refreshed.');
        } catch (\Throwable $e) {
            Log::error('Inventory autosync aborted: token refresh failed', [
                'error' => $e->getMessage(),
            ]);
            $this->recordProgress(
                'error',
                'No se pudo renovar el token de Microsoft.',
                $e->getMessage(),
                finished: true
            );
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $this->recordProgress('running', 'Sincronización nocturna iniciada (síncrona).');
            (new SyncProductInventory)->handle();
            $this->info('Inventory sync completed synchronously.');

            return self::SUCCESS;
        }

        $queueConnection = config('queue.default');
        if ($queueConnection === 'sync') {
            $queueConnection = 'redis';
        }

        $this->recordProgress('queued', 'Sincronización nocturna enviada a la cola.');

        SyncProductInventory::dispatch()
            ->onConnection($queueConnection)
            ->onQueue('inventory');

        Log::info('Inventory autosync dispatched', [
            'connection' => $queueConnection,
            'queue' => 'inventory',
        ]);
        $this->info("Inventory sync dispatched on {$queueConnection}:inventory.");

        return self::SUCCESS;
    }

    private function settingIsOn(mixed $value): bool
    {
        return $value === '1' || $value === 1 || $value === true;
    }

    private function recordProgress(string $status, string $message, ?string $errorMessage = null, bool $finished = false): void
    {
        $now = now()->toDateTimeString();

        Setting::updateOrCreate(
            ['key' => 'inventory_sync_progress'],
            [
                'name' => 'Inventario - progreso de sincronización',
                'value' => json_encode([
                    'status' => $status,
                    'message' => $message,
                    'current_bodega' => null,
                    'processed_bodegas' => 0,
                    'total_bodegas' => 0,
                    'percentage' => 0,
                    'error_message' => $errorMessage,
                    'started_at' => $now,
                    'updated_at' => $now,
                    'finished_at' => $finished ? $now : null,
                ]),
                'show' => false,
            ]
        );
    }
}
