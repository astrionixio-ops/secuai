# SecuAI — PHP/MySQL Backend

Astrionix SecuAI ported from Supabase (Postgres + Edge Functions) to Laravel 11 (PHP 8.3 + MySQL 8) for self-hosted deployment.

**Hosting setup:** IONOS VPS (where the app runs) + Hostinger DNS (where `astrionix.io` is registered). Target URL: `https://security.astrionix.io`. See `DEPLOY.md` for the full architecture diagram and step-by-step deploy.

## Status

This is **Phase 1 of 6**. What works today:

- Multi-tenant foundation with strict isolation (middleware + global scope + cross-tenant tests)
- Auth: signup, login, logout, JWT refresh, `/me`
- Tenants: create workspace, list user's workspaces, accept invite
- Invites: admin-only create/list/revoke, 7-day expiry, single-use tokens
- Audit log infrastructure (every state change logged)
- Rate limiting on auth/tenant-create/invites
- Enum-as-lookup-table pattern (no painful MySQL ALTERs later)
- VPS deployment scripts (Nginx, PHP-FPM, MySQL, Redis, Supervisor, UFW, Fail2ban)
- Cross-tenant security tests

What's coming in later phases — listed at the bottom.

## Phase plan

| Phase | Scope | State |
|---|---|---|
| 1 | Foundation: auth, tenants, invites, audit log, deployment | **shipped** |
| 2 | Core domain: environments, assets, scans, findings, frameworks, controls, assessments, evidence | next |
| 3 | Workflow: evidence packs + reviews, gaps, risks, remediation tasks, policies, acknowledgements | |
| 4 | Integrations: Paddle billing, AI analyst, Jira sync, SIEM sinks, notifications, scheduled jobs | |
| 5 | Frontend: keep React, build compatibility API layer | |
| 6 | Production hardening: backups, monitoring, log rotation, runbook | |

## Local development

Requires: PHP 8.3, Composer 2, MySQL 8 (or MariaDB 11), Redis, Node 20.

```bash
git clone <this-repo> secuai && cd secuai
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# Create DB then:
php artisan migrate

# Run tests — these MUST pass:
php artisan test --filter=CrossTenantIsolation

# Serve:
php artisan serve
```

Hit `http://localhost:8000/api/health` to confirm the stack is up.

## Deploy on IONOS VPS (KVM-class: 2 GB RAM, 2 vCPU, Ubuntu 22.04)

This codebase ships with a complete VPS bootstrap. **For step-by-step instructions including DNS setup at Hostinger, see `DEPLOY.md`.** Quick summary below:

### 1. SSH in as root and run the bootstrap

```bash
scp deploy/vps-setup.sh root@108.175.8.74:/root/
ssh root@108.175.8.74
APP_DOMAIN=secuai.yourdomain.com bash /root/vps-setup.sh
```

This installs PHP 8.3, MySQL 8 (tuned for 2 GB), Redis, Nginx, Certbot, Composer, Node 20, Supervisor, UFW, Fail2ban, adds a 2 GB swap file, and creates a `deploy` user.

### 2. Set up MySQL

```bash
sudo mysql_secure_installation
sudo mysql <<SQL
CREATE DATABASE secuai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'secuai'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL ON secuai.* TO 'secuai'@'localhost';
FLUSH PRIVILEGES;
SQL
```

### 3. Add your SSH key to the deploy user

```bash
sudo -u deploy bash -c 'cat >> ~/.ssh/authorized_keys' <<< 'ssh-ed25519 AAAA...your-key... you@laptop'
```

After this, work as `deploy`, not root.

### 4. Deploy the code

```bash
ssh deploy@108.175.8.74
cd /var/www/secuai
git clone <your-private-repo> .
composer install --no-dev --optimize-autoloader
cp .env.example .env
nano .env   # fill in DB_PASSWORD, APP_URL, SECRETS_ENCRYPTION_KEY, etc.
php artisan key:generate
php artisan jwt:secret
php artisan migrate --force
php artisan storage:link
```

### 5. Wire up Nginx

```bash
sudo cp deploy/nginx-secuai.conf /etc/nginx/sites-available/secuai
sudo sed -i 's/secuai.example.com/secuai.yourdomain.com/g' /etc/nginx/sites-available/secuai
sudo ln -s /etc/nginx/sites-available/secuai /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# HTTPS (point your DNS at 108.175.8.74 first):
sudo certbot --nginx -d secuai.yourdomain.com
```

