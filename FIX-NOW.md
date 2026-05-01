# Fix the Current Partial Deploy

You have SecuAI partially deployed at `/var/www/secuai/` on your IONOS VPS, alongside other apps (`ai.astrionix.io`, `musicmuse-media`, `n8n`). This guide gets you from where you are now to a working deploy without breaking your other apps.

## Where you are now

Based on your VPS state:

| Item | State |
|---|---|
| Code at `/var/www/secuai/` | Partial — missing `artisan` and several config files |
| `composer install` ran | Yes (vendor/ exists) |
| `.env` configured | No — still the example template |
| MySQL installed | **No, or not running** |
| Redis installed | **No** |
| Nginx site for SecuAI | **No** — Nginx serves your other 4 apps but not SecuAI |
| `curl` installed | **No** |
| RAM available | 15 GB free, 1.1 GB used — plenty of room |
| Currently logged in as | `root` (should be `deploy` for normal work) |
| Other apps running | Nginx (4 sites), n8n (port 5678), Ollama (port 11434), Docker |

## Strategy

We will NOT re-run `vps-setup.sh` — it would touch global Nginx and MySQL configs that affect your other apps. Instead we'll surgically install only what's missing.

---

## Step 1 — Install missing system packages

You're already root, so no sudo needed:

```bash
apt-get update
apt-get install -y curl mysql-server redis-server php8.3-mysql php8.3-redis php8.3-mbstring php8.3-xml php8.3-zip php8.3-curl php8.3-bcmath php8.3-gd php8.3-intl supervisor
```

**Why each one:**
- `curl` — for testing the API
- `mysql-server` — the database (none is currently installed)
- `redis-server` — cache/queue backend
- `php8.3-mysql` and other PHP modules — needed by Laravel
- `supervisor` — runs queue workers

This won't conflict with your existing apps. MySQL will install fresh and start with default settings. The `php8.3-*` packages just add modules to your existing PHP.

After install, verify everything is running:

```bash
systemctl status mysql --no-pager | head -5
systemctl status redis-server --no-pager | head -5
```

Both should say `active (running)`.

---

## Step 2 — Update the SecuAI code with the missing files

