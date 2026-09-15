<?php

use App\Http\Controllers\Api\DirectSendController;
use App\Http\Controllers\Api\SingleInstanceController;
use App\Http\Controllers\Api\AgentPresenceController;
use App\Http\Controllers\Api\NotificationApiController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\InstanceController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\OutboundConversationController;
use App\Http\Controllers\Api\SavedReplyController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Http\Controllers\WebChat\BroadcastAuthController as WebChatBroadcastAuthController;
use App\Http\Controllers\WebChat\Public\ConversationController as WebChatPublicConversationController;
use App\Http\Controllers\WebChat\Public\MessageController as WebChatPublicMessageController;
use App\Http\Controllers\WebChat\Public\SessionController as WebChatPublicSessionController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/whatsapp/{token}', [WhatsAppWebhookController::class, 'handle'])
    ->name('webhooks.whatsapp');

// ─── Web Live-Chat public widget API ─────────────────────────────────────────
// Cross-origin. Auth via widget public_key + visitor bearer token — NOT web
// session. CORS scoped in config/cors.php to `api/webchat/*` only.
Route::prefix('webchat/{key}')
    ->middleware(['webchat.widget', 'webchat.domain'])
    ->group(function () {
        // Session: start or resume. Visitor token issued in response body.
        Route::post('/session', [WebChatPublicSessionController::class, 'store'])
            ->middleware('throttle:webchat-session')
            ->name('webchat.public.session');

        // Everything below requires a valid visitor bearer token.
        Route::middleware('webchat.visitor')->group(function () {
            Route::post('/conversations', [WebChatPublicConversationController::class, 'store'])
                ->middleware('throttle:webchat-session')
                ->name('webchat.public.conversations.store');

            Route::post('/conversations/{uuid}/request-agent', [WebChatPublicConversationController::class, 'requestAgent'])
                ->middleware('throttle:webchat-session')
                ->name('webchat.public.conversations.request-agent');

            Route::post('/conversations/{uuid}/close', [WebChatPublicConversationController::class, 'close'])
                ->middleware('throttle:webchat-session')
                ->name('webchat.public.conversations.close');

            Route::post('/conversations/{uuid}/messages', [WebChatPublicMessageController::class, 'store'])
                ->middleware('throttle:webchat-message')
                ->name('webchat.public.messages.store');

            Route::get('/conversations/{uuid}/messages', [WebChatPublicMessageController::class, 'index'])
                ->name('webchat.public.messages.index');
        });
    });

// Visitor broadcast auth — signs Pusher/Reverb private-channel subscriptions
// for `private-webchat.conversation.{uuid}`. Reuses WebChatVisitorAuth which
// resolves the visitor's widget when no {key} is present in the path.
Route::post('/webchat/broadcasting/auth', [WebChatBroadcastAuthController::class, 'authenticate'])
    ->middleware(['webchat.visitor'])
    ->name('webchat.public.broadcasting.auth');

// All API routes authenticated via X-Api-Key header
Route::middleware(['api.key', \App\Http\Middleware\ResolveTenant::class])->group(function () {

    Route::post('/send', [DirectSendController::class, 'send']);

    Route::prefix('instance')->group(function () {
        Route::get('/',            [SingleInstanceController::class, 'show']);
        Route::get('/status',      [SingleInstanceController::class, 'status']);
        Route::post('/connect',    [SingleInstanceController::class, 'connect']);
        Route::post('/disconnect', [SingleInstanceController::class, 'disconnect']);
    });

    // ── OTP service (WhatsApp) — X-Api-Key auth, tenant-scoped ────────────────
    Route::prefix('otp')->group(function () {
        Route::post('/send',   [OtpController::class, 'send'])->name('api.otp.send');
        Route::post('/verify', [OtpController::class, 'verify'])->name('api.otp.verify');
    });

    // ── Notifications (all roles, no subscription gate) ───────────────────────
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
        Route::post('/conversations/{conversation}/suggest-title', [ConversationController::class, 'suggestTitle']);
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
        Route::get('/instances', [InstanceController::class, 'index']);
        Route::post('/instances', [InstanceController::class, 'store']);
        Route::get('/instances/{instance}', [InstanceController::class, 'show']);
        Route::post('/instances/{instance}/connect', [InstanceController::class, 'connect']);
        Route::get('/instances/{instance}/status', [InstanceController::class, 'status']);
        Route::post('/instances/{instance}/disconnect', [InstanceController::class, 'disconnect']);
        Route::post('/instances/{instance}/logout', [InstanceController::class, 'disconnect']);
        Route::delete('/instances/{instance}', [InstanceController::class, 'destroy']);
    });

    Route::middleware(['role:admin', 'subscription'])->group(function () {
        Route::get('/ai/settings', [AiController::class, 'show']);
        Route::put('/ai/settings', [AiController::class, 'update']);
        Route::post('/ai/test', [AiController::class, 'test']);
    });
});