### 6. Wire up queue workers + cron

```bash
sudo cp deploy/supervisor-secuai.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start secuai-worker:*

sudo -u deploy crontab deploy/cron.txt
```

### 7. Verify

```bash
curl https://secuai.yourdomain.com/api/health
# {"ok":true,"time":"2026-04-30T...","version":"phase-1"}
```

## Project layout

```
app/
  Models/                        Eloquent models (User, Tenant, TenantMember, ...)
  Models/Concerns/
    BelongsToTenant.php          Global scope + auto tenant_id fill
  Http/
    Controllers/Api/             AuthController, TenantController, InviteController
    Middleware/
      ResolveTenant.php          Sets the current tenant from request
      EnsureTenantRole.php       admin/auditor/analyst/viewer gate
  Services/
    TenantService.php            createWithOwner, acceptInvite, role helpers
    ActivityLogger.php           Single chokepoint for audit log writes
  Support/
    TenantContext.php            Request-scoped current tenant holder

database/
  migrations/
    000001  users + sessions + password_reset_tokens
    000002  enum lookup tables (24 enums)
    000003  Laravel infrastructure (cache, jobs, failed_jobs)
    000010  tenants
    000011  tenant_members, tenant_invites, profiles, user_roles, cookie_consent
    000012  activity_log, error_events
  factories/
    UserFactory.php

routes/
  api.php                        Phase 1 routes only (auth + tenants + invites)

tests/
  Feature/Security/
    CrossTenantIsolationTest.php The most important file in the repo

deploy/
  vps-setup.sh                   Idempotent fresh-VPS bootstrap
  nginx-secuai.conf              Nginx server block
  supervisor-secuai.conf         Queue worker config
  cron.txt                       Laravel scheduler cron entry

config/
  auth.php                       JWT guard config
  secuai.php                     App-specific flags (trial days, plan limits, etc.)
```

## Security model — read this before you build on it

**Tenant isolation is enforced at four independent layers:**

1. **Middleware (`ResolveTenant`)**: every tenant-scoped route has `auth:api` + `tenant`. The middleware verifies the user is a member of the requested tenant before the controller runs.
2. **Global scope (`BelongsToTenant`)**: every tenant-scoped Eloquent model adds `WHERE tenant_id = :current` automatically. Read `app/Models/Concerns/BelongsToTenant.php` to see how it fails closed if no tenant is set.
3. **Service layer**: `TenantService` always passes `tenant_id` explicitly. Don't bypass it.
4. **Tests**: `tests/Feature/Security/CrossTenantIsolationTest.php` exercises the full HTTP stack with two real tenants. Run before every deploy.

**Roles live on `tenant_members`, never on `users`.** Putting a `role` column on `users` is the classic privilege-escalation footgun — any user-update endpoint becomes a privilege escalation.

**Field-level encryption (Phase 2+)**: `cloud_credentials.secret_encrypted` and `integrations.secret_encrypted` are encrypted with `SECRETS_ENCRYPTION_KEY` (AES-256-GCM, app-level). Disk encryption alone is not enough — you want the secrets inert if someone steals a DB dump.

## Tuning notes for 2 GB VPS

- MySQL `innodb_buffer_pool_size = 384M` (set by `vps-setup.sh`). On a 4 GB box, bump to 1G.
- PHP-FPM `pm.max_children = 12`, `pm = ondemand`. On a 4 GB box, bump to 25 and try `dynamic`.
- Supervisor: 2 default workers + 1 long-running. On a 4 GB box, 4 + 2.
- Redis `maxmemory 128mb` with LRU eviction.
- 2 GB swapfile created with `vm.swappiness=10`. Treat swap as insurance, not normal operation — if you're swapping under load, upgrade to KVM 4.

## What's NOT in Phase 1

These are deliberately deferred. Don't try to build on top of them yet:

- Email sending (verification, invites, password reset) — Phase 1.1, simple to add
- Google OAuth — Phase 1.1
- All domain models (findings, scans, assessments, etc.) — Phase 2
- Billing, AI, integrations — Phase 4
- Frontend integration — Phase 5
- Backup automation, monitoring (Sentry/Bugsnag), log rotation — Phase 6
