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

    public $tries = 3;
    public $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Order $order,
        protected string $emailType,
        protected ?string $status = null
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Sending {$this->emailType} email for order {$this->order->id}");

        try {
            $mailingService = app(MailingService::class);

            if ($this->emailType === 'confirmation') {
                $mailingService->sendOrderConfirmationEmail($this->order);
            } elseif ($this->emailType === 'status' && $this->status) {
                $mailingService->sendOrderStatusEmail($this->order, $this->status);
            }

            Log::info("Email sent successfully for order {$this->order->id}");
        } catch (\Exception $e) {
            Log::error("Failed to send {$this->emailType} email for order {$this->order->id}: " . $e->getMessage());

            // Don't fail the job, just log the error
            // Emails are not critical to order processing
        }
    }
}
