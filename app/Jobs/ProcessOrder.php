<?php

namespace App\Jobs;

use App\Models\Order;
use App\Repositories\OrderRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Order $order
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Processing order {$this->order->id} via queue job", [
            'order_id' => $this->order->id,
            'attempt' => $this->attempts()
        ]);

        try {
            // Retry XML transmission
            OrderRepository::retryXmlTransmission($this->order);

            Log::info("Order {$this->order->id} processed successfully via queue job");
        } catch (\Throwable $exception) {
            Log::error("Failed to process order {$this->order->id} via queue job", [
                'order_id' => $this->order->id,
                'attempt' => $this->attempts(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);

            if ($this->attempts() >= $this->tries) {
                // Mark order as failed after all retries
                $this->order->update([
                    'status_id' => Order::STATUS_PENDING,
                    'response' => 'XML transmission failed after ' . $this->tries . ' attempts: ' . $exception->getMessage()
                ]);

                Log::error("Order {$this->order->id} failed after {$this->tries} attempts");
                throw $exception;
            }

            // Release job for retry with backoff
            $this->release($this->backoff[$this->attempts() - 1] ?? 900);
        }
    }
}