The code at `/var/www/secuai/` is missing `artisan` (Laravel's CLI entry point) and several config files. **You need to download the updated zip and copy these specific files in.**

If you used git for the initial clone, the cleanest fix is to commit the new files locally on your laptop, push, then `git pull` on the VPS:

**On your laptop (Git Bash):**
```bash
# In your secuai-php folder, REPLACE with the files from the new zip
# (just unzip on top — it'll overwrite/add files).
# Then:
cd ~/Downloads/secuai-php
git add -A
git commit -m "Add missing artisan + config files"
git push
```

**On the VPS:**
```bash
cd /var/www/secuai
git pull
chmod +x artisan   # ensure executable
```

**Alternative if you didn't use git yet** — just SCP the new files up. From your laptop:
```bash
# From the new unzipped folder:
scp artisan root@108.175.8.74:/var/www/secuai/
scp config/*.php root@108.175.8.74:/var/www/secuai/config/
ssh root@108.175.8.74 "chmod +x /var/www/secuai/artisan"
```

Verify on the VPS:
```bash
cd /var/www/secuai
ls -la artisan
ls config/
# Should now include: app.php, auth.php, cache.php, database.php, filesystems.php,
# jwt.php, logging.php, mail.php, queue.php, secuai.php, services.php, session.php
```

---

## Step 3 — Create the storage and bootstrap/cache directories

Laravel needs writable folders for caches, logs, and sessions. The new zip includes `.gitkeep` files for these but git doesn't track empty folders well. Create them manually if missing:

```bash
cd /var/www/secuai
mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
```

---

## Step 4 — Set up the MySQL database

```bash
# Set MySQL root password (skips through prompts):
mysql_secure_installation
# Answer:
#   - Validate password component? n
#   - New root password: pick a strong one and SAVE IT
#   - Remove anonymous users? y
#   - Disallow root login remotely? y
#   - Remove test database? y
#   - Reload privilege tables? y

# Create the secuai database and user:
mysql <<'SQL'
CREATE DATABASE secuai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'secuai'@'localhost' IDENTIFIED BY 'PICK_A_STRONG_PASSWORD_HERE';
GRANT ALL ON secuai.* TO 'secuai'@'localhost';
FLUSH PRIVILEGES;
SQL
```

**Replace `PICK_A_STRONG_PASSWORD_HERE` with an actual strong password and save it** — you'll paste it into `.env` next.

---

## Step 5 — Configure the .env file

```bash
cd /var/www/secuai
nano .env
```

**Change these specific lines** (leave everything else alone for now):

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://security.astrionix.io
APP_FRONTEND_URL=https://security.astrionix.io

DB_PASSWORD=PICK_A_STRONG_PASSWORD_HERE   # the one you set in Step 4

# Generate this on the command line first:
#   php -r "echo base64_encode(random_bytes(32)).PHP_EOL;"
# Paste output here:
SECRETS_ENCRYPTION_KEY=PASTE_GENERATED_VALUE_HERE
```

Save with Ctrl+O, Enter, Ctrl+X.

---

## Step 6 — Generate Laravel keys and run migrations

```bash
cd /var/www/secuai

# Laravel app key (encrypts cookies):
php artisan key:generate

# JWT signing secret:
php artisan jwt:secret

# Build the database schema (creates all 13 Phase 1 tables):
php artisan migrate --force
```

**Expected output of `migrate --force`** (last few lines):
```
INFO  Running migrations.
2026_05_01_000001_create_users_table .................. 50ms DONE
2026_05_01_000002_create_enum_lookup_tables ........... 200ms DONE
2026_05_01_000003_create_laravel_infrastructure_tables  80ms DONE
2026_05_01_000010_create_tenants_table ................ 30ms DONE
2026_05_01_000011_create_tenant_membership_tables ..... 100ms DONE
2026_05_01_000012_create_activity_and_error_tables .... 50ms DONE
```

If migrate fails with "Access denied", your DB password in `.env` doesn't match what you set in MySQL. Re-set it in MySQL or fix `.env`.

---

## Step 7 — Cache configs and set permissions

```bash
cd /var/www/secuai

# Cache configs and routes for production speed:
php artisan config:cache
php artisan route:cache

# Fix file ownership — Nginx (running as www-data) needs to read these,
# and Laravel (running via PHP-FPM as www-data) needs to write to storage/.
chown -R root:www-data /var/www/secuai
chmod -R 775 /var/www/secuai/storage /var/www/secuai/bootstrap/cache
```

---

## Step 8 — Add SecuAI as a new Nginx site (alongside your existing 4)

Create the config:

```bash
nano /etc/nginx/sites-available/secuai
```

Paste this **(this is your existing nginx config from `deploy/nginx-secuai.conf` but slightly trimmed for your environment — your other sites already define the rate limit zones, so I'll use unique names to avoid conflict):**

```nginx
limit_req_zone $binary_remote_addr zone=secuai_api:10m rate=30r/s;
limit_req_zone $binary_remote_addr zone=secuai_auth:10m rate=5r/s;

server {
    listen 80;
    listen [::]:80;
    server_name security.astrionix.io;

    root /var/www/secuai/public;
    index index.php;

    client_max_body_size 50M;
    server_tokens off;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location /api/auth/ {
        limit_req zone=secuai_auth burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /api/ {
        limit_req zone=secuai_api burst=50 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 60s;
    }

    location ~ /\.(ht|env|git) { deny all; }
    location ~ ^/(storage|bootstrap)/ { deny all; }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff2?)$ {
        expires 30d;
        access_log off;
    }

    access_log /var/log/nginx/secuai.access.log;
    error_log /var/log/nginx/secuai.error.log warn;
}
```

Save (Ctrl+O, Enter, Ctrl+X). Enable the site:

```bash
ln -s /etc/nginx/sites-available/secuai /etc/nginx/sites-enabled/

# Test config doesn't break your other sites:
nginx -t
# Should print: "syntax is ok" and "test is successful"

# Reload (this does NOT drop existing connections — your other apps stay up):
systemctl reload nginx
```

If `nginx -t` reports an error about duplicate `limit_req_zone`, it means one of your existing sites already defines `secuai_api` or `secuai_auth`. Unlikely but: rename them in the SecuAI config (e.g., `secuai_api2`) and update the `zone=` references below.

---

## Step 9 — Verify it works (HTTP first, before SSL)

**Make sure DNS is pointing security.astrionix.io to 108.175.8.74** in your Hostinger DNS panel. Verify:

```bash
# From your laptop:
dig +short security.astrionix.io
# Should return: 108.175.8.74
```

**Then on the VPS:**
```bash
curl -i http://localhost/api/health -H "Host: security.astrionix.io"
# Expected: HTTP/1.1 200 OK and JSON body {"ok":true,"time":"...","version":"phase-1"}
```

**From your laptop:**
```bash
curl http://security.astrionix.io/api/health
# Same response.
```

If you get this — Phase 1 is alive.

---

## Step 10 — Add HTTPS

```bash
apt-get install -y certbot python3-certbot-nginx
certbot --nginx -d security.astrionix.io
```

Answer the prompts:
- Email: yours
- Terms: A
- Newsletter: N
- Redirect HTTP → HTTPS: 2 (Yes)

Verify:
```bash
curl https://security.astrionix.io/api/health
```

---

## Step 11 — Smoke test the API

From your laptop:

```bash
# Sign up:
curl -X POST https://security.astrionix.io/api/auth/signup \
  -H "Content-Type: application/json" \
  -d '{
    "email": "you@astrionix.io",
    "password": "TestPass123!",
    "password_confirmation": "TestPass123!",
    "name": "Test User"
  }'
