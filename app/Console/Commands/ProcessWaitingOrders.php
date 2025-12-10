<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Jobs\ProcessOrderAsync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessWaitingOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:process-waiting';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process orders that are waiting for their scheduled transmission date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        
        $this->info("Processing waiting orders for date: {$today->format('Y-m-d')}");

        // Find orders that are waiting and their scheduled date has arrived
        $waitingOrders = Order::where('status_id', Order::STATUS_WAITING)
            ->whereNotNull('scheduled_transmission_date')
            ->whereDate('scheduled_transmission_date', '<=', $today)
            ->get();

        if ($waitingOrders->isEmpty()) {
            $this->info('No waiting orders found to process.');
            return 0;
        }

        $this->info("Found {$waitingOrders->count()} waiting order(s) to process.");

        $processed = 0;
        $failed = 0;

        foreach ($waitingOrders as $order) {
            try {
                // Update status to pending before dispatching
                $order->update(['status_id' => Order::STATUS_PENDING]);
                
                // Determine queue connection
                $queueConnection = config('queue.default');
                if ($queueConnection === 'sync') {
                    $queueConnection = 'database';
                }

                // Dispatch job to process the order
                ProcessOrderAsync::dispatch($order)->onConnection($queueConnection);
                
                $this->info("Dispatched processing for order #{$order->id}");
                $processed++;
            } catch (\Exception $e) {
                $this->error("Failed to dispatch order #{$order->id}: " . $e->getMessage());
                Log::error("Failed to process waiting order {$order->id}", [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->info("Processed: {$processed}, Failed: {$failed}");
        
        return 0;
    }
}
