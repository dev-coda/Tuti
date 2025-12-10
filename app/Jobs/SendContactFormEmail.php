<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Services\MailingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendContactFormEmail implements ShouldQueue
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
        protected Contact $contact
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Sending contact form email for contact {$this->contact->id}");

        try {
            // Refresh contact to ensure we have latest data
            $this->contact->refresh();
            
            // Load necessary relationships
            if (!$this->contact->relationLoaded('city')) {
                $this->contact->load('city');
            }

            $mailingService = app(MailingService::class);
            $result = $mailingService->sendContactFormNotification($this->contact);

            if ($result) {
                Log::info("Contact form email sent successfully for contact {$this->contact->id}");
            } else {
                Log::warning("Contact form email sending returned false for contact {$this->contact->id}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to send contact form email for contact {$this->contact->id}: " . $e->getMessage(), [
                'contact_id' => $this->contact->id,
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
        Log::critical("SendContactFormEmail job permanently failed for contact {$this->contact->id}", [
            'contact_id' => $this->contact->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
