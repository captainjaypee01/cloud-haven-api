<?php

use Illuminate\Support\Facades\Route;

Route::prefix('user')->namespace('App\Http\Controllers\Api\V1\User')
    ->middleware(['clerk.auth:api', 'role:user,admin,staff,superadmin'])
    ->group(function () {
        // User routes...
        Route::get('clerk/test', fn() => 'Clerk Middleware Check Guest | ' . auth()->user()->clerk_id);

        Route::post('/reviews', 'ReviewController@store');
        Route::get('/bookings/user', 'BookingController@listByUser');
        Route::patch('/bookings/ref/{referenceNumber}/claim', 'BookingController@claim');
    });
