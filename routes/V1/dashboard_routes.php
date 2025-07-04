<?php

use Illuminate\Support\Facades\Route;

Route::prefix('/')->namespace('App\Http\Controllers\Api\V1\Dashboard')
    // ->middleware(['clerk.auth:api', 'role:user,admin'])
    ->group(function () {
        /* ---------- Public dashboard (no auth) ---------- */
        Route::get('rooms',            'RoomController@index');
        Route::get('rooms/{room:slug}', 'RoomController@show');
        Route::get('clerk/test', fn() => 'Clerk Middleware Check Dashboard | ' . auth()->user()->clerk_id); // routes/api.php

        Route::get('/meal-prices', 'MealPriceController@getMealPrices');

        Route::post('/bookings', 'BookingController@store');

        Route::post('/bookings/{bookingId}/pay', 'PaymentController@pay');
    });
