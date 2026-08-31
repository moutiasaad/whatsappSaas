<?php

use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\LegalPageController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\ConversationWebController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
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
use App\Http\Controllers\WebChat\ConversationController as WebChatConversationController;
use App\Http\Controllers\WebChat\MessageController as WebChatMessageController;
use App\Http\Controllers\WebChat\WidgetSettingsController as WebChatWidgetSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/docs/api', fn () => response()->file(public_path('docs/api.html')))->name('docs.api');

// Legal pages (still reachable — required for Stripe/regulators)
Route::get('/legal/terms',   [LandingController::class, 'terms'])->name('legal.terms');
Route::get('/legal/privacy', [LandingController::class, 'privacy'])->name('legal.privacy');
Route::get('/legal/cookies', [LandingController::class, 'cookies'])->name('legal.cookies');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:5,1')->name('register.store');
    Route::get('/register/verify-otp', [RegisterController::class, 'showOtp'])->name('register.otp');
    Route::post('/register/verify-otp', [RegisterController::class, 'verifyOtp'])->name('register.otp.verify');
    Route::post('/register/resend-otp', [RegisterController::class, 'resendOtp'])->middleware('throttle:3,1')->name('register.otp.resend');
});

// Payment (Stripe + PayPal)
Route::get('/payment/checkout/{tenant}', [PaymentController::class, 'checkout'])->name('payment.checkout');
Route::post('/payment/initiate', [PaymentController::class, 'initiate'])->name('payment.initiate');
Route::post('/payment/upgrade', [PaymentController::class, 'upgrade'])->name('payment.upgrade')->middleware('auth');
Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/payment/failed', [PaymentController::class, 'failed'])->name('payment.failed');
Route::post('/payment/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// PayPal
Route::post('/payment/paypal/initiate', [PaymentController::class, 'initiatePaypal'])->name('payment.paypal.initiate');
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
    Route::get('/superadmin/login', [LoginController::class, 'showSuperAdminLoginForm'])->name('superadmin.login');
    Route::post('/superadmin/login', [LoginController::class, 'superAdminLogin'])->name('superadmin.login.submit');
});
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

