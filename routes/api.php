<?php

use App\Http\Controllers\Api\AgentPresenceController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\InstanceController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\SavedReplyController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/whatsapp/{token}', [WhatsAppWebhookController::class, 'handle'])
    ->name('webhooks.whatsapp')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::middleware(['auth', \App\Http\Middleware\ResolveTenant::class])->group(function () {

    // Notifications (all roles)
    Route::middleware('role:admin,super_admin,supervisor,agent')->group(function () {
        Route::get('/notifications', [NotificationApiController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationApiController::class, 'unreadCount']);
        Route::get('/notifications/history', [NotificationApiController::class, 'history']);
        Route::patch('/notifications/{id}/read', [NotificationApiController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationApiController::class, 'markAllRead']);
    });

    // Notification tenant-user lookup (super_admin only — checked inside controller)
    Route::get('/notifications/tenant-users/{tenantId}', [NotificationApiController::class, 'tenantUsers'])
        ->middleware('auth');

    Route::middleware('role:admin,super_admin,supervisor,agent')->group(function () {
        // Conversations
        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
        Route::get('/conversations/{conversation}/workspace', [ConversationController::class, 'workspace']);
        Route::post('/conversations/{conversation}/claim', [ConversationController::class, 'claim']);
        Route::post('/conversations/{conversation}/release', [ConversationController::class, 'release']);
        Route::post('/conversations/{conversation}/close', [ConversationController::class, 'close']);
        Route::post('/conversations/{conversation}/reassign', [ConversationController::class, 'reassign']);
        Route::post('/conversations/{conversation}/reopen', [ConversationController::class, 'reopen']);
        Route::post('/conversations/{conversation}/toggle-ai', [ConversationController::class, 'toggleAi']);
        Route::post('/conversations/{conversation}/read', [ConversationController::class, 'markRead']);
        Route::post('/conversations/{conversation}/presence', [ConversationController::class, 'updatePresence']);
        Route::get('/conversations/{conversation}/check-number', [ConversationController::class, 'checkNumber']);

        // Messages
        Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']);
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);
        Route::post('/conversations/{conversation}/notes', [MessageController::class, 'storeNote']);
        Route::post('/media/upload', [MessageController::class, 'uploadMedia']);

        // Saved replies (canned responses)
        Route::get('/saved-replies', [SavedReplyController::class, 'index']);
        Route::post('/saved-replies', [SavedReplyController::class, 'store']);
        Route::put('/saved-replies/{savedReply}', [SavedReplyController::class, 'update']);
        Route::delete('/saved-replies/{savedReply}', [SavedReplyController::class, 'destroy']);

        // Agent presence / online roster
        Route::post('/agents/heartbeat', [AgentPresenceController::class, 'heartbeat']);
        Route::get('/agents/online', [AgentPresenceController::class, 'online']);
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
