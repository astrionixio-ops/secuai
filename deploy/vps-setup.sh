#!/usr/bin/env bash
# ============================================================================
# SecuAI VPS Bootstrap — IONOS VPS (Ubuntu 22.04, 2GB RAM, 2 vCPU)
# Target VPS:    108.175.8.74  (IONOS)
# Target domain: security.astrionix.io  (DNS managed at Hostinger)
# ============================================================================
# Run as root on a FRESH box. Idempotent — safe to re-run.
#
#   scp deploy/vps-setup.sh root@108.175.8.74:/root/
#   ssh root@108.175.8.74 'bash /root/vps-setup.sh'
#
# What it does:
#   1. System update + 2 GB swap (insurance against OOM on a 2 GB box)
#   2. Installs PHP 8.3 + extensions Laravel needs
#   3. Installs MySQL 8 with small-server tuning (innodb_buffer_pool=384M)
#   4. Installs Redis (small mem cap)
#   5. Installs Nginx + Certbot (Let's Encrypt)
#   6. Installs Composer + Node.js 20 (for the React frontend build later)
#   7. Installs Supervisor (runs queue workers)
#   8. Creates 'deploy' user with limited sudo
#   9. UFW firewall: 22, 80, 443 only
#  10. Fail2ban for SSH brute-force protection
# ============================================================================
set -euo pipefail

if [[ $EUID -ne 0 ]]; then
    echo "Run as root." >&2
    exit 1
fi

DEPLOY_USER="${DEPLOY_USER:-deploy}"
APP_DOMAIN="${APP_DOMAIN:-security.astrionix.io}"
APP_DIR="/var/www/secuai"

echo "==> 1/10  System update"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -yq
apt-get install -yq software-properties-common curl wget gnupg ca-certificates lsb-release apt-transport-https unzip git ufw fail2ban htop

echo "==> 2/10  Swap (2 GB on a 2 GB box — strongly recommended)"
if [[ ! -f /swapfile ]]; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
    echo 'vm.swappiness=10' >> /etc/sysctl.conf
    sysctl -p >/dev/null
fi

echo "==> 3/10  PHP 8.3 + extensions"
add-apt-repository -y ppa:ondrej/php >/dev/null
apt-get update -qq
apt-get install -yq \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis php8.3-mbstring \
    php8.3-xml php8.3-zip php8.3-curl php8.3-bcmath php8.3-gd \
    php8.3-intl php8.3-imagick php8.3-soap php8.3-opcache

# PHP-FPM tuning for 2 GB box
PHPFPM_POOL=/etc/php/8.3/fpm/pool.d/www.conf
sed -i 's/^pm = .*/pm = ondemand/' "$PHPFPM_POOL"
sed -i 's/^pm.max_children = .*/pm.max_children = 12/' "$PHPFPM_POOL"
sed -i 's/^;pm.process_idle_timeout.*/pm.process_idle_timeout = 10s/' "$PHPFPM_POOL"
sed -i 's/^;pm.max_requests.*/pm.max_requests = 500/' "$PHPFPM_POOL"

# php.ini hardening
PHP_INI=/etc/php/8.3/fpm/php.ini
sed -i 's/^memory_limit = .*/memory_limit = 256M/' "$PHP_INI"
sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 50M/' "$PHP_INI"
sed -i 's/^post_max_size = .*/post_max_size = 50M/' "$PHP_INI"
sed -i 's/^;opcache.enable=.*/opcache.enable=1/' "$PHP_INI"
sed -i 's/^;opcache.memory_consumption=.*/opcache.memory_consumption=128/' "$PHP_INI"
sed -i 's/^expose_php = .*/expose_php = Off/' "$PHP_INI"

systemctl enable --now php8.3-fpm

echo "==> 4/10  MySQL 8"
apt-get install -yq mysql-server
# Small-server tuning — critical on 2 GB. Default config eats ~1.5 GB.
cat > /etc/mysql/mysql.conf.d/secuai.cnf <<'EOF'
[mysqld]
# Tuned for 2 GB VPS. Bump on bigger boxes.
innodb_buffer_pool_size = 384M
innodb_log_file_size = 64M
max_connections = 60
table_open_cache = 1024
tmp_table_size = 32M
max_heap_table_size = 32M
performance_schema = OFF
default_authentication_plugin = mysql_native_password
EOF
systemctl restart mysql
systemctl enable mysql

echo "==> 5/10  Redis"
apt-get install -yq redis-server
sed -i 's/^# maxmemory .*/maxmemory 128mb/' /etc/redis/redis.conf
sed -i 's/^# maxmemory-policy .*/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf
sed -i 's/^supervised .*/supervised systemd/' /etc/redis/redis.conf
systemctl enable --now redis-server

echo "==> 6/10  Nginx + Certbot"
apt-get install -yq nginx certbot python3-certbot-nginx
systemctl enable --now nginx

echo "==> 7/10  Composer + Node.js 20"
if ! command -v composer >/dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer >/dev/null
fi
if ! command -v node >/dev/null; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -yq nodejs
fi

echo "==> 8/10  Supervisor"
apt-get install -yq supervisor
systemctl enable --now supervisor

echo "==> 9/10  Deploy user"
if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
    adduser --disabled-password --gecos '' "$DEPLOY_USER"
    usermod -aG www-data "$DEPLOY_USER"
fi
mkdir -p "/home/$DEPLOY_USER/.ssh"
chmod 700 "/home/$DEPLOY_USER/.ssh"
touch "/home/$DEPLOY_USER/.ssh/authorized_keys"
chmod 600 "/home/$DEPLOY_USER/.ssh/authorized_keys"
chown -R "$DEPLOY_USER:$DEPLOY_USER" "/home/$DEPLOY_USER/.ssh"

mkdir -p "$APP_DIR"
chown -R "$DEPLOY_USER:www-data" "$APP_DIR"
chmod -R 2775 "$APP_DIR"

echo "==> 10/10 Firewall + Fail2ban"
ufw default deny incoming
ufw default allow outgoing
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
systemctl enable --now fail2ban

echo
echo "================================================================"
echo " VPS bootstrap complete for: $APP_DOMAIN"
echo "================================================================"
echo
echo " Next steps (manual) — see DEPLOY.md for full walkthrough:"
echo
echo "   1. Set MySQL root password and create the secuai database:"
echo "        sudo mysql_secure_installation"
echo "        sudo mysql -e \"CREATE DATABASE secuai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\""
echo "        sudo mysql -e \"CREATE USER 'secuai'@'localhost' IDENTIFIED BY 'CHANGE_ME';\""
echo "        sudo mysql -e \"GRANT ALL ON secuai.* TO 'secuai'@'localhost'; FLUSH PRIVILEGES;\""
echo
echo "   2. Add your SSH public key to: /home/$DEPLOY_USER/.ssh/authorized_keys"
echo
echo "   3. Point DNS for $APP_DOMAIN at 108.175.8.74 (A record)."
echo
echo "   4. Deploy code to $APP_DIR (see DEPLOY.md)."
echo
echo "   5. Get HTTPS:"
echo "        sudo certbot --nginx -d $APP_DOMAIN"
echo
