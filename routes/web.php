<?php

use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\ArchiveController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\LegalPageController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\ClaudeUsageController;
use App\Http\Controllers\Admin\CountriesController;
use App\Http\Controllers\Admin\ConversationWebController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InboxController;
use App\Http\Controllers\Admin\InstanceWebController;
use App\Http\Controllers\Admin\KnowledgeController;
use App\Http\Controllers\Admin\OtpServiceController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SavedReplyWebController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SuperAdminManagerController;
use App\Http\Controllers\Admin\SuperAdminPlatformController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\MessengerConnectController;
use App\Http\Controllers\Admin\MessengerInboxController;
use App\Http\Controllers\WebChat\ConversationController as WebChatConversationController;
use App\Http\Controllers\WebChat\MessageController as WebChatMessageController;
use App\Http\Controllers\WebChat\WidgetSettingsController as WebChatWidgetSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SsoHandoffController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LocaleController;
use App\Support\Wavadesk;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/docs/api', fn () => response()->file(public_path('docs/api.html')))->name('docs.api');

// Feature / SEO content pages. Static marketing routes, deliberately outside
// every auth group so crawlers reach them without a redirect.
Route::get('/features', [FeatureController::class, 'index'])->name('features.index');
Route::get('/features/{slug}', [FeatureController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('features.show');
Route::get('/sitemap.xml', [FeatureController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt',  [FeatureController::class, 'robots'])->name('robots');

// Legal pages (still reachable — required for Stripe/regulators)
Route::get('/legal/terms',   [LandingController::class, 'terms'])->name('legal.terms');
Route::get('/legal/privacy', [LandingController::class, 'privacy'])->name('legal.privacy');
Route::get('/legal/cookies', [LandingController::class, 'cookies'])->name('legal.cookies');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
});

// SSO handoff from the marketing app (wavadesk.com / Server A). The `code` is
// a short-lived, single-use, HMAC-signed handoff minted by Server A after a
// successful /api/v1/auth/register or /login round-trip. Rate-limit hard so a
// leaked or spammed URL can't be brute-forced. Not inside `guest` on purpose:
// a stale session on Server B should still be able to redeem a fresh code.
//
// Core-only. Both hosts hold the same signing secret, so a marketing host that
// also exposed this route could redeem its own codes and open a local session
// on wavadesk.com — the exact session the split exists to stop it from having.
if (Wavadesk::isCore()) {
    Route::get('/auth/sso', [SsoHandoffController::class, 'redeem'])
        ->middleware('throttle:30,1')
        ->name('auth.sso.redeem');
}

// Step 2 of signup: the workspace and its admin already exist, the plan does
// not. Deliberately outside the panel groups — no 'subscription' middleware
// (a plan-less tenant is exactly who this page is for) and no 'verified'
// gate, so an admin whose verification mail never arrived can still subscribe.
if (Wavadesk::isMarketing()) {
    // Marketing has no Laravel Auth user for the signup flow (identity lives
    // on core); the controller guards on the session PAT itself and 302s to
    // /register if it is missing. `auth` middleware here would misclassify a
    // mid-signup visitor as a guest and bounce them off the picker.
    Route::get('/register/plan', [RegisterController::class, 'plan'])->name('register.plan');
    Route::post('/register/plan', [RegisterController::class, 'choosePlan'])
        ->middleware('throttle:10,1')
        ->name('register.plan.store');
} else {
    Route::middleware(['auth', ResolveTenant::class, 'role:admin'])->group(function () {
        Route::get('/register/plan', [RegisterController::class, 'plan'])->name('register.plan');
        Route::post('/register/plan', [RegisterController::class, 'choosePlan'])
            ->middleware('throttle:10,1')
            ->name('register.plan.store');
    });
}

// Payment (Stripe + PayPal)
// Route splits by role: marketing takes no {tenant} in the URL — its local
// tenants table is stale, so the controller reads the workspace from the
// session PAT snapshot instead. Same route name so callers stay uniform.
if (Wavadesk::isMarketing()) {
    Route::get('/payment/checkout', [PaymentController::class, 'checkout'])->name('payment.checkout');
} else {
    Route::get('/payment/checkout/{tenant}', [PaymentController::class, 'checkout'])->name('payment.checkout');
}
Route::post('/payment/initiate', [PaymentController::class, 'initiate'])->name('payment.initiate');
Route::post('/payment/upgrade', [PaymentController::class, 'upgrade'])
    ->name('payment.upgrade')
    ->middleware(['auth', ResolveTenant::class, 'role:admin,super_admin']);
// One-off AI message top-up. The tenant comes from the signed-in admin, never
// the URL, so the pack can only ever be billed to the buyer's own workspace.
Route::get('/payment/ai-messages', [PaymentController::class, 'aiPackCheckout'])
    ->name('payment.ai-pack')
    ->middleware(['auth', ResolveTenant::class, 'role:admin']);
Route::get('/payment/seats', [PaymentController::class, 'seatPackCheckout'])
    ->name('payment.seat-pack')
    ->middleware(['auth', ResolveTenant::class, 'role:admin']);
// A plan change and add-ons bought together — one approval, one charge.
Route::get('/payment/order', [PaymentController::class, 'cartCheckout'])
    ->name('payment.cart')
    ->middleware(['auth', ResolveTenant::class, 'role:admin']);
Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/payment/failed', [PaymentController::class, 'failed'])->name('payment.failed');
Route::post('/payment/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// PayPal
Route::post('/payment/paypal/initiate', [PaymentController::class, 'initiatePaypal'])->name('payment.paypal.initiate');
// Email-only PayPal Standard checkout, used when no REST client id is set.
Route::post('/payment/paypal/standard', [PaymentController::class, 'paypalStandardStart'])
    ->name('payment.paypal.standard')
    ->middleware(['auth', ResolveTenant::class, 'role:admin']);
Route::get('/payment/paypal/return',    [PaymentController::class, 'paypalReturn'])->name('payment.paypal.return');
Route::get('/payment/paypal/cancel',    [PaymentController::class, 'paypalCancel'])->name('payment.paypal.cancel');
Route::post('/payment/paypal/webhook',  [PaymentController::class, 'paypalWebhook'])->name('payment.paypal.webhook')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
// Standard Payments IPN — email-only PayPal flow, no OAuth
Route::post('/payment/paypal/ipn',      [PaymentController::class, 'paypalIpn'])->name('payment.paypal.ipn')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
// PayPal Smart Buttons SDK — JSON endpoints called from checkout page
Route::post('/payment/paypal/create-order',            [PaymentController::class, 'createPaypalOrder'])->name('payment.paypal.create-order');
Route::post('/payment/paypal/capture-order/{orderId}', [PaymentController::class, 'capturePaypalOrder'])->name('payment.paypal.capture-order');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::get('/admin/login', [LoginController::class, 'showAdminLoginForm'])->name('admin.login');
    Route::post('/admin/login', [LoginController::class, 'adminLogin'])->name('admin.login.submit');
    Route::get('/supervisor/login', [LoginController::class, 'showSupervisorLoginForm'])->name('supervisor.login');
    Route::post('/supervisor/login', [LoginController::class, 'supervisorLogin'])->name('supervisor.login.submit');
    Route::get('/agent/login', [LoginController::class, 'showAgentLoginForm'])->name('agent.login');
    Route::post('/agent/login', [LoginController::class, 'agentLogin'])->name('agent.login.submit');
    // Control-panel sign-in lives under the panel's own prefix. The route
    // names stay `superadmin.login*` so existing links and the rejection
    // redirects in LoginController keep resolving.
    Route::get(config('app.super_admin_prefix', 'admin-control-panel') . '/login', [LoginController::class, 'showSuperAdminLoginForm'])->name('superadmin.login');
    Route::post(config('app.super_admin_prefix', 'admin-control-panel') . '/login', [LoginController::class, 'superAdminLogin'])->name('superadmin.login.submit');
    // Previous URL — kept so old bookmarks land on the new form.
    Route::get('/superadmin/login', fn () => redirect()->route('superadmin.login', [], 301));
});
// The marketing host has no local auth guard to sit behind — its "session" is
// the core app's token stashed in the session bag, not an Auth::login(). Left
// under 'auth' there, /logout would bounce every caller to the login page and
// the stashed token could never be cleared.
Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout')
    ->middleware(Wavadesk::isMarketing() ? [] : ['auth']);

// Email verification (PROC-024) — Laravel's signed-link flow. Verified admins
// created by other admins skip this because UserController + super-admin flows
// stamp email_verified_at on creation.
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', function () {
        // Nothing to wait for when the gate is off and no mail can be sent —
        // showing "check your inbox" would strand the user on a dead end.
        return auth()->user()->hasVerifiedEmail() || ! config('auth.require_email_verification')
            ? redirect()->route(auth()->user()->homeRouteName())
            : view('auth.verify-email');
    })->name('verification.notice');

    Route::get('/email/verify/{id}/{hash}', function (\Illuminate\Foundation\Auth\EmailVerificationRequest $request) {
        $request->fulfill();
        return redirect()->route(auth()->user()->homeRouteName())
            ->with('success', __('auth.verify_email.verified'));
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::post('/email/verification-notification', function (\Illuminate\Http\Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return back()->with('success', __('auth.verify_email.link_sent'));
    })->middleware('throttle:6,1')->name('verification.send');
});

