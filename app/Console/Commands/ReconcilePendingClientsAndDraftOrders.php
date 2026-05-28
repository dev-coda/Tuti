<?php

namespace App\Console\Commands;

use App\Services\DraftOrderReconciliationService;
use Illuminate\Console\Command;

class ReconcilePendingClientsAndDraftOrders extends Command
{
    protected $signature = 'clients:reconcile-pending-drafts';

    protected $description = 'Sync pending clients via getRuteros, promote to cliente, and transmit draft orders when valid';

    public function handle(DraftOrderReconciliationService $service): int
    {
        $stats = $service->reconcileAll();

        $this->info(sprintf(
            'Reconciliation complete. users_checked=%d users_promoted=%d drafts_attempted=%d queued=%d waiting=%d failed=%d',
            $stats['users_checked'],
            $stats['users_promoted'],
            $stats['drafts_attempted'],
            $stats['drafts_queued'],
            $stats['drafts_waiting'],
            $stats['drafts_failed'],
        ));

        return self::SUCCESS;
    }
}
