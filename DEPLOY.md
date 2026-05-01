# SecuAI Deployment Walkthrough

Target: **security.astrionix.io** → IONOS VPS at **108.175.8.74**

Read this end-to-end before running anything. The whole deploy takes ~30 minutes if it's your first time.

---

## How the pieces fit together — two vendors, one app

```
                                          ┌─────────────────────────────────┐
                                          │  IONOS — the VPS                │
                                          │  108.175.8.74 (Ubuntu)          │
                                          │  ─────                          │
                                          │   Nginx (ports 80/443)          │
   Your laptop  ──── code ────►           │   PHP-FPM (runs Laravel)        │
                                          │   MySQL (the database)          │
                                          │   Redis (cache + queues)        │
                                          │   Supervisor (queue workers)    │
                                          └────────┬────────────────────────┘
                                                   ▲
                                                   │ HTTPS
                                                   │
   ┌──────────────────────────────────────┐        │
   │  HOSTINGER — domain registrar        │        │
   │  ─────                               │        │
   │  astrionix.io is registered here.    │        │
   │  DNS records are managed here.       │        │
   │                                      │        │
   │  We add ONE DNS record:              │        │
   │    security  A  108.175.8.74  ───────┼────────┘
   │                                      │
   │  Browser asks "where is              │
   │  security.astrionix.io?"             │
   │  Hostinger DNS answers "108.175.8.74"│
   │  Browser then talks directly to IONOS│
   └──────────────────────────────────────┘
```

**Two vendors, two jobs:**

- **IONOS** = rents you the Linux box (the VPS). The app actually runs here. Everything on the box (OS updates, Nginx, MySQL, your code, your backups) is **your responsibility**, not IONOS's. Their support helps with hardware/network issues only.
- **Hostinger** = where `astrionix.io` is registered + probably your marketing website. For SecuAI, Hostinger's only job is **DNS** — pointing `security.astrionix.io` at the IONOS IP. Your marketing site at `astrionix.io` keeps living on Hostinger, untouched.

**The connection between them is just DNS.** Once the A record is set, browsers go straight to IONOS. Hostinger is no longer in the request path.

---

## IONOS-specific things to check first

Before you run anything, verify in your **IONOS Cloud Panel** (panel.ionos.com → your server):

1. **Firewall policy** — IONOS has its own firewall layer in front of the VPS, separate from Ubuntu's UFW. Make sure **ports 22, 80, and 443 are allowed**. If port 80 is blocked at the IONOS level, Let's Encrypt SSL will fail with a confusing timeout error.

2. **Root password** — make sure you can SSH in. If you haven't yet, reset it in the IONOS panel.

3. **Reverse DNS (PTR)** — for when we add transactional email later (Phase 1.1). In IONOS panel → server → Network → set the PTR for `108.175.8.74` to `security.astrionix.io`. Without this, invitation/verification emails will land in spam.

4. **Snapshots/backups** — IONOS sells snapshot backups as an add-on. Worth enabling. Note: snapshots are NOT a substitute for MySQL dumps — different recovery scenarios.

**The database**: lives on the same VPS as your code (`DB_HOST=127.0.0.1`). MySQL is installed by `vps-setup.sh`. You create an empty `secuai` database, then `php artisan migrate` builds the tables from migration code.

**The code**: lives at `/var/www/secuai/` on the VPS, owned by the `deploy` user.

---

## Step 0 — Prerequisites on your laptop

You need:

- **SSH client**: built-in on macOS/Linux. On Windows: use Windows Terminal + OpenSSH (built into Windows 10/11) or PuTTY.
- **An SSH key pair**. If you don't have one, make one now:
  ```bash
  ssh-keygen -t ed25519 -C "you@laptop"
  # press Enter through prompts; passphrase recommended
  ```
  This creates `~/.ssh/id_ed25519` (private — never share) and `~/.ssh/id_ed25519.pub` (public — safe to share).

- **Git** (if you go the git route): `git --version` to check.

---

## Step 1 — Point DNS at the IONOS VPS (do this FIRST so it propagates while you work)

DNS for `astrionix.io` is managed at **Hostinger** (your domain registrar). The IONOS VPS doesn't enter into this step at all — we're just telling the internet "the name `security.astrionix.io` resolves to this IP."

**In the Hostinger control panel:**