$registerPanelRoutes = function (string $prefix, string $namePrefix, array $roles, bool $includeManagement, bool $legacy = false): void {
    // 'subscription' gates the whole panel so a suspended tenant loses access
    // to server-rendered pages (conversations, customers, archive, reports)
    // and not only to the JSON API. Super admins are exempted inside the
    // middleware itself. Billing and profile routes clear the middleware
    // individually so a lapsed tenant can still pay to reactivate and
    // manage credentials.
    //
    // 'verified' (PROC-024) blocks the panel until the signup email has been
    // confirmed. Users created by an admin get email_verified_at set on
    // creation, so this only bites the public /register path. Billing and
    // profile stay exempt so an unverified admin can still pay or correct a
    // wrong email address.
    //
    // Dropped entirely when auth.require_email_verification is false: with no
    // working mail provider the gate locks every new signup out of a panel
    // they can never reach, since the link that opens it cannot be delivered.
    $middleware = ['auth', ResolveTenant::class, 'subscription'];
    if (config('auth.require_email_verification')) {
        $middleware[] = 'verified';
    }
    $middleware[] = 'role:' . implode(',', $roles);
    if ($legacy) {
        $middleware[] = 'role_path';
    }

    Route::middleware($middleware)
        ->prefix($prefix)
        ->name($namePrefix . '.')
        ->group(function () use ($includeManagement, $roles) {
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

            // Unified inbox — WhatsApp + Live Chat in one list
            Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
            Route::get('/inbox/list', [InboxController::class, 'list'])->name('inbox.list');
            Route::get('/inbox/thread/{channel}/{ref}', [InboxController::class, 'thread'])
                ->whereIn('channel', ['whatsapp', 'webchat', 'messenger'])
                ->name('inbox.thread');
            // Agents a thread may be handed to, filtered by the same rules the
            // reassign endpoints enforce.
            Route::get('/inbox/assignable/{channel}/{ref}', [InboxController::class, 'assignable'])
                ->whereIn('channel', ['whatsapp', 'webchat', 'messenger'])
                ->name('inbox.assignable');

            // Conversations - all system users
            Route::get('/conversations', [ConversationWebController::class, 'index'])->name('conversations.index');
            Route::get('/conversations/{conversation}', [ConversationWebController::class, 'show'])->name('conversations.show');

            // Archive - closed tickets from both channels
            Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
            Route::get('/archive/whatsapp/{conversation}', [ArchiveController::class, 'showWhatsApp'])->name('archive.whatsapp.show');
            Route::get('/archive/webchat/{uuid}', [ArchiveController::class, 'showWebChat'])->name('archive.webchat.show');

            // Customers - all system users
            Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

            // Saved replies - every role that answers a conversation needs its
            // own canned replies, so the library is not admin-only. Who may
            // create or edit a *team* reply is enforced by the API the page
            // and the inbox composer both talk to.
            Route::middleware('module:saved_replies')->group(function () {
                Route::get('/saved-replies', [SavedReplyWebController::class, 'index'])->name('saved-replies.index');
            });

            // Web Live-Chat — human-answered chat widget (separate from WhatsApp).
            // Not available to super_admin — they don't act as frontline agents.
            Route::middleware('role:admin,supervisor,agent')
                ->prefix('webchat')
                ->name('webchat.')
                ->group(function () {
                    Route::get('/conversations', [WebChatConversationController::class, 'index'])
                        ->name('conversations.index');
                    Route::get('/conversations/{uuid}', [WebChatConversationController::class, 'show'])
                        ->name('conversations.show');
                    Route::post('/conversations/{uuid}/claim', [WebChatConversationController::class, 'claim'])
                        ->name('conversations.claim');
                    Route::post('/conversations/{uuid}/release', [WebChatConversationController::class, 'release'])
                        ->name('conversations.release');
                    Route::post('/conversations/{uuid}/reassign', [WebChatConversationController::class, 'reassign'])
                        ->name('conversations.reassign');
                    Route::post('/conversations/{uuid}/suggest-title', [WebChatConversationController::class, 'suggestTitle'])
                        ->name('conversations.suggest-title');
                    Route::post('/conversations/{uuid}/close', [WebChatConversationController::class, 'close'])
                        ->name('conversations.close');
                    Route::post('/conversations/{uuid}/read', [WebChatConversationController::class, 'markRead'])
                        ->name('conversations.read');
                    Route::post('/conversations/{uuid}/messages', [WebChatMessageController::class, 'store'])
                        ->name('messages.store');
                });

            // Messenger inbox — mirrors the webchat routes above. Gated on
            // the `messenger` plan module so tenants without the module see
            // 403 instead of a broken empty page. Not available to
            // super_admin — they don't act as frontline agents.
            Route::middleware(['role:admin,supervisor,agent', 'module:messenger'])
                ->prefix('messenger')
                ->name('messenger.')
                ->group(function () {
                    Route::get('/',                        [MessengerInboxController::class, 'index'])->name('index');
                    Route::get('/conversations/{uuid}',    [MessengerInboxController::class, 'show'])->name('show');
                    Route::post('/conversations/{uuid}/claim',   [MessengerInboxController::class, 'claim'])->name('claim');
                    Route::post('/conversations/{uuid}/release', [MessengerInboxController::class, 'release'])->name('release');
                    Route::post('/conversations/{uuid}/close',   [MessengerInboxController::class, 'close'])->name('close');
                    Route::post('/conversations/{uuid}/read',    [MessengerInboxController::class, 'markRead'])->name('read');
                    Route::post('/conversations/{uuid}/reply',   [MessengerInboxController::class, 'reply'])->name('reply');

                    // Facebook Page connection — admin-only settings inside the
                    // Messenger module. Agents can work the inbox but only the
                    // tenant admin decides which Pages Wavadesk manages.
                    Route::middleware('role:admin')->group(function () {
                        Route::get('/settings',                [MessengerConnectController::class, 'settings'])->name('settings');
                        Route::get('/oauth/start',             [MessengerConnectController::class, 'start'])->name('oauth.start');
                        Route::get('/oauth/callback',          [MessengerConnectController::class, 'callback'])->name('oauth.callback');
                        Route::post('/oauth/select',           [MessengerConnectController::class, 'select'])->name('oauth.select');
                        Route::post('/pages/{id}/disconnect',  [MessengerConnectController::class, 'disconnect'])->name('oauth.disconnect');
                    });
                });

            // Impersonation leave route (when admin is currently impersonating).
            // Exempt from the subscription gate: middleware runs BEFORE the
            // controller can flip Auth back to the original user, so
            // CheckSubscription sees the (possibly lapsed / trial-ended)
            // impersonated tenant and blocks the route — trapping the
            // super-admin inside the impersonation with no way out short
            // of clearing cookies. Also exempt from `verified` so an
            // unverified admin doesn't get locked in either.
            Route::withoutMiddleware([\App\Http\Middleware\CheckSubscription::class, \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class])->group(function () {
                Route::get('/impersonate/leave', [UserController::class, 'leaveImpersonation'])->name('users.impersonate.leave');
            });

            // Profile — all roles. Exempted from the subscription gate so a
            // lapsed tenant's users can still see and manage their own account.
            // Also exempted from 'verified' (PROC-024) so an unverified admin
            // can correct a mistyped signup email without being locked out.
            Route::withoutMiddleware([\App\Http\Middleware\CheckSubscription::class, \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class])->group(function () {
                Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
                Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
                Route::post('/profile/email-change', [ProfileController::class, 'requestEmailChange'])->name('profile.email-change');
                Route::post('/profile/email-verify', [ProfileController::class, 'verifyEmailChange'])->name('profile.email-verify');
            });

            // API Access — its own section, gated by the api_access plan module
            // so tenants on lower tiers see the locked upgrade preview (same
            // treatment Reservations and Live Chat get). Available to all
            // panel roles — keys are per-user, not per-tenant.
            Route::middleware('module:api_access')->group(function () {
                Route::get('/api-access', [\App\Http\Controllers\Admin\ApiAccessController::class, 'show'])->name('api-access.show');
                Route::post('/api-access/regenerate', [\App\Http\Controllers\Admin\ApiAccessController::class, 'regenerate'])->name('api-access.regenerate');
            });

            if (!$includeManagement && in_array('supervisor', $roles, true)) {
                Route::middleware('role:supervisor')->group(function () {
                    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
                    Route::get('/teams/{team}/edit', [TeamController::class, 'edit'])->name('teams.edit');
                    Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
                });
            }

            if (!$includeManagement) {
                return;
            }

            // Management routes - admin and super admin only
            Route::middleware('role:admin,super_admin')->group(function () {
                Route::get('/instances', [InstanceWebController::class, 'index'])->name('instances.index');
                Route::get('/instances/create', [InstanceWebController::class, 'create'])->name('instances.create');
                Route::post('/instances', [InstanceWebController::class, 'store'])->name('instances.store');
                Route::get('/instances/{instance}', [InstanceWebController::class, 'show'])->name('instances.show');
                Route::get('/instances/{instance}/webhook-events', [InstanceWebController::class, 'webhookEvents'])->name('instances.webhook-events');
                Route::post('/instances/{instance}/webhook-events/{event}/reprocess', [InstanceWebController::class, 'reprocessWebhookEvent'])->name('instances.webhook-events.reprocess');
                Route::get('/instances/{instance}/edit', [InstanceWebController::class, 'edit'])->name('instances.edit');
                Route::put('/instances/{instance}', [InstanceWebController::class, 'update'])->name('instances.update');
                Route::delete('/instances/{instance}', [InstanceWebController::class, 'destroy'])->name('instances.destroy');

                // Users
                Route::get('/users', [UserController::class, 'index'])->name('users.index');
                Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
                Route::post('/users', [UserController::class, 'store'])->name('users.store');
                Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
                Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
                Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
                Route::post('/users/bulk', [UserController::class, 'bulk'])->name('users.bulk');
                Route::get('/users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
                // Archive / restore a single user. Distinct from delete: data
                // preserved, user hidden from the main list, cannot log in.
                // See UserController::toggleArchive for the semantic split.
                Route::patch('/users/{user}/toggle-archive', [UserController::class, 'toggleArchive'])->name('users.toggle-archive');

                // Teams — plan module.
                Route::middleware('module:teams')->group(function () {
                    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
                    Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
                    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
                    Route::get('/teams/{team}/edit', [TeamController::class, 'edit'])->name('teams.edit');
                    Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
                    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
                    Route::post('/teams/bulk', [TeamController::class, 'bulk'])->name('teams.bulk');
                });

                // Reports + Audit log — plan modules.
                Route::middleware('module:reports')->group(function () {
                    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
                    Route::get('/reports/data', [ReportController::class, 'data'])->name('reports.data');
                });

                Route::middleware('module:audit_log')->group(function () {
                    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
                });

                // Billing — accessible to both admin and super_admin (controller gates by role).
                // Exempted from the subscription gate so a lapsed tenant can still reach the
                // page that lets them pay to reactivate. Also exempted from 'verified'
                // (PROC-024) so an unverified new signup can still pay if they want.
                Route::withoutMiddleware([\App\Http\Middleware\CheckSubscription::class, \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class])->group(function () {
                    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
                    Route::get('/billing/payments', [BillingController::class, 'payments'])->name('billing.payments');
                    Route::get('/billing/payments/{payment}', [BillingController::class, 'showPayment'])->name('billing.payment.show');
                });

                // Settings
                Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
                Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

                // Notifications
                Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
                Route::post('/notifications', [NotificationController::class, 'send'])->name('notifications.send');
            });

            // Reservations module (admin only; the controller also checks the
            // legacy reservations_enabled flag, which stays in sync with the
            // `reservations` module checkbox).
            Route::middleware(['role:admin', 'module:reservations'])->group(function () {
                Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
                Route::patch('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])->name('reservations.status');
                Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');
                Route::get('/reservations/slots', [ReservationController::class, 'slots'])->name('reservations.slots');
                Route::post('/reservations/slots', [ReservationController::class, 'storeSlot'])->name('reservations.slots.store');
                Route::post('/reservations/slots/bulk', [ReservationController::class, 'bulkStoreSlots'])->name('reservations.slots.bulk');
                Route::put('/reservations/slots/{slot}', [ReservationController::class, 'updateSlot'])->name('reservations.slots.update');
                Route::delete('/reservations/slots/{slot}', [ReservationController::class, 'destroySlot'])->name('reservations.slots.destroy');
                Route::get('/reservations/settings', [ReservationController::class, 'settings'])->name('reservations.settings');
                Route::put('/reservations/settings', [ReservationController::class, 'saveSettings'])->name('reservations.settings.save');
                Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
            });

            // Knowledge Base + AI settings (tenant admin only).
            // Each block carries its own plan-module gate.
            Route::middleware('role:admin')->group(function () {
                // Knowledge Base
                Route::middleware('module:knowledge_base')->group(function () {
                Route::get('/knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
                Route::post('/knowledge', [KnowledgeController::class, 'store'])->name('knowledge.store');
                Route::get('/knowledge/import/template', [KnowledgeController::class, 'importJsonTemplate'])->name('knowledge.import.template');
                Route::post('/knowledge/import', [KnowledgeController::class, 'importJson'])->name('knowledge.import');
                Route::get('/knowledge/{entry}/edit', [KnowledgeController::class, 'edit'])->name('knowledge.edit');
                Route::put('/knowledge/{entry}', [KnowledgeController::class, 'update'])->name('knowledge.update');
                Route::delete('/knowledge/{entry}', [KnowledgeController::class, 'destroy'])->name('knowledge.destroy');
                });

                // AI Settings
                Route::middleware('module:ai_agent')->group(function () {
                    Route::get('/ai-settings', [AiSettingsController::class, 'index'])->name('ai-settings.index');
                    Route::put('/ai-settings', [AiSettingsController::class, 'update'])->name('ai-settings.update');
                });

                // Web Live-Chat — widget settings (per-tenant customization)
                Route::middleware('module:webchat')->group(function () {
                    Route::get('/webchat/settings', [WebChatWidgetSettingsController::class, 'show'])->name('webchat.settings.show');
                    Route::put('/webchat/settings', [WebChatWidgetSettingsController::class, 'update'])->name('webchat.settings.update');
                });

                // OTP-over-WhatsApp API service (tenant configuration + integration snippets)
                Route::middleware('module:otp_service')->group(function () {
                    Route::get('/otp-service', [OtpServiceController::class, 'show'])->name('otp-service.show');
                    Route::put('/otp-service', [OtpServiceController::class, 'update'])->name('otp-service.update');
                });
            });

            // SaaS control plane (super admin only)
            Route::middleware('role:super_admin')->group(function () {
                // Super Admin account management
                Route::get('/super-admins', [SuperAdminManagerController::class, 'index'])->name('super-admins.index');
                Route::get('/super-admins/create', [SuperAdminManagerController::class, 'create'])->name('super-admins.create');
                Route::post('/super-admins', [SuperAdminManagerController::class, 'store'])->name('super-admins.store');
                Route::get('/super-admins/{superAdmin}/edit', [SuperAdminManagerController::class, 'edit'])->name('super-admins.edit');
                Route::put('/super-admins/{superAdmin}', [SuperAdminManagerController::class, 'update'])->name('super-admins.update');
                Route::delete('/super-admins/{superAdmin}', [SuperAdminManagerController::class, 'destroy'])->name('super-admins.destroy');
                Route::get('/platform/tenants', [SuperAdminPlatformController::class, 'tenants'])->name('platform.tenants');
                Route::get('/platform/tenants/create', [SuperAdminPlatformController::class, 'createTenant'])->name('platform.tenants.create');
                Route::post('/platform/tenants', [SuperAdminPlatformController::class, 'storeTenant'])->name('platform.tenants.store');
                Route::get('/platform/tenants/{tenant}', [SuperAdminPlatformController::class, 'showTenant'])->name('platform.tenants.show');
                Route::get('/platform/tenants/{tenant}/edit', [SuperAdminPlatformController::class, 'editTenant'])->name('platform.tenants.edit');
                Route::put('/platform/tenants/{tenant}', [SuperAdminPlatformController::class, 'updateTenant'])->name('platform.tenants.update');
                Route::delete('/platform/tenants/{tenant}', [SuperAdminPlatformController::class, 'destroyTenant'])->name('platform.tenants.destroy');
                Route::post('/platform/tenants/bulk', [SuperAdminPlatformController::class, 'bulkTenants'])->name('platform.tenants.bulk');
                Route::get('/platform/plans', [SuperAdminPlatformController::class, 'plans'])->name('platform.plans');
                Route::get('/platform/plans/create', [SuperAdminPlatformController::class, 'createPlan'])->name('platform.plans.create');
                Route::post('/platform/plans', [SuperAdminPlatformController::class, 'storePlan'])->name('platform.plans.store');
                Route::get('/platform/plans/{plan}', [SuperAdminPlatformController::class, 'showPlan'])->name('platform.plans.show');
                Route::get('/platform/plans/{plan}/edit', [SuperAdminPlatformController::class, 'editPlan'])->name('platform.plans.edit');
                Route::put('/platform/plans/{plan}', [SuperAdminPlatformController::class, 'updatePlan'])->name('platform.plans.update');
                Route::patch('/platform/plans/{plan}/status', [SuperAdminPlatformController::class, 'togglePlanStatus'])->name('platform.plans.status');
                Route::post('/platform/plans/bulk', [SuperAdminPlatformController::class, 'bulkPlans'])->name('platform.plans.bulk');
Route::get('/platform/system-health', [SuperAdminPlatformController::class, 'systemHealth'])->name('platform.system-health');

                // Legal page editor
                Route::get('/platform/legal-pages', [LegalPageController::class, 'index'])->name('platform.legal-pages.index');
                Route::get('/platform/legal-pages/{slug}/{locale}/edit', [LegalPageController::class, 'edit'])->name('platform.legal-pages.edit');
                Route::put('/platform/legal-pages/{slug}/{locale}', [LegalPageController::class, 'update'])->name('platform.legal-pages.update');
            });
        });
};

$superAdminPrefix = config('app.super_admin_prefix', 'admin-control-panel');

// The control panel used to live at /super-admin. Permanently redirect the old
// paths (query string included) so saved bookmarks and any link still pointing
// at them land on the current URL instead of a 404.
if ($superAdminPrefix !== 'super-admin') {
    Route::get('/super-admin/{path?}', function (?string $path = null) use ($superAdminPrefix) {
        $target = '/' . $superAdminPrefix . ($path !== null && $path !== '' ? '/' . $path : '');
        $query  = request()->getQueryString();

        return redirect($target . ($query ? '?' . $query : ''), 301);
    })->where('path', '.*');
}


// ─────────────────────────────────────────────────────────────────────────────
// PLATFORM CONTROL PANEL (super admin only)
//
// Registered separately from $registerPanelRoutes on purpose. The super admin
// runs the platform, not a workspace: they get the control plane plus the
// cross-tenant read views, and none of the tenant-operational pages
// (instances, users, teams, settings, AI, knowledge, saved replies, webchat,
// OTP, reservations) that the tenant panel registers.
//
// The route-name prefix stays `super_admin.` so User::routeNamePrefix() and
// every existing route('super_admin.*') call keep resolving — only the URL
// prefix moved from /super-admin to /admin-control-panel.
// ─────────────────────────────────────────────────────────────────────────────
$registerControlPanelRoutes = function () use ($superAdminPrefix): void {
    $panelPrefix = $superAdminPrefix;
    $middleware  = ['auth', ResolveTenant::class];
    if (config('auth.require_email_verification')) {
        $middleware[] = 'verified';
    }
    $middleware[] = 'role:super_admin';

    Route::middleware($middleware)
        ->prefix($panelPrefix)
        ->name('super_admin.')
        ->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

            // ── Cross-tenant oversight (read) ────────────────────────────
            Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
            Route::get('/inbox/list', [InboxController::class, 'list'])->name('inbox.list');
            Route::get('/inbox/thread/{channel}/{ref}', [InboxController::class, 'thread'])
                ->whereIn('channel', ['whatsapp', 'webchat', 'messenger'])
                ->name('inbox.thread');
            // The inbox renders a reassign picker for any role ConversationPolicy
            // lets reassign, and super_admin is one of them — without this route
            // the page itself 500s on route('super_admin.inbox.assignable').
            Route::get('/inbox/assignable/{channel}/{ref}', [InboxController::class, 'assignable'])
                ->whereIn('channel', ['whatsapp', 'webchat', 'messenger'])
                ->name('inbox.assignable');

            Route::get('/conversations', [ConversationWebController::class, 'index'])->name('conversations.index');
            Route::get('/conversations/{conversation}', [ConversationWebController::class, 'show'])->name('conversations.show');

            Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
            Route::get('/archive/whatsapp/{conversation}', [ArchiveController::class, 'showWhatsApp'])->name('archive.whatsapp.show');
            Route::get('/archive/webchat/{uuid}', [ArchiveController::class, 'showWebChat'])->name('archive.webchat.show');

            Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/data', [ReportController::class, 'data'])->name('reports.data');

            Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

            Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
            Route::get('/billing/payments', [BillingController::class, 'payments'])->name('billing.payments');
            Route::get('/billing/payments/{payment}', [BillingController::class, 'showPayment'])->name('billing.payment.show');

            Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
            Route::post('/notifications', [NotificationController::class, 'send'])->name('notifications.send');

            // ── Own account ──────────────────────────────────────────────
            // Exempt from 'verified' so a super admin can still correct a
            // mistyped address without being locked out of the panel.
            Route::withoutMiddleware([\Illuminate\Auth\Middleware\EnsureEmailIsVerified::class])->group(function () {
                Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
                Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
                Route::post('/profile/email-change', [ProfileController::class, 'requestEmailChange'])->name('profile.email-change');
                Route::post('/profile/email-verify', [ProfileController::class, 'verifyEmailChange'])->name('profile.email-verify');
                Route::post('/profile/regenerate-api-key', [ProfileController::class, 'regenerateApiKey'])->name('profile.regenerate-api-key');
            });

            // ── Super admin accounts ─────────────────────────────────────
            Route::get('/super-admins', [SuperAdminManagerController::class, 'index'])->name('super-admins.index');
            Route::get('/super-admins/create', [SuperAdminManagerController::class, 'create'])->name('super-admins.create');
            Route::post('/super-admins', [SuperAdminManagerController::class, 'store'])->name('super-admins.store');
            Route::get('/super-admins/{superAdmin}/edit', [SuperAdminManagerController::class, 'edit'])->name('super-admins.edit');
            Route::put('/super-admins/{superAdmin}', [SuperAdminManagerController::class, 'update'])->name('super-admins.update');
            Route::delete('/super-admins/{superAdmin}', [SuperAdminManagerController::class, 'destroy'])->name('super-admins.destroy');

            // ── Control plane ────────────────────────────────────────────
            Route::get('/platform/tenants', [SuperAdminPlatformController::class, 'tenants'])->name('platform.tenants');
            Route::get('/platform/tenants/create', [SuperAdminPlatformController::class, 'createTenant'])->name('platform.tenants.create');
            Route::post('/platform/tenants', [SuperAdminPlatformController::class, 'storeTenant'])->name('platform.tenants.store');
            Route::get('/platform/tenants/{tenant}', [SuperAdminPlatformController::class, 'showTenant'])->name('platform.tenants.show');
            Route::get('/platform/tenants/{tenant}/edit', [SuperAdminPlatformController::class, 'editTenant'])->name('platform.tenants.edit');
            Route::put('/platform/tenants/{tenant}', [SuperAdminPlatformController::class, 'updateTenant'])->name('platform.tenants.update');
            Route::delete('/platform/tenants/{tenant}', [SuperAdminPlatformController::class, 'destroyTenant'])->name('platform.tenants.destroy');
            Route::post('/platform/tenants/bulk', [SuperAdminPlatformController::class, 'bulkTenants'])->name('platform.tenants.bulk');
            // One-click "log in as this tenant" — resolves the tenant's
            // primary active admin and reuses the ImpersonationLog +
            // session['impersonating'] machinery so /impersonate/leave
            // restores this super-admin's session with no extra plumbing.
            // Belongs in THIS block (the separate super_admin registration
            // that owns admin-control-panel/* URLs), not $registerPanelRoutes
            // which is for admin / tenant-admin / supervisor / agent panels.
            Route::get('/platform/tenants/{tenant}/impersonate-admin', [SuperAdminPlatformController::class, 'impersonateTenantAdmin'])
                ->name('platform.tenants.impersonate-admin');
            // Per-row block/unblock. Toggles tenants.is_active, which the
            // CheckSubscription middleware reads via Tenant::isActive() —
            // when false, every user of that tenant is blocked at the next
            // panel request. Mirrors what the bulk `disable` action does
            // but as a single-tenant one-shot from the table row.
            Route::patch('/platform/tenants/{tenant}/toggle-active', [SuperAdminPlatformController::class, 'toggleTenantActive'])
                ->name('platform.tenants.toggle-active');
            // Archive / restore. Distinct from block: archive hides the
            // tenant from the main list and reads as long-term shelving,
            // block is a live suspension that stays visible. See
            // SuperAdminPlatformController::toggleTenantArchive for the
            // semantic split.
            Route::patch('/platform/tenants/{tenant}/toggle-archive', [SuperAdminPlatformController::class, 'toggleTenantArchive'])
                ->name('platform.tenants.toggle-archive');
            // Test-only lever: back-date the trial to yesterday so QA can
            // check what the app does after a trial expires without waiting
            // for the real end date. No-op unless the tenant is currently
            // on trial. See SuperAdminPlatformController::expireTrial.
            Route::post('/platform/tenants/{tenant}/expire-trial', [SuperAdminPlatformController::class, 'expireTrial'])
                ->name('platform.tenants.expire-trial');
            // Grant N extra days on the tenant's current plan window (trial
            // or paid). Every extension writes an AuditLog row.
            Route::post('/platform/tenants/{tenant}/extend-plan', [SuperAdminPlatformController::class, 'extendTenantPlan'])
                ->name('platform.tenants.extend-plan');
            // Replace the plan end date with an operator-chosen absolute date.
            Route::post('/platform/tenants/{tenant}/set-plan-date', [SuperAdminPlatformController::class, 'setTenantPlanDate'])
                ->name('platform.tenants.set-plan-date');

            // Claude API cost report. Ledger-backed, one row per Anthropic
            // call recorded by App\Services\AI\UsageTracker.
            Route::get('/platform/claude-usage', [ClaudeUsageController::class, 'index'])->name('platform.claude-usage');

            // Countries the platform sells in — enable each ISO-code
            // country you want localised pricing for. Once a country row
            // exists, per-plan prices become editable on the plan edit page.
            Route::get('/platform/countries', [CountriesController::class, 'index'])->name('platform.countries.index');
            Route::get('/platform/countries/create', [CountriesController::class, 'create'])->name('platform.countries.create');
            Route::post('/platform/countries', [CountriesController::class, 'store'])->name('platform.countries.store');
            Route::get('/platform/countries/{country}/edit', [CountriesController::class, 'edit'])->name('platform.countries.edit');
            Route::put('/platform/countries/{country}', [CountriesController::class, 'update'])->name('platform.countries.update');
            Route::patch('/platform/countries/{country}/toggle', [CountriesController::class, 'toggle'])->name('platform.countries.toggle');
            Route::delete('/platform/countries/{country}', [CountriesController::class, 'destroy'])->name('platform.countries.destroy');

            Route::get('/platform/plans', [SuperAdminPlatformController::class, 'plans'])->name('platform.plans');
            Route::get('/platform/plans/create', [SuperAdminPlatformController::class, 'createPlan'])->name('platform.plans.create');
            Route::post('/platform/plans', [SuperAdminPlatformController::class, 'storePlan'])->name('platform.plans.store');
            Route::get('/platform/plans/{plan}', [SuperAdminPlatformController::class, 'showPlan'])->name('platform.plans.show');
            Route::get('/platform/plans/{plan}/edit', [SuperAdminPlatformController::class, 'editPlan'])->name('platform.plans.edit');
            Route::put('/platform/plans/{plan}', [SuperAdminPlatformController::class, 'updatePlan'])->name('platform.plans.update');
            // Per-country pricing for the plan. Separate endpoint so
            // pricing edits don't need to re-submit the entire plan form.
            Route::put('/platform/plans/{plan}/country-prices', [SuperAdminPlatformController::class, 'updatePlanCountryPrices'])
                ->name('platform.plans.country-prices.update');
            Route::patch('/platform/plans/{plan}/status', [SuperAdminPlatformController::class, 'togglePlanStatus'])->name('platform.plans.status');
            Route::post('/platform/plans/bulk', [SuperAdminPlatformController::class, 'bulkPlans'])->name('platform.plans.bulk');

            Route::get('/platform/conversation-settings', [SuperAdminPlatformController::class, 'conversationSettings'])->name('platform.conversation-settings');
            // Platform default language — used by SetLocale when a visitor
            // has no locale in session yet. DB-backed so the change lands
            // without editing .env on every box.
            Route::get('/platform/localization', [SuperAdminPlatformController::class, 'localizationSettings'])->name('platform.localization');
            Route::put('/platform/localization', [SuperAdminPlatformController::class, 'updateLocalizationSettings'])->name('platform.localization.update');
            Route::put('/platform/conversation-settings', [SuperAdminPlatformController::class, 'updateConversationSettings'])->name('platform.conversation-settings.update');

            // Add-on pricing — what tenants pay for extra seats and AI packs.
            Route::get('/platform/addons', [SuperAdminPlatformController::class, 'addonSettings'])->name('platform.addons');
            Route::put('/platform/addons', [SuperAdminPlatformController::class, 'updateAddonSettings'])->name('platform.addons.update');

            // Meta / Messenger credentials — App ID, App Secret, Verify Token,
            // Graph version. Editable here so ops doesn't need SSH to rotate
            // the secret. Secrets stored encrypted (Crypt) in platform_settings.
            Route::get('/platform/meta-settings', [SuperAdminPlatformController::class, 'metaSettings'])->name('platform.meta-settings');
            Route::put('/platform/meta-settings', [SuperAdminPlatformController::class, 'updateMetaSettings'])->name('platform.meta-settings.update');

            Route::get('/platform/system-health', [SuperAdminPlatformController::class, 'systemHealth'])->name('platform.system-health');

            Route::get('/platform/legal-pages', [LegalPageController::class, 'index'])->name('platform.legal-pages.index');
            Route::get('/platform/legal-pages/{slug}/{locale}/edit', [LegalPageController::class, 'edit'])->name('platform.legal-pages.edit');
            Route::put('/platform/legal-pages/{slug}/{locale}', [LegalPageController::class, 'update'])->name('platform.legal-pages.update');
        });
};

// Legacy path retained for compatibility. Canonical role paths are enforced on
// safe methods. Super admins are deliberately absent: the control plane lives on
// its own prefix and no longer shares the tenant panel's routes.
$registerPanelRoutes('admin', 'admin', ['admin', 'supervisor', 'agent'], true, true);

// Canonical role-specific pathnames
$registerControlPanelRoutes();
$registerPanelRoutes('tenant-admin', 'tenant_admin', ['admin'], true);
$registerPanelRoutes('supervisor', 'supervisor', ['supervisor'], false);
$registerPanelRoutes('agent', 'agent', ['agent'], false);

// ── Language entry points ───────────────────────────────────────────────────
// /ar, /en/features/whatsapp-multi-agent and so on open the site in that
// language and then redirect to the clean path, so the prefix never stays in
// the URL and no page gains a second, duplicate address.
//
// Registered last and constrained to the supported codes, so it can only ever
// match a locale segment — /features and /agent are untouched.
Route::get('/{locale}/{path?}', [LocaleController::class, 'enter'])
    ->where('locale', implode('|', array_keys(config('locales.supported', ['en' => []]))))
    ->where('path', '.*')
    ->name('locale.enter');
