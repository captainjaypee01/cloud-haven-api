<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin')->namespace('App\Http\Controllers\Api\V1\Admin')
    ->middleware(['clerk.auth:api', 'role:admin'])
    ->group(function () {
        // Admin Routes
        Route::get('dashboard', 'DashboardController@index');
        Route::get('clerk/test', fn() => 'Clerk Middleware Check Admin | ' . auth()->user()->clerk_id);
        Route::apiResource('rooms', 'RoomController');

        Route::apiResource('users', 'UserController');

        Route::apiResource('amenities', 'AmenityController');
        Route::patch('amenities/{id}/update-status', 'AmenityController@updateStatus');

        Route::apiResource('bookings', 'BookingController');
        Route::post('bookings/{booking}/other-charges', 'BookingController@storeOtherCharge');
        Route::patch('bookings/{booking}/reschedule', 'BookingController@reschedule');
        Route::delete('bookings/{booking}/other-charges/{charge}', 'OtherChargeController@destroy');
        // Route::apiResource('payments', 'PaymentController');
        Route::post('payments/pay', 'PaymentController@pay');
        Route::put('payments/{payment}', 'PaymentController@update');

        Route::patch('promos/bulk-update-status', 'PromoController@bulkUpdateStatus');
        Route::patch('promos/{id}/update-status', 'PromoController@updateStatus');
        Route::patch('promos/{id}/update-exclusive', 'PromoController@updateExclusive');
        Route::apiResource('promos', 'PromoController');
        
        Route::apiResource('meal-prices', 'MealPriceController');

        Route::apiResource('images', 'ImageController')->only(['index', 'store', 'destroy']);

    });
