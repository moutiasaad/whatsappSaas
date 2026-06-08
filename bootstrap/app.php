<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
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
        ]);

        // All /api/* routes are protected by auth:sanctum — no CSRF needed
        $middleware->validateCsrfTokens(except: ['api/*']);

        $middleware->alias([
            'role'         => \App\Http\Middleware\CheckRole::class,
            'role_path'    => \App\Http\Middleware\EnsureCanonicalRolePath::class,
            'subscription' => \App\Http\Middleware\CheckSubscription::class,
            'api.key'      => \App\Http\Middleware\AuthenticateWithApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
