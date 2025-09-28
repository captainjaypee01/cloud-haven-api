<?php

use Illuminate\Support\Facades\Route;

// Base admin routes with authentication
Route::prefix('admin')->namespace('App\Http\Controllers\API\V1\Admin')
    ->middleware(['clerk.auth:api'])
    ->group(function () {
        
        // Test route
        Route::get('clerk/test', fn() => 'Clerk Middleware Check Admin | ' . auth()->user()->clerk_id);
        
        // Staff, Admin, Superadmin routes
        Route::middleware(['role:staff,admin,superadmin'])->group(function () {
            Route::get('dashboard', 'DashboardController@index');
            
            // Bookings - Staff can view, Admin/Superadmin can manage
            Route::get('bookings/calendar', 'BookingController@calendar');
            Route::get('bookings', 'BookingController@index');
            Route::get('bookings/{booking}', 'BookingController@show');
            
            // Room Units Calendar - All roles can view
            Route::get('room-units/calendar', 'RoomUnitController@getCalendarData');
            Route::get('room-units/day-tour-calendar', 'RoomUnitController@getDayTourCalendarData');
            Route::get('room-units/{roomUnit}/booking-details', 'RoomUnitController@getBookingDetails');
            Route::get('room-units/{roomUnit}/day-tour-booking-details', 'RoomUnitController@getDayTourBookingDetails');
        });
        
        // Admin, Superadmin routes
        Route::middleware(['role:admin,superadmin'])->group(function () {
            // Rooms
            Route::apiResource('rooms', 'RoomController');
            
            // Room Units
            Route::get('room-types/{room}/units', 'RoomUnitController@getRoomUnits');
            Route::get('room-types/{room}/stats', 'RoomUnitController@getRoomStats');
            Route::post('room-types/{room}/units/generate', 'RoomUnitController@generateUnits');
            Route::apiResource('room-units', 'RoomUnitController');
            
            // Amenities
            Route::apiResource('amenities', 'AmenityController');
            Route::patch('amenities/{id}/update-status', 'AmenityController@updateStatus');
            
            // Bookings management
            Route::post('bookings', 'BookingController@store');
            Route::put('bookings/{booking}', 'BookingController@update');
            Route::delete('bookings/{booking}', 'BookingController@destroy');
            Route::post('bookings/{booking}/other-charges', 'BookingController@storeOtherCharge');
            Route::patch('bookings/{booking}/reschedule', 'BookingController@reschedule');
            Route::delete('bookings/{booking}/other-charges/{charge}', 'OtherChargeController@destroy');
            
            // Booking cancellation management
            Route::get('bookings/cancellation-reasons', 'BookingCancellationController@getCancellationReasons');
            Route::post('bookings/{booking}/cancel', 'BookingCancellationController@cancel');
            Route::get('bookings/{booking}/can-cancel', 'BookingCancellationController@canCancel');
            
            // Room unit management for bookings
            Route::get('bookings/{booking}/available-room-units', 'BookingController@getAvailableRoomUnits');
            Route::patch('bookings/{booking}/booking-rooms/{bookingRoom}/change-room-unit', 'BookingController@changeRoomUnit');
            
            // Payment management routes
            Route::get('payments', 'PaymentController@index');
            Route::get('bookings/{booking}/payments', 'PaymentController@getByBooking');
            Route::post('payments/pay', 'PaymentController@pay');
            Route::put('payments/{payment}', 'PaymentController@update');
            
            // Payment proof management
            Route::patch('payments/{payment}/proof-upload/reset', 'PaymentController@resetProofUploads');
            Route::patch('payments/{payment}/proof-status', 'PaymentController@updateProofStatus');
            
            // Promos
            Route::patch('promos/bulk-update-status', 'PromoController@bulkUpdateStatus');
            Route::patch('promos/{id}/update-status', 'PromoController@updateStatus');
            Route::patch('promos/{id}/update-exclusive', 'PromoController@updateExclusive');
            Route::apiResource('promos', 'PromoController');
            
            // Meal Prices
            Route::apiResource('meal-prices', 'MealPriceController');
            
            // Meal Programs
            Route::get('meal-programs/{id}/preview', 'MealProgramController@preview');
            Route::apiResource('meal-programs', 'MealProgramController');
            
            // Meal Pricing Tiers
            Route::post('meal-programs/{programId}/pricing-tiers', 'MealPricingTierController@store');
            Route::put('meal-programs/{programId}/pricing-tiers/{tierId}', 'MealPricingTierController@update');
            Route::delete('meal-programs/{programId}/pricing-tiers/{tierId}', 'MealPricingTierController@destroy');
            
            // Meal Calendar Overrides
            Route::post('meal-programs/{programId}/overrides', 'MealCalendarOverrideController@store');
            Route::put('meal-programs/{programId}/overrides/{overrideId}', 'MealCalendarOverrideController@update');
            Route::delete('meal-programs/{programId}/overrides/{overrideId}', 'MealCalendarOverrideController@destroy');
            
            // Reviews
            Route::apiResource('reviews', 'ReviewController');
        });
        
        // Superadmin only routes
        Route::middleware(['role:superadmin'])->group(function () {
            // Users
            Route::apiResource('users', 'UserController');
            
            // Booking deletion
            Route::post('bookings/{booking}/delete', 'BookingCancellationController@delete');
            
            // Day Tour Pricing
            Route::patch('day-tour-pricing/{id}/toggle-status', 'DayTourPricingController@toggleStatus');
            Route::apiResource('day-tour-pricing', 'DayTourPricingController');
            
            // Images
            Route::apiResource('images', 'ImageController')->only(['index', 'store', 'destroy']);
        });
    });
