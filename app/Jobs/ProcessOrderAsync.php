<?php

namespace App\Jobs;

use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\MailingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOrderAsync implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public $uniqueFor = 3600; // 1 hour

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Order $order
    ) {
        //
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return 'order-' . $this->order->id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting async order processing for order {$this->order->id}", [
            'order_id' => $this->order->id,
            'attempt' => $this->attempts()
        ]);

        // Refresh order from database to get latest status
        $this->order->refresh();

        // Skip if already successfully processed
        if ($this->order->status_id === Order::STATUS_PROCESSED) {
            Log::info("Order {$this->order->id} already processed, skipping");
            return;
        }

        try {
            // Step 1: Process XML transmission
            OrderRepository::presalesOrder($this->order);

            Log::info("XML transmission completed for order {$this->order->id}");

            // Step 2: Send emails after successful XML transmission
            try {
                $mailingService = app(MailingService::class);

                // Send order confirmation email
                $mailingService->sendOrderConfirmationEmail($this->order);

                // Send order status email (processed)
                $mailingService->sendOrderStatusEmail($this->order, 'processed');

                Log::info("Emails sent successfully for order {$this->order->id}");
            } catch (\Exception $e) {
                // Don't fail the job if email sending fails
                // Emails are not critical to order processing
                Log::error("Email sending failed for order {$this->order->id}: " . $e->getMessage());
            }

            Log::info("Order {$this->order->id} processed successfully via async job");
        } catch (\Throwable $exception) {
            Log::error("Failed to process order {$this->order->id} via async job", [
                'order_id' => $this->order->id,
                'attempt' => $this->attempts(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString()
            ]);

            if ($this->attempts() >= $this->tries) {
                // Mark order as failed after all retries
                $this->order->update([
                    'status_id' => Order::STATUS_ERROR_WEBSERVICE,
                    'response' => 'Async processing failed after ' . $this->tries . ' attempts: ' . $exception->getMessage()
                ]);

                Log::error("Order {$this->order->id} failed after {$this->tries} attempts");
                throw $exception;
            }

            // Release job for retry with backoff
            $this->release($this->backoff[$this->attempts() - 1] ?? 900);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessOrderAsync job completely failed for order {$this->order->id}", [
            'order_id' => $this->order->id,
            'error' => $exception->getMessage()
        ]);

        // Send admin notification about failed order processing
        // You can implement admin notification here if needed
    }
}
