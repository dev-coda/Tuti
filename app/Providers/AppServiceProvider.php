<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MailingService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Initialize mail configuration from database settings
        $mailingService = app(MailingService::class);
        $mailingService->updateMailConfiguration();
    }

    //app url

}
