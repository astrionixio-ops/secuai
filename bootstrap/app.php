<?php

use App\Http\Middleware\EnsureTenantRole;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/**
 * Note: Rate limiters are NOT defined here. They live in
 * App\Providers\AppServiceProvider::boot() so they are registered before
 * route resolution runs. Defining them inside withMiddleware() is too late
 * once routes are cached, and you'll get "Rate limiter [auth] is not defined"
 * at request time.
 */
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
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })
    ->create();
