<?php

use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')->namespace('App\Http\Controllers\Api\V1\Dashboard')
    // ->middleware(['clerk.auth:api', 'role:user,admin'])
    ->group(function () {
        // User routes...
        Route::get('clerk/test', fn() => 'Clerk Middleware Check Dashboard | ' . auth()->user()->clerk_id);
    });