1. Log in at hpanel.hostinger.com
2. Go to **Domains** → click `astrionix.io` → **DNS / Nameservers** → **DNS Records**
3. Click **Add record** and set:

   | Field | Value |
   |---|---|
   | Type | **A** |
   | Name | **security** *(just the subdomain part — Hostinger appends `.astrionix.io` automatically)* |
   | Points to | **108.175.8.74** |
   | TTL | **3600** *(or "Auto"/default, doesn't matter)* |

4. Save.

**Verify from your laptop** (give it 2-5 minutes first):

```bash
dig +short security.astrionix.io
# Should print: 108.175.8.74
```

If it returns nothing or a different IP, wait 10 more minutes — DNS propagation is usually fast but can be slow. Don't block on this; keep going with the next steps in parallel. SSL setup (step 9) is the only step that actually requires DNS to be live.

**Note: your existing astrionix.io website is unaffected.** That site has its own A record (probably pointing somewhere else, like Hostinger's web hosting servers). We're only adding a NEW record for the `security` subdomain — the root domain and `www` keep working exactly as they do today.

---

## Step 2 — Initial SSH into the VPS as root

```bash
ssh root@108.175.8.74
# Password is what IONOS emailed you, or what you set during VPS provisioning.
```

If this is the first time, accept the host key fingerprint. You're now on the VPS as root.

**Recommended: change the root password immediately:**
```bash
passwd
```

---

## Step 3 — Run the bootstrap script

The bootstrap installs PHP, MySQL, Redis, Nginx, etc. From your **laptop**:

```bash
# Send the script up to the VPS:
scp deploy/vps-setup.sh root@108.175.8.74:/root/

# SSH in and run it:
ssh root@108.175.8.74
bash /root/vps-setup.sh
# Takes ~5-10 minutes. Watch the output.
```

When done, you'll see "VPS bootstrap complete for: security.astrionix.io".

---

## Step 4 — Create the MySQL database

Still on the VPS as root:

```bash
# Secure MySQL (sets root password, removes test DB).
sudo mysql_secure_installation
# Answer Y to most prompts. Set a strong root password and remember it.

# Create the application database and user.
# Replace STRONG_PASSWORD_HERE with a real strong password (save it — you'll
# need it in the .env file in step 7).
sudo mysql <<'SQL'
CREATE DATABASE secuai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'secuai'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL ON secuai.* TO 'secuai'@'localhost';
FLUSH PRIVILEGES;
SQL
```

The database is now empty. Migrations in step 7 build the tables.

---

## Step 5 — Set up SSH access for the deploy user

Working as `root` is bad practice. We use a `deploy` user (created by the bootstrap) for everything from here on.

**On your laptop**, copy your public key:
```bash
cat ~/.ssh/id_ed25519.pub
# It looks like: ssh-ed25519 AAAAC3Nz...long-string... you@laptop
# Copy the entire line.
```

**On the VPS as root**, paste it into the deploy user's authorized_keys:
```bash
nano /home/deploy/.ssh/authorized_keys
# Paste the line, save with Ctrl+O, Enter, Ctrl+X.
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys
```

**Test from your laptop** (open a new terminal, keep root one open in case):
```bash
ssh deploy@108.175.8.74
# Should log in WITHOUT asking for a password (uses your SSH key).
```

If that works, you can stop using root SSH for normal work. (Keep the ability to log in as root — you'll need sudo from `deploy` for system-level stuff.)

---

## Step 6 — Get the code onto the VPS

You picked an upload method earlier. Three options below — do ONE.

### Option A — Git (recommended)

**On your laptop**, push the code to a private repo:

```bash
cd ~/Downloads/secuai-php   # wherever you unzipped
git init
git add -A
git commit -m "Phase 1: foundation"

# Create a private repo on GitHub/GitLab/Bitbucket (use the web UI), then:
git remote add origin git@github.com:YOUR-USERNAME/secuai.git
git branch -M main
git push -u origin main
```

**On the VPS as deploy**:
```bash
ssh deploy@108.175.8.74

# Add a deploy key so the VPS can pull from your repo (read-only access):
ssh-keygen -t ed25519 -C "secuai-vps" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub
# Copy the printed key. In GitHub/GitLab: repo → Settings → Deploy keys → Add.
# Paste it. Read-only is fine.

# Clone:
cd /var/www/secuai
git clone git@github.com:YOUR-USERNAME/secuai.git .
# Note the trailing dot — clones into the current directory.
```

### Option B — scp (direct copy, no git)

**On your laptop**, from the parent dir of the project:

```bash
# Create a tarball excluding garbage:
cd ~/Downloads
tar --exclude='secuai-php/vendor' \
    --exclude='secuai-php/node_modules' \
    --exclude='secuai-php/.env' \
    -czf secuai-php.tar.gz secuai-php/

# Send it up:
scp secuai-php.tar.gz deploy@108.175.8.74:/tmp/

# SSH in and extract:
ssh deploy@108.175.8.74
cd /var/www/secuai
tar -xzf /tmp/secuai-php.tar.gz --strip-components=1
rm /tmp/secuai-php.tar.gz
```

### Option C — SFTP with FileZilla / Cyberduck

1. Open FileZilla. New site:
   - Protocol: **SFTP**
   - Host: **108.175.8.74**
   - Port: **22**
   - Logon Type: **Key file**
   - User: **deploy**
   - Key file: `~/.ssh/id_ed25519`
2. Connect.
3. Remote side: navigate to `/var/www/secuai/`.
4. Local side: navigate to your unzipped `secuai-php` folder.
5. Drag the **contents** (not the folder itself) into `/var/www/secuai/`.

### After any option — verify
```bash
ssh deploy@108.175.8.74
ls /var/www/secuai/
# Should show: app/  bootstrap/  composer.json  config/  database/  deploy/  public/  README.md  routes/  tests/  ...
```

---

## Step 7 — Install dependencies and configure the app

On the VPS as `deploy`:

```bash
cd /var/www/secuai

# Install PHP packages (Laravel itself, JWT auth, etc.)
composer install --no-dev --optimize-autoloader
# Takes 1-2 minutes the first time.

# Create the .env file from the template:
cp .env.example .env
nano .env
```

In `.env`, change these values:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://security.astrionix.io
APP_FRONTEND_URL=https://security.astrionix.io

DB_DATABASE=secuai
DB_USERNAME=secuai
DB_PASSWORD=STRONG_PASSWORD_HERE   # the one you set in step 4

# Generate a 32-byte key for cloud_credentials encryption:
# Run on the VPS: php -r "echo base64_encode(random_bytes(32)).PHP_EOL;"
# Paste the output here:
SECRETS_ENCRYPTION_KEY=PASTE_GENERATED_KEY_HERE

# Mail — leave for now or fill in if you have SMTP creds:
# MAIL_HOST=smtp.hostinger.com
# MAIL_USERNAME=noreply@astrionix.io
# MAIL_PASSWORD=...
```

Save (Ctrl+O, Enter, Ctrl+X).

Then:

```bash
# Generate Laravel app key (encrypts cookies/sessions):
php artisan key:generate

# Generate JWT signing secret:
php artisan jwt:secret

# Build the database tables:
php artisan migrate --force

# Cache config for production speed:
php artisan config:cache
php artisan route:cache

# Set storage permissions:
chmod -R 775 storage bootstrap/cache
sudo chown -R deploy:www-data storage bootstrap/cache
```

If `php artisan migrate` succeeds, you'll see migrations being applied — that's the database getting built.

---

## Step 8 — Wire up Nginx

Still on the VPS, switch to root for system files:

```bash
sudo cp /var/www/secuai/deploy/nginx-secuai.conf /etc/nginx/sites-available/secuai
sudo ln -s /etc/nginx/sites-available/secuai /etc/nginx/sites-enabled/

# Remove the default site (it'd conflict on port 80):
sudo rm -f /etc/nginx/sites-enabled/default

# Test config and reload:
sudo nginx -t
sudo systemctl reload nginx
```

**Test HTTP first** (before SSL):
```bash
# From your laptop:
curl http://security.astrionix.io/api/health
# Should return: {"ok":true,"time":"...","version":"phase-1"}
```

If you get this, the chain is working: DNS → Nginx → PHP-FPM → Laravel.

If DNS hasn't propagated yet, test by IP:
```bash
curl -H "Host: security.astrionix.io" http://108.175.8.74/api/health
```

---

## Step 9 — Get HTTPS (Let's Encrypt)

```bash
sudo certbot --nginx -d security.astrionix.io
# Answer the prompts:
#   Email: your email (for renewal alerts)
#   Terms: Y
#   Newsletter: N (or Y, your call)
#   Redirect HTTP to HTTPS: 2 (Yes — recommended)
```

Certbot rewrites the Nginx config to listen on 443 with the cert and redirect 80→443. Auto-renewal is set up via systemd timer.

**Verify:**
```bash
curl https://security.astrionix.io/api/health
# Same response, now over HTTPS.
```

---

## Step 10 — Wire up queue workers (Supervisor)

```bash
sudo cp /var/www/secuai/deploy/supervisor-secuai.conf /etc/supervisor/conf.d/secuai-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start secuai-worker:*

# Verify they're running:
sudo supervisorctl status
# Should show secuai-worker-default_00, _01, and secuai-worker-long_00 all RUNNING.
```

---

## Step 11 — Wire up cron (Laravel scheduler)

```bash
sudo -u deploy crontab -e
# Add this single line, save:
* * * * * cd /var/www/secuai && php artisan schedule:run >> /dev/null 2>&1
```

For Phase 1 the scheduler is a no-op (no scheduled commands yet), but having cron set up means Phase 4 commands just work when added.

---

## Step 12 — Smoke test

From your laptop:

```bash
# Health check
curl https://security.astrionix.io/api/health

# Create a user
curl -X POST https://security.astrionix.io/api/auth/signup \
  -H "Content-Type: application/json" \
  -d '{
    "email": "you@astrionix.io",
    "password": "TestPass123!",
    "password_confirmation": "TestPass123!",
    "name": "Test User"
  }'
# Returns a JWT token + user object.

# Save the token from the response, then:
TOKEN="eyJ0eXAi...the-token-from-above..."

curl https://security.astrionix.io/api/me \
  -H "Authorization: Bearer $TOKEN"
# Returns user object + empty tenants array.

# Create a workspace:
curl -X POST https://security.astrionix.io/api/tenants \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "Astrionix Internal"}'
# Returns tenant + role:admin.
```

If those all work — **Phase 1 is live.**

---

## Common problems and fixes

**`502 Bad Gateway` from Nginx**
PHP-FPM isn't running, or the socket path is wrong:
```bash
sudo systemctl status php8.3-fpm
sudo systemctl restart php8.3-fpm
sudo tail -50 /var/log/nginx/secuai.error.log
```

**`SQLSTATE[HY000] [1045] Access denied for user 'secuai'@'localhost'`**
Wrong DB password in `.env`. Re-set it:
```bash
sudo mysql -e "ALTER USER 'secuai'@'localhost' IDENTIFIED BY 'NEW_PASSWORD';"
nano /var/www/secuai/.env   # update DB_PASSWORD to match
php artisan config:cache
```

**`Class "Tymon\JWTAuth\..." not found`**
You ran `composer install` with `--no-dev` but the package is fine — try:
```bash
cd /var/www/secuai
composer install --no-dev --optimize-autoloader
php artisan config:cache
```

**`Permission denied` writing to storage/logs**
Ownership got reset:
```bash
sudo chown -R deploy:www-data /var/www/secuai/storage /var/www/secuai/bootstrap/cache
sudo chmod -R 775 /var/www/secuai/storage /var/www/secuai/bootstrap/cache
```

**`certbot` fails with "DNS problem" or "timeout during connect"**
Two possible causes:
1. DNS hasn't propagated yet — wait 15 minutes, retry.
2. **IONOS firewall is blocking port 80.** Let's Encrypt validates by hitting your server on port 80, and IONOS's external firewall (different from UFW on the VPS!) might be blocking it. Check **IONOS Cloud Panel → server → Network → Firewall Policies** and make sure ports 22, 80, 443 are allowed. UFW on the VPS allowing them isn't enough — IONOS's layer has to allow them too.
```bash
sudo certbot --nginx -d security.astrionix.io
```

**Queue worker won't start**
Check Supervisor logs:
```bash
sudo tail -50 /var/log/supervisor/secuai-worker-default.log
sudo supervisorctl tail secuai-worker:secuai-worker-default_00
```

**You see `Whoops, looks like something went wrong` in production**
That means `APP_DEBUG=true` was on. Turn it off:
```bash
nano /var/www/secuai/.env   # APP_DEBUG=false
php artisan config:cache
```
Real errors live in `storage/logs/laravel.log`.

---

## How to update the app later (after this initial deploy)

If you used **git** in step 6:
```bash
ssh deploy@108.175.8.74
cd /var/www/secuai
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
sudo supervisorctl restart secuai-worker:*
```

If you used **scp/SFTP**: re-upload changed files, then run the same commands from `composer install` onward.

(Phase 2+ I can give you a `deploy.sh` that does all of this in one command, if you want zero-downtime deploys.)

---

## Backup reminder

You have ZERO backups right now. Before you put anything important in this DB, set up at least:

```bash
# Daily MySQL dump to /var/backups/secuai/
sudo mkdir -p /var/backups/secuai
sudo nano /etc/cron.daily/secuai-db-backup
```

Paste:
```bash
#!/bin/bash
mysqldump -u secuai -p'STRONG_PASSWORD_HERE' secuai \
  | gzip > /var/backups/secuai/secuai-$(date +\%Y\%m\%d).sql.gz
find /var/backups/secuai/ -name "*.sql.gz" -mtime +14 -delete
```

```bash
sudo chmod +x /etc/cron.daily/secuai-db-backup
```

Phase 6 covers proper offsite backups (snapshot to S3, encrypted).
