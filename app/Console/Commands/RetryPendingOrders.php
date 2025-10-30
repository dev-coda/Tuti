<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOrderAsync;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryPendingOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:retry-pending 
                            {--hours=1 : Retry orders older than this many hours}
                            {--max=10 : Maximum number of orders to retry in one run}
                            {--dry-run : Show what would be retried without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry processing of orders that have been stuck in pending status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hoursOld = (int) $this->option('hours');
        $maxOrders = (int) $this->option('max');
        $dryRun = $this->option('dry-run');

        $this->info("Looking for pending orders older than {$hoursOld} hour(s)...");

        // Find orders that are:
        // 1. Still in pending status
        // 2. Created more than X hours ago
        // 3. Not already being processed (no active job)
        $cutoffTime = now()->subHours($hoursOld);

        $pendingOrders = Order::where('status_id', Order::STATUS_PENDING)
            ->where('created_at', '<', $cutoffTime)
            ->orderBy('created_at', 'asc')
            ->limit($maxOrders)
            ->get();

        if ($pendingOrders->isEmpty()) {
            $this->info('No stuck pending orders found.');
            Log::info('RetryPendingOrders: No stuck orders found', [
                'cutoff_time' => $cutoffTime,
                'hours_old' => $hoursOld
            ]);
            return 0;
        }

        $this->info("Found {$pendingOrders->count()} stuck pending order(s):");

        $table = [];
        foreach ($pendingOrders as $order) {
            $ageHours = $order->created_at->diffInHours(now());
            $table[] = [
                'ID' => $order->id,
                'Created' => $order->created_at->format('Y-m-d H:i:s'),
                'Age (hours)' => $ageHours,
                'Total' => '$' . number_format($order->total, 2),
                'User ID' => $order->user_id
            ];
        }

        $this->table(
            ['ID', 'Created', 'Age (hours)', 'Total', 'User ID'],
            $table
        );

        if ($dryRun) {
            $this->warn('DRY RUN: No orders will be retried.');
            return 0;
        }

        if (!$this->confirm('Do you want to retry these orders?', true)) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $this->info('Dispatching retry jobs...');
        $successCount = 0;
        $failCount = 0;

        foreach ($pendingOrders as $order) {
            try {
                // Mark order as manually retried
                $order->markAsManuallyRetried();

                // Dispatch the async job to retry this order
                ProcessOrderAsync::dispatch($order)
                    ->onQueue(config('queue.default_queue', 'default'));

                $this->line("✓ Queued retry for order #{$order->id}");
                $successCount++;

                Log::info("Manually retrying stuck order", [
                    'order_id' => $order->id,
                    'age_hours' => $order->created_at->diffInHours(now()),
                    'created_at' => $order->created_at,
                    'processing_attempts' => $order->processing_attempts,
                    'triggered_by' => 'retry-pending-orders command'
                ]);
            } catch (\Exception $e) {
                $this->error("✗ Failed to queue order #{$order->id}: {$e->getMessage()}");
                $failCount++;

                Log::error("Failed to dispatch retry job for stuck order", [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->newLine();
        $this->info("Retry dispatch complete:");
        $this->line("  • Success: {$successCount}");
        if ($failCount > 0) {
            $this->line("  • Failed: {$failCount}");
        }

        Log::info('RetryPendingOrders command completed', [
            'total_found' => $pendingOrders->count(),
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'hours_old' => $hoursOld
        ]);

        return 0;
    }
}
