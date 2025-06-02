<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin')->namespace('App\Http\Controllers\Api\V1\Admin')
    ->middleware(['clerk.auth:api', 'role:admin'])
    ->group(function () {
        // Admin Routes
        Route::get('clerk/test', fn() => 'Clerk Middleware Check Admin | ' . auth()->user()->clerk_id);
        Route::apiResource('rooms', 'RoomController');
        Route::apiResource('users', 'UserController');
    });
