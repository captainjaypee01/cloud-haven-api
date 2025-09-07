<?php

namespace App\Providers;

use App\Contracts\Repositories\DayTourPricingRepositoryInterface;
use App\Contracts\Services\DayTourPricingServiceInterface;
use App\Repositories\DayTourPricingRepository;
use App\Services\DayTourPricingService;
use Illuminate\Support\ServiceProvider;

class DayTourPricingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind Repository Interface to Implementation
        $this->app->bind(
            DayTourPricingRepositoryInterface::class,
            DayTourPricingRepository::class
        );

        // Bind Service Interface to Implementation
        $this->app->bind(
            DayTourPricingServiceInterface::class,
            DayTourPricingService::class
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}