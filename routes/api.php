<?php

use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/subscriptions', [SubscriptionController::class, 'store']);
Route::delete('/subscriptions', [SubscriptionController::class, 'destroy']);
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);