```

Save the `token` from the response, then:

```bash
TOKEN="eyJ0eXAi...paste-real-token-here..."

# Get my profile:
curl https://security.astrionix.io/api/me -H "Authorization: Bearer $TOKEN"

# Create a workspace:
curl -X POST https://security.astrionix.io/api/tenants \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Astrionix Internal"}'
```

If these work — Phase 1 is fully deployed and verified. ✅

---

## What we skipped that's in DEPLOY.md (and why it's fine)

- **`vps-setup.sh`** — wasn't run, that's OK. Your VPS already had PHP and Nginx; we installed only what was missing.
- **`deploy` user** — you're working as root. **Recommended fix later:** `adduser deploy && usermod -aG www-data deploy`, then `chown -R deploy:www-data /var/www/secuai/`. Not urgent.
- **UFW firewall** — IONOS has its own firewall layer. Don't enable UFW on a box that's already serving 4 apps without checking what they need.
- **Supervisor for queue workers** — not needed yet for Phase 1 (no queued jobs in the auth flow). Will be needed in Phase 2+.

---

## When something doesn't work

Paste the **exact command** you ran and the **complete output**. Don't paraphrase, don't summarize — copy/paste both. That's the fastest way for me to diagnose.

Common issues:

**`Class "Tymon\JWTAuth\..." not found`**
JWT package isn't autoloaded. Run:
```bash
cd /var/www/secuai
composer dump-autoload
php artisan config:clear
```

**`SQLSTATE[HY000] [1045] Access denied for user 'secuai'@'localhost'`**
DB password mismatch:
```bash
mysql -e "ALTER USER 'secuai'@'localhost' IDENTIFIED BY 'NEW_PASSWORD';"
nano /var/www/secuai/.env   # update DB_PASSWORD to match
php artisan config:cache
```

**Nginx serves the wrong site / IONOS default page on security.astrionix.io**
Check site is enabled and config has correct server_name:
```bash
ls -la /etc/nginx/sites-enabled/
grep server_name /etc/nginx/sites-enabled/secuai
nginx -t && systemctl reload nginx
```

**`502 Bad Gateway`**
PHP-FPM not running or socket path wrong:
```bash
systemctl status php8.3-fpm
ls /var/run/php/   # confirm php8.3-fpm.sock exists
tail -50 /var/log/nginx/secuai.error.log
tail -50 /var/www/secuai/storage/logs/laravel.log
```
