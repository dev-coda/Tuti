<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\MailingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * Retry intervals: 1 minute, 5 minutes, 15 minutes
     */
    public $backoff = [60, 300, 900];

    /**
     * The maximum number of seconds the job should run before timing out.
     */
    public $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Order $order,
        protected string $emailType, // 'confirmation' or 'status'
        protected ?string $status = null // Required if emailType is 'status'
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Sending {$this->emailType} email for order {$this->order->id}", [
            'order_id' => $this->order->id,
            'email_type' => $this->emailType,
            'status' => $this->status,
        ]);

        try {
            // Refresh order to ensure we have latest data
            $this->order->refresh();
            
            // Load necessary relationships
            if (!$this->order->relationLoaded('user')) {
                $this->order->load('user');
            }
            if ($this->emailType === 'confirmation' && !$this->order->relationLoaded('products')) {
                $this->order->load('products.product');
            }

            $mailingService = app(MailingService::class);

            if ($this->emailType === 'confirmation') {
                $result = $mailingService->sendOrderConfirmationEmail($this->order);
            } elseif ($this->emailType === 'status' && $this->status) {
                $result = $mailingService->sendOrderStatusEmail($this->order, $this->status);
            } else {
                Log::error("Invalid email type or missing status for order {$this->order->id}", [
                    'email_type' => $this->emailType,
                    'status' => $this->status,
                ]);
                return;
            }

            if ($result) {
                Log::info("Email sent successfully for order {$this->order->id}", [
                    'email_type' => $this->emailType,
                ]);
            } else {
                Log::warning("Email sending returned false for order {$this->order->id}", [
                    'email_type' => $this->emailType,
                ]);
                // Don't throw exception - just log and let job complete
                // This prevents retries for non-critical email failures
            }
        } catch (\Exception $e) {
            Log::error("Failed to send {$this->emailType} email for order {$this->order->id}: " . $e->getMessage(), [
                'order_id' => $this->order->id,
                'email_type' => $this->emailType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical("SendOrderEmail job permanently failed for order {$this->order->id}", [
            'order_id' => $this->order->id,
            'email_type' => $this->emailType,
            'status' => $this->status,
            'error' => $exception->getMessage(),
        ]);
    }
}
