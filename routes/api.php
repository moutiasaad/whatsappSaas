<?php

use App\Http\Controllers\Api\AgentPresenceController;
use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\InstanceController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\OutboundConversationController;
use App\Http\Controllers\Api\SavedReplyController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// Public — no auth, no CSRF
Route::withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    Route::post('/webhooks/whatsapp/{token}', [WhatsAppWebhookController::class, 'handle'])
        ->name('webhooks.whatsapp');

    Route::post('/auth/login', [ApiAuthController::class, 'login'])->name('api.login');
});

Route::post('/auth/logout', [ApiAuthController::class, 'logout'])
    ->middleware(['auth:sanctum'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('api.logout');

Route::middleware(['auth:sanctum', \App\Http\Middleware\ResolveTenant::class])->group(function () {

    // ── Notifications (all roles, no subscription gate) ──────────────────────
    Route::middleware('role:admin,super_admin,supervisor,agent')->group(function () {
        Route::get('/notifications', [NotificationApiController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationApiController::class, 'unreadCount']);
        Route::get('/notifications/history', [NotificationApiController::class, 'history']);
        Route::patch('/notifications/{id}/read', [NotificationApiController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationApiController::class, 'markAllRead']);
    });

    Route::get('/notifications/tenant-users/{tenantId}', [NotificationApiController::class, 'tenantUsers']);

    // ── Subscription-protected routes ─────────────────────────────────────────
    Route::middleware(['role:admin,super_admin,supervisor,agent', 'subscription'])->group(function () {

        // Conversations
        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::post('/conversations/start', [OutboundConversationController::class, 'start']);
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
        Route::delete('/conversations/{conversation}', [OutboundConversationController::class, 'destroy']);

        // Messages
        Route::get('/conversations/{conversation}/messages', [MessageController::class, 'index']);
        Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);
        Route::post('/conversations/{conversation}/notes', [MessageController::class, 'storeNote']);
        Route::post('/media/upload', [MessageController::class, 'uploadMedia']);

        // Saved replies
        Route::get('/saved-replies', [SavedReplyController::class, 'index']);
        Route::post('/saved-replies', [SavedReplyController::class, 'store']);
        Route::put('/saved-replies/{savedReply}', [SavedReplyController::class, 'update']);
        Route::delete('/saved-replies/{savedReply}', [SavedReplyController::class, 'destroy']);

        // Agent presence
        Route::post('/agents/heartbeat', [AgentPresenceController::class, 'heartbeat']);
        Route::get('/agents/online', [AgentPresenceController::class, 'online']);
    });

    Route::middleware(['role:admin,super_admin', 'subscription'])->group(function () {

        // WhatsApp Instances — full CRUD
        Route::get('/instances', [InstanceController::class, 'index']);
        Route::post('/instances', [InstanceController::class, 'store']);
        Route::get('/instances/{instance}', [InstanceController::class, 'show']);
        Route::post('/instances/{instance}/connect', [InstanceController::class, 'connect']);
        Route::get('/instances/{instance}/status', [InstanceController::class, 'status']);
        Route::post('/instances/{instance}/disconnect', [InstanceController::class, 'disconnect']);
        Route::post('/instances/{instance}/logout', [InstanceController::class, 'disconnect']); // alias
        Route::delete('/instances/{instance}', [InstanceController::class, 'destroy']);
    });

    Route::middleware(['role:admin', 'subscription'])->group(function () {
        // AI settings
        Route::get('/ai/settings', [AiController::class, 'show']);
        Route::put('/ai/settings', [AiController::class, 'update']);
        Route::post('/ai/test', [AiController::class, 'test']);
    });
});