$registerPanelRoutes = function (string $prefix, string $namePrefix, array $roles, bool $includeManagement, bool $legacy = false): void {
    $middleware = ['auth', ResolveTenant::class, 'role:' . implode(',', $roles)];
    if ($legacy) {
        $middleware[] = 'role_path';
    }

    Route::middleware($middleware)
        ->prefix($prefix)
        ->name($namePrefix . '.')
        ->group(function () use ($includeManagement, $roles) {
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

            // Conversations - all system users
            Route::get('/conversations', [ConversationWebController::class, 'index'])->name('conversations.index');
            Route::get('/conversations/{conversation}', [ConversationWebController::class, 'show'])->name('conversations.show');

            // Customers - all system users
            Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

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
                    Route::post('/conversations/{uuid}/close', [WebChatConversationController::class, 'close'])
                        ->name('conversations.close');
                    Route::post('/conversations/{uuid}/read', [WebChatConversationController::class, 'markRead'])
                        ->name('conversations.read');
                    Route::post('/conversations/{uuid}/messages', [WebChatMessageController::class, 'store'])
                        ->name('messages.store');
                });

            // Impersonation leave route (when admin is currently impersonating)
            Route::get('/impersonate/leave', [UserController::class, 'leaveImpersonation'])->name('users.impersonate.leave');

            // Profile — all roles
            Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
            Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
            Route::post('/profile/email-change', [ProfileController::class, 'requestEmailChange'])->name('profile.email-change');
            Route::post('/profile/email-verify', [ProfileController::class, 'verifyEmailChange'])->name('profile.email-verify');
            Route::post('/profile/regenerate-api-key', [ProfileController::class, 'regenerateApiKey'])->name('profile.regenerate-api-key');

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

                // Teams
                Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
                Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
                Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
                Route::get('/teams/{team}/edit', [TeamController::class, 'edit'])->name('teams.edit');
                Route::put('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
                Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
                Route::post('/teams/bulk', [TeamController::class, 'bulk'])->name('teams.bulk');

                // Reports
                Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
                Route::get('/reports/data', [ReportController::class, 'data'])->name('reports.data');

                // Audit Log
                Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

                // Billing — accessible to both admin and super_admin (controller gates by role)
                Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
                Route::get('/billing/payments', [BillingController::class, 'payments'])->name('billing.payments');
                Route::get('/billing/payments/{payment}', [BillingController::class, 'showPayment'])->name('billing.payment.show');

                // Settings
                Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
                Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

                // Notifications
                Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
                Route::post('/notifications', [NotificationController::class, 'send'])->name('notifications.send');
            });

            // Reservations module (admin only, plan-gated inside controller)
            Route::middleware('role:admin')->group(function () {
                Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
                Route::patch('/reservations/{reservation}/status', [ReservationController::class, 'updateStatus'])->name('reservations.status');
                Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');
                Route::get('/reservations/slots', [ReservationController::class, 'slots'])->name('reservations.slots');
                Route::post('/reservations/slots', [ReservationController::class, 'storeSlot'])->name('reservations.slots.store');
                Route::put('/reservations/slots/{slot}', [ReservationController::class, 'updateSlot'])->name('reservations.slots.update');
                Route::delete('/reservations/slots/{slot}', [ReservationController::class, 'destroySlot'])->name('reservations.slots.destroy');
                Route::get('/reservations/settings', [ReservationController::class, 'settings'])->name('reservations.settings');
                Route::put('/reservations/settings', [ReservationController::class, 'saveSettings'])->name('reservations.settings.save');
                Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
            });

            // Knowledge Base, AI settings, Saved Replies (tenant admin only)
            Route::middleware('role:admin')->group(function () {
                // Knowledge Base
                Route::get('/knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
                Route::post('/knowledge', [KnowledgeController::class, 'store'])->name('knowledge.store');
                Route::get('/knowledge/import/template', [KnowledgeController::class, 'importJsonTemplate'])->name('knowledge.import.template');
                Route::post('/knowledge/import', [KnowledgeController::class, 'importJson'])->name('knowledge.import');
                Route::get('/knowledge/{entry}/edit', [KnowledgeController::class, 'edit'])->name('knowledge.edit');
                Route::put('/knowledge/{entry}', [KnowledgeController::class, 'update'])->name('knowledge.update');
                Route::delete('/knowledge/{entry}', [KnowledgeController::class, 'destroy'])->name('knowledge.destroy');

                // AI Settings
                Route::get('/ai-settings', [AiSettingsController::class, 'index'])->name('ai-settings.index');
                Route::put('/ai-settings', [AiSettingsController::class, 'update'])->name('ai-settings.update');

                // Saved Replies
                Route::get('/saved-replies', [SavedReplyWebController::class, 'index'])->name('saved-replies.index');

                // Web Live-Chat — widget settings (per-tenant customization)
                Route::get('/webchat/settings', [WebChatWidgetSettingsController::class, 'show'])->name('webchat.settings.show');
                Route::put('/webchat/settings', [WebChatWidgetSettingsController::class, 'update'])->name('webchat.settings.update');

                // OTP-over-WhatsApp API service (tenant configuration + integration snippets)
                Route::get('/otp-service', [OtpServiceController::class, 'show'])->name('otp-service.show');
                Route::put('/otp-service', [OtpServiceController::class, 'update'])->name('otp-service.update');
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

// Legacy path retained for compatibility. Canonical role paths are enforced on safe methods.
$registerPanelRoutes('admin', 'admin', ['super_admin', 'admin', 'supervisor', 'agent'], true, true);

// Canonical role-specific pathnames
$registerPanelRoutes('super-admin', 'super_admin', ['super_admin'], true);
$registerPanelRoutes('tenant-admin', 'tenant_admin', ['admin'], true);
$registerPanelRoutes('supervisor', 'supervisor', ['supervisor'], false);
$registerPanelRoutes('agent', 'agent', ['agent'], false);
