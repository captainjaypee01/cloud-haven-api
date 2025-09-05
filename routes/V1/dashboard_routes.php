<?php

use Illuminate\Support\Facades\Route;

Route::prefix('/')->namespace('App\Http\Controllers\API\V1\Dashboard')
    // ->middleware(['clerk.auth:api', 'role:user,admin'])
    ->group(function () {
        /* ---------- Public dashboard (no auth) ---------- */
        Route::get('rooms',            'RoomController@index');
        Route::post('rooms/availability', 'RoomAvailabilityController@batchCheck');
        Route::get('rooms/featured', 'RoomController@featuredRooms');

        Route::get('rooms/{room:slug}', 'RoomController@show');
        Route::get('rooms/{roomSlug}/availability', 'RoomController@checkAvailability');

        Route::get('clerk/test', fn() => 'Clerk Middleware Check Dashboard'); // routes/api.php

        Route::get('/meal-prices', 'MealPriceController@getMealPrices');

        // Meal Programs
        Route::get('/public/meal-availability', 'MealController@availability');
        Route::post('/public/quotes/meal', 'MealController@quote');

        Route::post('/bookings', 'BookingController@store');
        Route::get('/bookings/ref/{referenceNumber}', 'BookingController@showByReferenceNumber'); // routes/api.php
        Route::post('/bookings/ref/{referenceNumber}/pay', 'PaymentController@pay'); // routes/api.php
        Route::post('/bookings/ref/{referenceNumber}/pay/upload-proof', 'PaymentController@uploadProof'); // Legacy route
        
        // New proof upload routes for specific payments
        Route::post('/bookings/ref/{referenceNumber}/payments/{paymentId}/proof', 'PaymentController@uploadProof');

        Route::get('/promos/exclusive', 'PromoController@exclusiveOffers');
        Route::get('/promos/{promoCode}', 'PromoController@showByCode');

        Route::get('/reviews/testimonials', 'ReviewController@testimonials');
    });
