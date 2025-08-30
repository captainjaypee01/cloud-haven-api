<?php

use Illuminate\Support\Facades\Route;

// Public user routes (no auth required)
Route::prefix('user')->namespace('App\Http\Controllers\API\V1\Dashboard')
    ->group(function () {
        Route::get('/rooms/{roomSlug}/availability', 'RoomController@checkAvailability');
    });

Route::prefix('user')->namespace('App\Http\Controllers\API\V1\User')
    ->middleware(['clerk.auth:api', 'role:user,admin,staff,superadmin'])
    ->group(function () {
        // User routes...
        Route::get('clerk/test', fn() => 'Clerk Middleware Check Guest | ' . auth()->user()->clerk_id);

        Route::post('/reviews', 'ReviewController@store');
        Route::get('/bookings/user', 'BookingController@listByUser');
        Route::patch('/bookings/ref/{referenceNumber}/claim', 'BookingController@claim');
    });
