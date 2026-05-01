# Phase 1 — Update Guide

This update fixes 7 bugs that came up during the initial Phase 1 deployment. The deployed VPS already has these fixes applied (we made them live). This guide is for getting your **laptop's git repo** in sync with what's on the VPS.

## What changed and why

| File | Change | Why |
|---|---|---|
| `app/Models/User.php` | Removed `MustVerifyEmail` interface and `->withTimestamps()` from pivot | MustVerifyEmail triggered Laravel's auto-mailer which crashed on missing `verification.verify` route. `withTimestamps()` queries `pivot_updated_at` which doesn't exist on `tenant_members`. |
| `app/Models/Tenant.php` | Removed `->withTimestamps()` from pivot | Same column-not-found bug as above |
| `app/Http/Controllers/Api/AuthController.php` | Cleaner email-verified guard, fixed `me()` to not pass column list | Column list on `belongsToMany->get()` strips pivot data, so `pivot->role` was null |
| `app/Http/Controllers/Api/TenantController.php` | Same fix as AuthController for `index()` | Same pivot bug |
| `bootstrap/app.php` | Removed inline `RateLimiter::for(...)` calls | They run too late once routes are cached → "Rate limiter [auth] is not defined" |
| `app/Providers/AppServiceProvider.php` | **NEW** — rate limiters defined here in `boot()` | Correct registration timing |
| `bootstrap/providers.php` | **NEW** — registers AppServiceProvider | Required by Laravel 11 |
| `config/secuai.php` | `require_email_verification` defaults to `false` | No SMTP wired up yet, can't actually send verification emails |

## How to apply (laptop side)

If you're using git (the recommended path):

```bash
# In your local secuai-fixed folder:
cd ~/downloads/secuai-fixed

# Replace the changed files with the fixed versions from the new zip.
# Easiest: unzip the new zip on top, overwriting:
#   (or copy the files manually if you prefer surgical updates)

# Then commit and push:
git add -A
git status                           # verify changes look right
git commit -m "Phase 1 bug fixes: pivot, rate limiters, email verification"
git push
```

Then on the VPS:

```bash
ssh root@108.175.8.74
cd /var/www/secuai
git pull                             # pulls the changes
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
systemctl restart php8.3-fpm
```

## How to verify it worked

The easiest way: run the included smoke test from your laptop:

```bash
# In Git Bash:
cd ~/downloads/secuai-fixed
chmod +x deploy/smoke-test.sh
./deploy/smoke-test.sh https://security.astrionix.io
```

You need `jq` installed for this — Git Bash usually has it. If not, the script exits with a clear error.

Expected output: 8 sections, each with green checkmarks, ending with "All Phase 1 endpoints working."

If anything fails, the script tells you exactly which check broke and prints the response. Paste that to debug.

## What's deliberately NOT fixed

- **Email sending is still stubbed.** Signup doesn't actually send a verification email. The `Registered` event only fires if `REQUIRE_EMAIL_VERIFICATION=true` is in `.env`, and even then there's no email infrastructure. Phase 1.1 fixes this properly.
- **`bootstrap/cache/` and `storage/` are gitignored.** Make sure they exist on the VPS before running. The earlier deploy already created them.

## Lessons learned (worth bookmarking)

These bit us during the initial deploy. Worth knowing for future:

1. **Use `systemctl restart php8.3-fpm`, not `reload`, after editing PHP files.** `reload` doesn't flush OPcache; `restart` does. About 50ms of brief 502s, then fully fresh code.

2. **After every nano save, verify with `grep`.** It's easy to think nano saved when it didn't (`Ctrl+X` without `Ctrl+O` first). Quick pattern:
    ```bash
    grep "the line you changed" /path/to/file.php
    ```

3. **Don't constrain columns on `belongsToMany->get(['col1', 'col2'])`.** Laravel strips pivot data. Either pass no column list, or include pivot columns explicitly.

4. **`->withTimestamps()` on a pivot requires both `created_at` AND `updated_at`** to exist on the pivot table. If you only have `created_at`, use `->withPivot('created_at')` instead.

5. **Define rate limiters in a service provider's `boot()`, not in `bootstrap/app.php`.** Otherwise route caching breaks them.

6. **Check `config/*.php` env defaults match your code defaults.** A config file saying `env('FOO', true)` overrides any `false` fallback in `config('foo', false)` calls in your code.

7. **In production, `APP_DEBUG=false` is non-negotiable** even during debugging — read errors from `storage/logs/laravel.log` instead of leaking stack traces in API responses.
