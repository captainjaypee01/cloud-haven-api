<?php

use Illuminate\Support\Facades\Route;

Route::prefix('/')->namespace('App\Http\Controllers\Api\V1\Dashboard')
    // ->middleware(['clerk.auth:api', 'role:user,admin'])
    ->group(function () {
        /* ---------- Public dashboard (no auth) ---------- */
        Route::get('rooms',            'RoomController@index');
        Route::post('rooms/availability', 'RoomAvailabilityController@batchCheck');
        Route::get('rooms/featured', 'RoomController@featuredRooms');

        Route::get('rooms/{room:slug}', 'RoomController@show');

        Route::get('clerk/test', fn() => 'Clerk Middleware Check Dashboard | ' . auth()->user()->clerk_id); // routes/api.php

        Route::get('/meal-prices', 'MealPriceController@getMealPrices');

        Route::post('/bookings', 'BookingController@store');
        Route::get('/bookings/ref/{referenceNumber}', 'BookingController@showByReferenceNumber'); // routes/api.php
        Route::post('/bookings/ref/{referenceNumber}/pay', 'PaymentController@pay'); // routes/api.php

        Route::get('/promos/exclusive', 'PromoController@exclusiveOffers');
        Route::get('/promos/{promoCode}', 'PromoController@showByCode');
    });
