<?php

use App\Http\Controllers\Webhook\ClerkWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// routes/api.php
Route::prefix('v1')->group(function () {
    // Public routes
    Route::get('/', fn() => 'Welcome to Cloud Haven API V1');

    Route::post('/webhooks/clerk', ClerkWebhookController::class);
    // Auth routes
    // Route::post('/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('clerk:api')->group(function () {
        Route::get('/clerk/test', fn() => 'Clerk Middleware Check');
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
