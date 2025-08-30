<?php

namespace App\Providers;

use App\Contracts\Mail\MailServiceInterface;
use App\Services\Mail\MailService;
use Illuminate\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(MailServiceInterface::class, MailService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
