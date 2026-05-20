<?php

use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\InstanceController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/whatsapp/{token}', [WhatsAppWebhookController::class, 'handle'])
    ->name('webhooks.whatsapp');

Route::middleware(['auth', \App\Http\Middleware\ResolveTenant::class])->group(function () {

    Route::middleware('role:admin,supervisor,agent')->group(function () {
        // Conversations
        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
        Route::post('/conversations/{conversation}/claim', [ConversationController::class, 'claim']);
        Route::post('/conversations/{conversation}/release', [ConversationController::class, 'release']);
        Route::post('/conversations/{conversation}/close', [ConversationController::class, 'close']);
        Route::post('/conversations/{conversation}/reassign', [ConversationController::class, 'reassign']);

        // Messages
        Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']);
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);
        Route::post('/conversations/{conversation}/notes', [MessageController::class, 'storeNote']);
    });

    Route::middleware('role:admin,super_admin')->group(function () {
        // WhatsApp Instances
        Route::get('/instances', [InstanceController::class, 'index']);
        Route::post('/instances', [InstanceController::class, 'store']);
        Route::post('/instances/{instance}/connect', [InstanceController::class, 'connect']);
        Route::get('/instances/{instance}/status', [InstanceController::class, 'status']);
        Route::post('/instances/{instance}/logout', [InstanceController::class, 'logout']);
    });

    Route::middleware('role:admin')->group(function () {
        // AI
        Route::get('/ai/settings', [AiController::class, 'show']);
        Route::put('/ai/settings', [AiController::class, 'update']);
        Route::post('/ai/test', [AiController::class, 'test']);
    });
});
