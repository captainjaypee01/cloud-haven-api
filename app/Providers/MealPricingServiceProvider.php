<?php

namespace App\Providers;

use App\Contracts\Repositories\MealCalendarOverrideRepositoryInterface;
use App\Contracts\Repositories\MealPricingTierRepositoryInterface;
use App\Contracts\Repositories\MealProgramRepositoryInterface;
use App\Contracts\Services\DayTourServiceInterface;
use App\Contracts\Services\MealCalendarServiceInterface;
use App\Contracts\Services\MealPricingServiceInterface;
use App\Repositories\MealCalendarOverrideRepository;
use App\Repositories\MealPricingTierRepository;
use App\Repositories\MealProgramRepository;
use App\Services\DayTourService;
use App\Services\MealCalendarService;
use App\Services\MealPricingService;
use Illuminate\Support\ServiceProvider;

class MealPricingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind repository interfaces
        $this->app->bind(MealProgramRepositoryInterface::class, MealProgramRepository::class);
        $this->app->bind(MealPricingTierRepositoryInterface::class, MealPricingTierRepository::class);
        $this->app->bind(MealCalendarOverrideRepositoryInterface::class, MealCalendarOverrideRepository::class);

        // Bind service interfaces
        $this->app->bind(MealCalendarServiceInterface::class, MealCalendarService::class);
        $this->app->bind(MealPricingServiceInterface::class, MealPricingService::class);
        $this->app->bind(DayTourServiceInterface::class, DayTourService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
