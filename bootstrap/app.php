<?php

use App\Http\Middleware\EnsureTenantRole;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'tenant.role' => EnsureTenantRole::class,
        ]);

        // Rate limiters — defined here so they're configured before routes load.
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
        RateLimiter::for('tenant-create', fn (Request $r) => Limit::perHour(5)->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('invites', fn (Request $r) => Limit::perHour(50)->by(optional($r->user())->id ?: $r->ip()));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by(optional($r->user())->id ?: $r->ip()));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Force JSON for API errors. Laravel's default already does this for
        // /api/* routes, but be explicit.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })
    ->create();
