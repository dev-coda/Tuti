<?php

namespace App\Providers;

use App\Models\ContentPage;
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

        view()->composer('elements.footer', function ($view) {
            $footerContentPages = ContentPage::query()
                ->enabled()
                ->shownInFooter()
                ->orderBy('title')
                ->get();

            $view->with('footerContentPages', $footerContentPages);
        });
    }

    //app url

}
