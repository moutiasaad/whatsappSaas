<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // API routes share the web session so admin AJAX calls work with cookie auth
            \Illuminate\Support\Facades\Route::middleware('web')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            // Split hosting: each host answers only for the pages it owns, so
            // core stops serving its inherited landing/login/register copies
            // and marketing stops answering for panels, payment and the APIs.
            // No-ops on an un-split box.
            \App\Http\Middleware\SplitHostingRedirect::class,
        ]);

        // `auth` is in Laravel's priority list and SplitHostingRedirect is not,
        // so by default authentication sorts ahead of it: a guest asking
        // wavadesk.com for /payment/order was bounced to the marketing login
        // instead of being sent to the host that owns the order. Slot the
        // redirect in just before it — still after StartSession, so it can
        // tell a signed-in user from a guest.
        //
        // The anchor is the CONTRACT, not \Illuminate\Auth\Middleware\
        // Authenticate: the priority list holds the interface, and naming the
        // concrete class matches nothing, which appends to the end of the list
        // and silently leaves the original ordering in place.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\SplitHostingRedirect::class,
        );

        // PROC-008: resolve the tenant BEFORE route-model binding. SubstituteBindings
        // is in Laravel's priority list and ResolveTenant is not, so by default bindings
        // resolve while current_tenant_id is unbound and the tenant scope silently no-ops.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\ResolveTenant::class,
        );

        // Same trap as SplitHostingRedirect above: `auth:sanctum` implements
        // AuthenticatesRequests, which IS in Laravel's priority list, so it
        // sorts ahead of any middleware that isn't — including the shared-
        // secret gate. Without this line an authenticated /api/v1/* endpoint
        // 401s on a missing PAT before EnsureMarketingCaller can 404 on the
        // missing secret, which leaks the endpoint's existence.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\EnsureMarketingCaller::class,
        );

        // All /api/* routes are protected by auth:sanctum — no CSRF needed.
        // Logout is exempted so an expired session (stale form token) still lets
        // the user sign out cleanly instead of hitting a 419 PAGE EXPIRED wall.
        $middleware->validateCsrfTokens(except: ['api/*', 'logout']);

        // A guest who lands on a control-panel URL belongs at the control-panel
        // sign-in, not the workspace one — the two are separate doors.
        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            $panel = config('app.super_admin_prefix', 'admin-control-panel');

            return $request->is($panel, $panel . '/*')
                ? route('superadmin.login')
                : route('login');
        });

        $middleware->alias([
            'role'         => \App\Http\Middleware\CheckRole::class,
            'role_path'    => \App\Http\Middleware\EnsureCanonicalRolePath::class,
            'subscription' => \App\Http\Middleware\CheckSubscription::class,
            'module'       => \App\Http\Middleware\CheckPlanModule::class,
            'api.key'      => \App\Http\Middleware\AuthenticateWithApiKey::class,
            // Server-to-server guard on /api/v1/auth/*: only the marketing app
            // on wavadesk.com, proving itself with the shared secret, gets in.
            'wavadesk.caller' => \App\Http\Middleware\EnsureMarketingCaller::class,
            'webchat.widget'  => \App\Http\Middleware\WebChat\ResolveWebChatWidget::class,
            'webchat.domain'  => \App\Http\Middleware\WebChat\WebChatDomainGuard::class,
            'webchat.visitor' => \App\Http\Middleware\WebChat\WebChatVisitorAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
