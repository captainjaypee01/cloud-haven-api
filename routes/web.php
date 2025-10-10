<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return '';
});

// Public review page route (signed URL for security)
Route::get('/review/{token}', function ($token) {
    return redirect(config('app.frontend_url') . '/review/' . $token);
})->name('public.review');
