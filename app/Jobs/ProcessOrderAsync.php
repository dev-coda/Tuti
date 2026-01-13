<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use App\Repositories\OrderRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessOrderAsync implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     * After 3 attempts with lengthy intervals, the order is marked as failed.
     */
    public $tries = 3;

    /**
     * Retry intervals: 5 minutes, 30 minutes, 2 hours
     * These lengthy intervals help handle temporary API/network issues
     */
    public $backoff = [300, 1800, 7200]; // 5 min, 30 min, 2 hours

    /**
     * The maximum number of seconds the job should run before timing out.
     * Prevents jobs from hanging indefinitely.
     */
    public $timeout = 120; // 2 minutes

    /**
     * The number of seconds after which the job's unique lock will be released.
     * Set to 3 hours to allow all retries to complete.
     */
    public $uniqueFor = 10800; // 3 hours

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
        // Increment processing attempts tracker
        $this->order->incrementProcessingAttempts();

        Log::info("Starting async order processing for order {$this->order->id}", [
            'order_id' => $this->order->id,
            'attempt' => $this->attempts(),
            'processing_attempts' => $this->order->processing_attempts,
            'manually_retried' => $this->order->manually_retried
        ]);

        // Refresh order from database to get latest status
        // Load relationships needed for email sending
        $this->order->refresh();
        $this->order->load(['products.product', 'user', 'zone']);

        // Skip if already successfully processed
        if ($this->order->status_id === Order::STATUS_PROCESSED) {
            Log::info("Order {$this->order->id} already processed, skipping");
            return;
        }

        // Check if order is waiting for scheduled transmission date
        // BUT: Skip waiting if force delivery date is enabled (emergency override)
        $forceDeliveryDate = \App\Models\Setting::getByKey('force_delivery_date_enabled') == '1';
        
        if ($this->order->status_id === Order::STATUS_WAITING && $this->order->scheduled_transmission_date && !$forceDeliveryDate) {
            $scheduledDate = \Carbon\Carbon::parse($this->order->scheduled_transmission_date);
            $today = \Carbon\Carbon::today();
            
            if ($scheduledDate->gt($today)) {
                Log::info("Order {$this->order->id} is waiting for scheduled transmission date {$this->order->scheduled_transmission_date}, skipping");
                // Release job to retry tomorrow
                $this->release(86400); // 24 hours
                return;
            }
            
            // Scheduled date has arrived, update status to pending
            $this->order->update(['status_id' => Order::STATUS_PENDING]);
            Log::info("Order {$this->order->id} scheduled transmission date has arrived, processing now");
        } elseif ($this->order->status_id === Order::STATUS_WAITING && $forceDeliveryDate) {
            // Force delivery date is active - bypass waiting and process immediately
            $this->order->update(['status_id' => Order::STATUS_PENDING]);
            Log::warning("Order {$this->order->id} was WAITING but Force Delivery Date is ACTIVE - processing immediately", [
                'original_scheduled_date' => $this->order->scheduled_transmission_date,
                'force_delivery_date_enabled' => true
            ]);
        }

        try {
            // Step 1: Process XML transmission
            OrderRepository::presalesOrder($this->order);

            Log::info("XML transmission completed for order {$this->order->id}");

            // Step 2: Dispatch email jobs asynchronously (non-blocking)
            // This ensures order processing completes even if emails fail
            try {
                // Determine queue connection
                $queueConnection = config('queue.default');
                if ($queueConnection === 'sync') {
                    $queueConnection = 'database';
                }

                // Dispatch order confirmation email job
                SendOrderEmail::dispatch($this->order, 'confirmation')
                    ->onConnection($queueConnection)
                    ->onQueue('emails');

                // Dispatch order status email job (processed)
                SendOrderEmail::dispatch($this->order, 'status', 'processed')
                    ->onConnection($queueConnection)
                    ->onQueue('emails');

                Log::info("Email jobs dispatched for order {$this->order->id}", [
                    'queue_connection' => $queueConnection,
                ]);
            } catch (\Exception $e) {
                // Don't fail the job if email dispatch fails
                // Emails are not critical to order processing
                Log::error("Failed to dispatch email jobs for order {$this->order->id}: " . $e->getMessage(), [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
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
     * Called when all retry attempts have been exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical("ProcessOrderAsync job permanently failed for order {$this->order->id}", [
            'order_id' => $this->order->id,
            'user_id' => $this->order->user_id,
            'total' => $this->order->total,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'created_at' => $this->order->created_at,
            'attempts_made' => $this->tries
        ]);

        // Update order with detailed failure information
        $this->order->update([
            'status_id' => Order::STATUS_ERROR_WEBSERVICE,
            'response' => sprintf(
                'Processing failed permanently after %d attempts over %s. Last error: %s',
                $this->tries,
                $this->formatRetryDuration(),
                $exception->getMessage()
            )
        ]);

        // Send admin notification about failed order processing
        $this->sendAdminFailureNotification($exception);
    }

    /**
     * Send notification to admins about permanently failed order
     */
    private function sendAdminFailureNotification(\Throwable $exception): void
    {
        try {
            $adminEmails = User::where('role', 'admin')
                ->orWhere('email', 'like', '%@admin.%')
                ->pluck('email')
                ->toArray();

            if (empty($adminEmails)) {
                Log::warning("No admin emails found to notify about failed order {$this->order->id}");
                return;
            }

            $subject = "⚠️ Order #{$this->order->id} Failed After Multiple Retries";
            $message = sprintf(
                "Order #%d failed to process after %d attempts.\n\n" .
                    "Order Details:\n" .
                    "- Order ID: %d\n" .
                    "- Customer: %s (ID: %d)\n" .
                    "- Total: $%s\n" .
                    "- Created: %s\n" .
                    "- Retry Intervals: 5 min, 30 min, 2 hours\n\n" .
                    "Error: %s\n\n" .
                    "Action Required: Please check the order in the admin panel and process it manually.",
                $this->order->id,
                $this->tries,
                $this->order->id,
                $this->order->user->name ?? 'Unknown',
                $this->order->user_id,
                number_format($this->order->total, 2),
                $this->order->created_at->format('Y-m-d H:i:s'),
                $exception->getMessage()
            );

            foreach ($adminEmails as $email) {
                \Mail::raw($message, function ($mail) use ($email, $subject) {
                    $mail->to($email)
                        ->subject($subject);
                });
            }

            Log::info("Admin notifications sent for failed order {$this->order->id}", [
                'recipients' => $adminEmails
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to send admin notification for order {$this->order->id}: " . $e->getMessage());
        }
    }

    /**
     * Format the total retry duration for human readability
     */
    private function formatRetryDuration(): string
    {
        $totalSeconds = array_sum($this->backoff);
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);

        $parts = [];
        if ($hours > 0) $parts[] = "{$hours} hour" . ($hours > 1 ? 's' : '');
        if ($minutes > 0) $parts[] = "{$minutes} minute" . ($minutes > 1 ? 's' : '');

        return implode(' and ', $parts) ?: 'less than a minute';
    }
}
