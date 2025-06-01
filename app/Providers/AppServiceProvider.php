<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Contracts\RoomServiceInterface::class, \App\Services\RoomService::class);
        $this->app->bind(\App\Contracts\Room\CreateRoomContract::class,\App\Services\Rooms\Actions\CreateRoomAction::class);
        $this->app->bind(\App\Contracts\Room\UpdateRoomContract::class,\App\Services\Rooms\Actions\UpdateRoomAction::class);
        $this->app->bind(\App\Contracts\Room\DeleteRoomContract::class,\App\Services\Rooms\Actions\DeleteRoomAction::class);
        $this->app->bind(\App\Contracts\Room\UpdateStatusContract::class,\App\Services\Rooms\Actions\UpdateStatusAction::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
