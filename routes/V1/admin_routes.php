<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin')->namespace('App\Http\Controllers\API\V1\Admin')
    ->middleware(['clerk.auth:api', 'role:admin,superadmin,staff'])
    ->group(function () {
        // Admin Routes
        Route::get('dashboard', 'DashboardController@index');
        Route::get('clerk/test', fn() => 'Clerk Middleware Check Admin | ' . auth()->user()->clerk_id);
        Route::apiResource('rooms', 'RoomController');
        Route::patch('day-tour-pricing/{id}/toggle-status', 'DayTourPricingController@toggleStatus');
        Route::apiResource('day-tour-pricing', 'DayTourPricingController');

        // Room Units
        Route::get('room-types/{room}/units', 'RoomUnitController@getRoomUnits');
        Route::get('room-types/{room}/stats', 'RoomUnitController@getRoomStats');
        Route::post('room-types/{room}/units/generate', 'RoomUnitController@generateUnits');
        Route::apiResource('room-units', 'RoomUnitController');

        Route::apiResource('users', 'UserController')->middleware('role:admin,superadmin');

        Route::apiResource('amenities', 'AmenityController')->middleware('role:admin,superadmin');
        Route::patch('amenities/{id}/update-status', 'AmenityController@updateStatus')->middleware('role:admin,superadmin');

        // Calendar view for bookings (range filtered)
        Route::get('bookings/calendar', 'BookingController@calendar');
        
        // Booking cancellation management (must be before resource routes)
        Route::get('bookings/cancellation-reasons', 'BookingCancellationController@getCancellationReasons')->middleware('role:admin,superadmin');
        Route::post('bookings/{booking}/cancel', 'BookingCancellationController@cancel')->middleware('role:admin,superadmin');
        Route::post('bookings/{booking}/delete', 'BookingCancellationController@delete')->middleware('role:superadmin');
        Route::get('bookings/{booking}/can-cancel', 'BookingCancellationController@canCancel')->middleware('role:admin,superadmin');
        
        Route::apiResource('bookings', 'BookingController')->middleware('role:admin,superadmin');
        Route::post('bookings/{booking}/other-charges', 'BookingController@storeOtherCharge')->middleware('role:admin,superadmin');
        Route::patch('bookings/{booking}/reschedule', 'BookingController@reschedule')->middleware('role:admin,superadmin');
        Route::delete('bookings/{booking}/other-charges/{charge}', 'OtherChargeController@destroy')->middleware('role:admin,superadmin');
        
        // Payment management routes
        Route::get('payments', 'PaymentController@index')->middleware('role:admin,superadmin');
        Route::post('payments/pay', 'PaymentController@pay')->middleware('role:admin,superadmin');
        Route::put('payments/{payment}', 'PaymentController@update')->middleware('role:admin,superadmin');
        
        // Payment proof management
        Route::patch('payments/{payment}/proof-upload/reset', 'PaymentController@resetProofUploads')->middleware('role:admin,superadmin');
        Route::patch('payments/{payment}/proof-status', 'PaymentController@updateProofStatus')->middleware('role:admin,superadmin');

        Route::patch('promos/bulk-update-status', 'PromoController@bulkUpdateStatus')->middleware('role:admin,superadmin');
        Route::patch('promos/{id}/update-status', 'PromoController@updateStatus')->middleware('role:admin,superadmin');
        Route::patch('promos/{id}/update-exclusive', 'PromoController@updateExclusive')->middleware('role:admin,superadmin');
        Route::apiResource('promos', 'PromoController')->middleware('role:admin,superadmin');
        
        Route::apiResource('meal-prices', 'MealPriceController')->middleware('role:admin,superadmin');

        // Meal Programs
        Route::get('meal-programs/{id}/preview', 'MealProgramController@preview')->middleware('role:admin,superadmin');
        Route::apiResource('meal-programs', 'MealProgramController')->middleware('role:admin,superadmin');
        
        // Meal Pricing Tiers
        Route::post('meal-programs/{programId}/pricing-tiers', 'MealPricingTierController@store')->middleware('role:admin,superadmin');
        Route::put('meal-programs/{programId}/pricing-tiers/{tierId}', 'MealPricingTierController@update')->middleware('role:admin,superadmin');
        Route::delete('meal-programs/{programId}/pricing-tiers/{tierId}', 'MealPricingTierController@destroy')->middleware('role:admin,superadmin');
        
        // Meal Calendar Overrides
        Route::post('meal-programs/{programId}/overrides', 'MealCalendarOverrideController@store')->middleware('role:admin,superadmin');
        Route::put('meal-programs/{programId}/overrides/{overrideId}', 'MealCalendarOverrideController@update')->middleware('role:admin,superadmin');
        Route::delete('meal-programs/{programId}/overrides/{overrideId}', 'MealCalendarOverrideController@destroy')->middleware('role:admin,superadmin');

        Route::apiResource('images', 'ImageController')->only(['index', 'store', 'destroy'])->middleware('role:admin,superadmin');

    });
