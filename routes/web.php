<?php

use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\ConversationWebController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InstanceWebController;
use App\Http\Controllers\Admin\KnowledgeController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SuperAdminPlatformController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

// Landing page
Route::get('/', [LandingController::class, 'index'])->name('landing');

// Registration
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

// Payment (Flouci)
Route::get('/payment/checkout/{tenant}', [PaymentController::class, 'checkout'])->name('payment.checkout');
Route::post('/payment/initiate', [PaymentController::class, 'initiate'])->name('payment.initiate');
Route::get('/payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/payment/failed', [PaymentController::class, 'failed'])->name('payment.failed');
Route::post('/payment/webhook', [PaymentController::class, 'webhook'])->name('payment.webhook')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

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

            // Impersonation leave route (when admin is currently impersonating)
            Route::get('/impersonate/leave', [UserController::class, 'leaveImpersonation'])->name('users.impersonate.leave');

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
                // Instances
                Route::get('/instances', [InstanceWebController::class, 'index'])->name('instances.index');
                Route::get('/instances/create', [InstanceWebController::class, 'create'])->name('instances.create');
                Route::post('/instances', [InstanceWebController::class, 'store'])->name('instances.store');
                Route::get('/instances/{instance}/webhook-events', [InstanceWebController::class, 'webhookEvents'])->name('instances.webhook-events');
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

                // Audit Log
                Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');

                // Settings
                Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
                Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
            });

            // Knowledge Base and AI settings (tenant admin only)
            Route::middleware('role:admin')->group(function () {
                // Knowledge Base
                Route::get('/knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
                Route::post('/knowledge', [KnowledgeController::class, 'store'])->name('knowledge.store');
                Route::get('/knowledge/{entry}/edit', [KnowledgeController::class, 'edit'])->name('knowledge.edit');
                Route::put('/knowledge/{entry}', [KnowledgeController::class, 'update'])->name('knowledge.update');
                Route::delete('/knowledge/{entry}', [KnowledgeController::class, 'destroy'])->name('knowledge.destroy');

                // AI Settings
                Route::get('/ai-settings', [AiSettingsController::class, 'index'])->name('ai-settings.index');
                Route::put('/ai-settings', [AiSettingsController::class, 'update'])->name('ai-settings.update');
            });

            // SaaS control plane (super admin only)
            Route::middleware('role:super_admin')->group(function () {
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
                Route::get('/platform/global-settings', [SuperAdminPlatformController::class, 'globalSettings'])->name('platform.global-settings');
                Route::get('/platform/global-settings/edit', [SuperAdminPlatformController::class, 'editGlobalSettings'])->name('platform.global-settings.edit');
                Route::put('/platform/global-settings', [SuperAdminPlatformController::class, 'updateGlobalSettings'])->name('platform.global-settings.update');
                Route::get('/platform/system-health', [SuperAdminPlatformController::class, 'systemHealth'])->name('platform.system-health');
                Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
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
