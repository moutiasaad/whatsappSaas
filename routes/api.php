<?php

use App\Http\Controllers\Api\V1\Auth\AuthApiController;
use App\Http\Controllers\Api\V1\BillingApiController;
use App\Http\Controllers\Api\V1\PlansApiController;
use App\Http\Controllers\Api\V1\SessionStatusController;
use App\Http\Middleware\SessionStatusCors;
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
use App\Http\Controllers\Webhooks\MessengerWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Http\Controllers\WebChat\BroadcastAuthController as WebChatBroadcastAuthController;
use App\Http\Controllers\WebChat\Public\ConversationController as WebChatPublicConversationController;
use App\Http\Controllers\WebChat\Public\MessageController as WebChatPublicMessageController;
use App\Http\Controllers\WebChat\Public\SessionController as WebChatPublicSessionController;
use App\Support\Wavadesk;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/whatsapp/{token}', [WhatsAppWebhookController::class, 'handle'])
    ->name('webhooks.whatsapp');

// ─── Meta / Facebook Messenger webhook ───────────────────────────────────────
// Meta pushes events for every Page subscribed via /me/accounts. Both halves
// of the contract live in MessengerWebhookController:
//   GET  → subscription verification (hub_challenge echo)
//   POST → event delivery (HMAC-SHA256 signed with the App Secret)
// Registered only on the core box — the marketing box has no DB rows to
// serve them from, and Meta must not be pointed at wavadesk.com anyway.
if (Wavadesk::isCore()) {
    Route::get('/webhooks/messenger',  [MessengerWebhookController::class, 'verify'])
        ->name('webhooks.messenger.verify');
    Route::post('/webhooks/messenger', [MessengerWebhookController::class, 'handle'])
        ->name('webhooks.messenger.handle');
}

// ─── v1 Auth API (called by the marketing app on wavadesk.com) ───────────────
// Server-to-server only: wavadesk.com's php-fpm calls these, never a browser.
// That is why there is no CORS entry for them (see config/cors.php) and why
// every route sits behind `wavadesk.caller`, which demands the shared secret.
// Without that guard /api/v1/auth/login would be an open credential oracle.
//
// Registered only on the core app. On the marketing host these endpoints do
// not exist at all — it has no identity database to serve them from.
if (Wavadesk::isCore()) {
    Route::prefix('v1/auth')->middleware('wavadesk.caller')->group(function () {
        // Rate limits still apply behind the caller guard: they bound the blast
        // radius if the shared secret ever leaks, and they stop a bug in the
        // marketing app from hammering the database in a retry loop.
        Route::post('/register', [AuthApiController::class, 'register'])
            ->middleware('throttle:5,1')
            ->name('api.v1.auth.register');

        Route::post('/login', [AuthApiController::class, 'login'])
            ->middleware('throttle:10,1')
            ->name('api.v1.auth.login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me',      [AuthApiController::class, 'me'])->name('api.v1.auth.me');
            Route::post('/logout', [AuthApiController::class, 'logout'])->name('api.v1.auth.logout');
        });
    });

    // ─── v1 Plans API (called by the marketing plan picker) ────────────────
    // GET is public within the caller-gated group — the marketing site needs
    // the catalog to render the picker before the user is signed in on this
    // side. POST /choose commits a pick and needs the user's PAT so the
    // trial gets attached to the right workspace.
    Route::prefix('v1/plans')->middleware('wavadesk.caller')->group(function () {
        Route::get('/', [PlansApiController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('api.v1.plans.index');

        Route::post('/choose', [PlansApiController::class, 'choose'])
            ->middleware(['auth:sanctum', 'throttle:10,1'])
            ->name('api.v1.plans.choose');
    });

    // ─── v1 Billing API (called by the marketing checkout button) ──────────
    // Auth via user's PAT: the tenant to bill is derived from the token, so
    // a caller cannot name a workspace they do not own. Callback URLs live
    // on this host — the provider (Stripe/PayPal) needs a stable callback
    // that owns the tenant row, and the browser lands on the panel (also
    // here) once the payment captures.
    Route::prefix('v1/billing')->middleware('wavadesk.caller')->group(function () {
        Route::post('/checkout', [BillingApiController::class, 'checkout'])
            ->middleware(['auth:sanctum', 'throttle:10,1'])
            ->name('api.v1.billing.checkout');

        // PayPal SDK create-order / capture-order proxies for the marketing
        // checkout page. The SDK on wavadesk.com posts to
        // /payment/paypal/create-order and /payment/paypal/capture-order/{id}
        // there; marketing proxies both to these endpoints with the user's
        // PAT. Throttle is higher than /checkout because the SDK can retry
        // the pair a couple of times during a single approval (network
        // blips, card 3DS re-auth).
        Route::post('/paypal/create-order', [BillingApiController::class, 'paypalCreateOrder'])
            ->middleware(['auth:sanctum', 'throttle:20,1'])
            ->name('api.v1.billing.paypal.create-order');

        Route::post('/paypal/capture-order/{orderId}', [BillingApiController::class, 'paypalCaptureOrder'])
            ->middleware(['auth:sanctum', 'throttle:20,1'])
            ->name('api.v1.billing.paypal.capture-order');
    });

    // ─── v1 Session status (called from the marketing site's browser) ──────
    // Unlike everything else above this is NOT server-to-server: the visitor's
    // own browser fetches it with `credentials: 'include'` so the marketing
    // header can flip from "Sign in / Start free trial" to "Go to dashboard"
    // when the same browser already carries a core session cookie.
    //
    // No `wavadesk.caller` gate — the browser has no shared secret. The
    // origin allowlist in SessionStatusCors is what stands in for the secret,
    // and the payload is display-only (name, initials, role, home URL) so a
    // leaked response reveals no tokens.
    Route::match(['get', 'options'], '/v1/session/status', [SessionStatusController::class, 'show'])
        ->middleware([SessionStatusCors::class, 'throttle:60,1'])
        ->name('api.v1.session.status');
}

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
