<?php

use App\Http\Controllers\Webhook\ClerkWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// routes/api.php
Route::prefix('v1')->group(function () {
    // Public routes
    Route::get('/', fn() => 'Welcome to Cloud Haven API V1');

    Route::prefix('webhooks')->namespace('App\Http\Controllers\Webhook')
        ->group(function () {
            Route::post('clerk', ClerkWebhookController::class);
        });
    // Auth routes
    // Route::post('/login', [AuthController::class, 'login']);

    // Protected routes
    // Clerk JWT authentication middleware applied here (see below)
    // Include route files
    require base_path('routes/V1/dashboard_routes.php');
    require base_path('routes/V1/user_routes.php');
    require base_path('routes/V1/admin_routes.php');

    Route::middleware('clerk.auth:api')->group(function () {
        Route::get('/clerk/test', fn() => 'Clerk Middleware Check | ' . auth()->user()->clerk_id);
    });
});
