<?php

namespace App\Console\Commands;

use App\Jobs\BulkSyncClientsData;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncClientsRuteroDaily extends Command
{
    protected $signature = 'clients:sync-rutero-daily';

    protected $description = 'Queue bulk Dynamics (getRuteros) sync for all client users with document (nightly job)';

    public function handle(): int
    {
        $enabled = Setting::getByKeyWithDefault('daily_client_rutero_sync_enabled', '1');
        if ($enabled !== '1' && $enabled !== 1 && $enabled !== true) {
            $this->info('Daily client rutero sync is disabled (setting daily_client_rutero_sync_enabled).');

            return self::SUCCESS;
        }

        $userIds = User::query()
            ->whereDoesntHave('roles')
            ->whereNotNull('document')
            ->where('document', '!=', '')
            ->pluck('id')
            ->all();

        if ($userIds === []) {
            $this->warn('No client users with document to sync.');

            return self::SUCCESS;
        }

        $sessionId = 'daily-' . now()->format('Ymd-His') . '-' . Str::random(8);

        $queueConnection = config('queue.default');
        if ($queueConnection === 'sync') {
            $queueConnection = 'database';
        }

        BulkSyncClientsData::dispatch($userIds, $sessionId)
            ->onConnection($queueConnection)
            ->onQueue('default');

        $this->info('Dispatched rutero sync for ' . count($userIds) . ' clients. Session: ' . $sessionId);

        return self::SUCCESS;
    }
}
