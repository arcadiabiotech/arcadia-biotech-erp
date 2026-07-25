<?php

namespace App\Providers;

use App\Contracts\SmsProviderInterface;
use App\Services\Sms\SmsManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsManager::class);

        // Resolves to whichever driver SMS_PROVIDER (.env) selects, so
        // anything type-hinting SmsProviderInterface never needs to know
        // which concrete provider is active.
        $this->app->bind(SmsProviderInterface::class, fn ($app) => $app->make(SmsManager::class)->driver());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
