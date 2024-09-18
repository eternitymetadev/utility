<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OAuthController;

Route::get('/', function () {
    return view('welcome');
});

// Add this route to your web.php or api.php file
Route::get('/create-subscription', [OAuthController::class, 'createSubscription']);

Route::get('auth/redirect', [OAuthController::class, 'redirectToProvider'])->name('auth.redirect');
Route::get('auth/callback', [OAuthController::class, 'handleProviderCallback'])->name('auth.callback');

// routes/web.php
Route::get('/trigger-redirect', [OAuthController::class, 'triggerRedirect']);

Route::post('/notifications', [NotificationController::class, 'handleNotification']);


