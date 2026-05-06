<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use App\Http\Middleware\EnsureUserIsActive;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API group: token-based auth only, so keep middleware stack lean.
        $middleware->api(
            append: [
                SubstituteBindings::class,
            ],
        );

        // Alias for Spatie Permission middlewares and custom active check
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // Exempt CSRF for token-based auth endpoints (handled by Sanctum/guards)
        $middleware->validateCsrfTokens(except: [
            'api/login',
            'api/register',
            'api/forgot-password',
            'api/reset-password',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Force JSON responses for API routes to avoid HTML pages
        $exceptions->shouldRenderJsonWhen(function ($request) {
            return $request->is('api/*');
        });
    })->create();
