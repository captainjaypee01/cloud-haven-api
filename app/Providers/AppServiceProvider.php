<?php

namespace App\Providers;

use App\Services\WebhookVerifier;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
// Use the Configuration class 
use Cloudinary\Configuration\Configuration as CloudinaryConfiguration;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Svix Webhok
        $this->app->bind(WebhookVerifier::class, function ($app) {
            $secret = config('services.clerk.webhook_secret_key');
            return new WebhookVerifier(new \Svix\Webhook($secret));
        });

        // Admin Room
        $this->app->bind(\App\Contracts\Repositories\RoomRepositoryInterface::class, \App\Repositories\RoomRepository::class);
        $this->app->bind(\App\Contracts\Services\RoomServiceInterface::class, \App\Services\RoomService::class);
        $this->app->bind(\App\Contracts\Room\CreateRoomContract::class, \App\Services\Rooms\Actions\CreateRoomAction::class);
        $this->app->bind(\App\Contracts\Room\UpdateRoomContract::class, \App\Services\Rooms\Actions\UpdateRoomAction::class);
        $this->app->bind(\App\Contracts\Room\DeleteRoomContract::class, \App\Services\Rooms\Actions\DeleteRoomAction::class);
        $this->app->bind(\App\Contracts\Room\UpdateStatusContract::class, \App\Services\Rooms\Actions\UpdateStatusAction::class);

        // Admin Amenity
        $this->app->bind(\App\Contracts\Repositories\AmenityRepositoryInterface::class, \App\Repositories\AmenityRepository::class);
        $this->app->bind(\App\Contracts\Services\AmenityServiceInterface::class, \App\Services\Amenities\AmenityService::class);
        $this->app->bind(\App\Contracts\Amenities\CreateAmenityContract::class, \App\Services\Amenities\Actions\CreateAmenityAction::class);
        $this->app->bind(\App\Contracts\Amenities\UpdateAmenityContract::class, \App\Services\Amenities\Actions\UpdateAmenityAction::class);
        $this->app->bind(\App\Contracts\Amenities\DeleteAmenityContract::class, \App\Services\Amenities\Actions\DeleteAmenityAction::class);

        $this->app->bind(\App\Contracts\Services\BookingLockServiceInterface::class, \App\Services\Bookings\BookingLockService::class);
        $this->app->bind(\App\Contracts\Services\BookingServiceInterface::class, \App\Services\Bookings\BookingService::class);


        $this->app->bind(\App\Contracts\Services\PaymentGatewayInterface::class, \App\Actions\Payments\SimulatePaymentAction::class); // For simulation
        $this->app->bind(\App\Contracts\Services\PaymentServiceInterface::class, \App\Services\Payments\PaymentService::class);
        $this->app->bind(\App\Contracts\Repositories\PaymentRepositoryInterface::class, \App\Repositories\PaymentRepository::class);

        $this->app->bind(\App\Contracts\Repositories\BookingRepositoryInterface::class, \App\Repositories\BookingRepository::class);

        $this->app->bind(
            \App\Contracts\Services\ImageServiceInterface::class,
            \App\Services\Images\ImageService::class
        );

        $this->bindUsers();
        $this->bindMealPrices();
        $this->bindPromos();
        $this->app->bind(
            \App\Contracts\Services\DashboardServiceInterface::class,
            \App\Services\DashboardService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        
        CloudinaryConfiguration::instance(config('cloudinary.cloud_url') . "?secure=true");
    }

    public function bindUsers(): void
    {
        // Admin User
        $this->app->bind(\App\Contracts\Services\UserServiceInterface::class, \App\Services\UserService::class);
        $this->app->bind(\App\Contracts\Repositories\UserRepositoryInterface::class, \App\Repositories\UserRepository::class);
        $this->app->bind(\App\Contracts\Users\CreateUserContract::class, \App\Services\Users\Actions\CreateUserAction::class);
        $this->app->bind(\App\Contracts\Users\UpdateUserContract::class, \App\Services\Users\Actions\UpdateUserAction::class);
        $this->app->bind(\App\Contracts\Users\SyncLinkedProvidersContract::class, \App\Services\Users\Actions\SyncLinkedProvidersAction::class);
        $this->app->bind(\App\Contracts\Users\DeleteUserContract::class, \App\Services\Users\Actions\DeleteUserAction::class);
        $this->app->bind(\App\Contracts\Users\CreateClerkUserContract::class, \App\Services\Users\Actions\CreateClerkUserAction::class);
        $this->app->bind(\App\Contracts\Users\UpdateClerkUserContract::class, \App\Services\Users\Actions\UpdateClerkUserAction::class);
        $this->app->bind(\App\Contracts\Users\DeleteClerkUserContract::class, \App\Services\Users\Actions\DeleteClerkUserAction::class);
    }
    public function bindPromos(): void
    {

        $this->app->bind(\App\Contracts\Repositories\PromoRepositoryInterface::class, \App\Repositories\PromoRepository::class);
        $this->app->bind(\App\Contracts\Services\PromoServiceInterface::class, \App\Services\Promos\PromoService::class);
        $this->app->bind(\App\Contracts\Promos\CreatePromoContract::class, \App\Actions\Promos\CreatePromoAction::class);
        $this->app->bind(\App\Contracts\Promos\UpdatePromoContract::class, \App\Actions\Promos\UpdatePromoAction::class);
        $this->app->bind(\App\Contracts\Promos\DeletePromoContract::class, \App\Actions\Promos\DeletePromoAction::class);
    }

    public function bindMealPrices(): void
    {
        $this->app->bind(\App\Contracts\Services\MealPriceServiceInterface::class, \App\Services\MealPrices\MealPriceService::class);
        $this->app->bind(\App\Contracts\Repositories\MealPriceRepositoryInterface::class, \App\Repositories\MealPriceRepository::class);
        $this->app->bind(\App\Contracts\MealPrices\CreateMealPriceContract::class, \App\Actions\MealPrices\CreateMealPriceAction::class);
        $this->app->bind(\App\Contracts\MealPrices\UpdateMealPriceContract::class, \App\Actions\MealPrices\UpdateMealPriceAction::class);
        $this->app->bind(\App\Contracts\MealPrices\DeleteMealPriceContract::class, \App\Actions\MealPrices\DeleteMealPriceAction::class);
    }
}
