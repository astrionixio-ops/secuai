<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Rate limiters MUST be defined in a service provider's boot() method so they
 * are registered before route resolution happens. Defining them in
 * bootstrap/app.php works in dev with route caching off, but breaks in
 * production once you run `php artisan route:cache`.
 *
 * If you need a new rate limiter, add it here AND reference it in routes via
 * ->middleware('throttle:NAME').
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Place model bindings, container singletons, etc. here.
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    private function configureRateLimiters(): void
    {
        // General API limit — falls back to IP for unauthenticated.
        RateLimiter::for('api', fn (Request $r) =>
            Limit::perMinute(120)->by(optional($r->user())->id ?: $r->ip())
        );

        // Auth endpoints (signup/login) — by IP only since the user isn't
        // authenticated yet. Tight limit because credential stuffing.
        RateLimiter::for('auth', fn (Request $r) =>
            Limit::perMinute(10)->by($r->ip())
        );

        // Tenant creation — limit per user. Prevents one user spawning workspaces.
        RateLimiter::for('tenant-create', fn (Request $r) =>
            Limit::perHour(5)->by(optional($r->user())->id ?: $r->ip())
        );

        // Invite creation — generous because admins do bursty invite flows.
        RateLimiter::for('invites', fn (Request $r) =>
            Limit::perHour(50)->by(optional($r->user())->id ?: $r->ip())
        );
    }
}
