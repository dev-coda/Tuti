<?php

namespace App\Listeners;

use App\Events\Registered;
use App\Services\MailingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendWelcomeEmail implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct(
        private MailingService $mailingService
    ) {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        try {
            $user = $event->user;

            // Only send email for new users with pending status
            if ($user->status_id === \App\Models\User::PENDING) {
                $this->mailingService->sendUserRegistrationEmail($user);
                Log::info("Welcome email sent to user: {$user->email}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to send welcome email to user: {$event->user->email}", [
                'error' => $e->getMessage(),
                'user_id' => $event->user->id,
            ]);

            // Re-throw to mark the job as failed
            throw $e;
        }
    }
}
