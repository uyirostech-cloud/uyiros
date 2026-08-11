# ClinicFlow — Hostinger VPS Deployment (CI/CD)

**Target:** `https://uyiros.tech` · Hostinger KVM VPS (Ubuntu, nginx + PHP-FPM) ·
MySQL/MariaDB on the same VPS.

> **Nothing deploys to production without explicit approval.** The GitHub Actions workflow below
> is manually triggered (`workflow_dispatch`) and gated by a repository variable — it never runs
> on every push.

## 1. Why this setup

A VPS gives root access, so none of the shared-hosting workarounds apply: no `.htaccess`
guessing games, no hPanel-managed folder conventions to reverse-engineer, a real systemd-managed
nginx + PHP-FPM + MySQL stack, and a normal SSH-based deploy. The app itself didn't change —
PHP 8, MySQL/MariaDB, a static React build — only how it gets onto the server.

## 2. Server layout

```
/var/www/uyiros.tech/
├── public/                      ← nginx document root (web-reachable)
│   ├── index.html               ← Vite build
│   ├── assets/                  ← Vite build (hashed js/css)
│   └── api/
│       └── index.php            ← copy of server/public/index.php
├── app/                         ← NOT web-reachable
│   ├── bootstrap.php  config/  app/  routes/  database/  scripts/
│   └── .env                     ← production credentials (chmod 600, never in Git)
└── storage/                     ← NOT web-reachable
    ├── uploads/<organization_id>/…
    └── logs/
```

`public/api/index.php` contains only:

```php
<?php
define('CLINICFLOW_BASE', '/var/www/uyiros.tech/app');
require CLINICFLOW_BASE . '/bootstrap.php';
(new App\Core\Kernel())->handle()->send();
```

Everything else lives outside the document root, so no PHP class file, `.env`, migration or
upload is directly fetchable over HTTP.

## 3. One-time server setup

Run once, as root, on a fresh VPS:

```bash
apt update && apt install -y nginx mariadb-server php8.3-fpm php8.3-cli php8.3-mysql \
  php8.3-mbstring php8.3-xml php8.3-curl unzip certbot python3-certbot-nginx
systemctl enable --now nginx mariadb php8.3-fpm

# database
mysql -u root <<'SQL'
CREATE DATABASE clinicflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'clinicflow'@'localhost' IDENTIFIED BY 'REPLACE_WITH_A_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON clinicflow.* TO 'clinicflow'@'localhost';
FLUSH PRIVILEGES;
SQL

# directories
mkdir -p /var/www/uyiros.tech/{public/api,app,storage/uploads,storage/logs}
chown -R www-data:www-data /var/www/uyiros.tech
```

### nginx (`/etc/nginx/sites-available/uyiros.tech`)

```nginx
server {
    listen 80;
    server_name uyiros.tech www.uyiros.tech;

    root /var/www/uyiros.tech/public;
    index index.html;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api/ {
        try_files $uri $uri/ /api/index.php?$query_string;
    }

    location ~ ^/api/.*\.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(env|ht) {
        deny all;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/uyiros.tech /etc/nginx/sites-enabled/uyiros.tech
nginx -t && systemctl reload nginx
certbot --nginx -d uyiros.tech -d www.uyiros.tech
```

### `app/.env` (created once by hand, chmod 600, never touched by CI/CD)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://uyiros.tech
API_URL=https://uyiros.tech/api
APP_KEY=<php scripts/genkey.php>
SESSION_SECRET=<php scripts/genkey.php>
SESSION_COOKIE_SECURE=true
SESSION_COOKIE_SAMESITE=Lax

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=clinicflow
DB_USER=clinicflow
DB_PASSWORD=<the password set above>

STORAGE_PATH=/var/www/uyiros.tech/storage
CORS_ALLOWED_ORIGINS=
```

Then run migrations once and create the first platform admin:

```bash
php /var/www/uyiros.tech/app/database/migrate.php
php /var/www/uyiros.tech/app/database/seed.php
```

## 4. CI/CD — GitHub Actions

`.github/workflows/deploy.yml` builds the frontend, syncs both it and the PHP backend to the
VPS over SSH, runs migrations, and health-checks the result. It **only** runs when manually
triggered from the Actions tab, and only if the `DEPLOY_ENABLED` repository variable is `true` —
both are deliberate gates so a routine push never touches production.

### Required GitHub secrets (repo Settings → Secrets and variables → Actions)

| Secret | Value |
|---|---|
| `DEPLOY_SSH_HOST` | VPS IP or hostname |
| `DEPLOY_SSH_PORT` | usually `22` |
| `DEPLOY_SSH_USER` | e.g. `root` or a deploy user with sudo |
| `DEPLOY_SSH_KEY` | private key whose public half is in the VPS's `~/.ssh/authorized_keys` |
| `DEPLOY_TARGET_PATH` | `/var/www/uyiros.tech` |

### Required repository variable

| Variable | Value |
|---|---|
| `DEPLOY_ENABLED` | `true` — flip to `false` to hard-disable deploys without touching the workflow |

No database credentials go into GitHub — `.env` lives only on the VPS and is never overwritten
by the sync (see the workflow's `--exclude`).

### Triggering a deploy

GitHub → **Actions** tab → **Deploy** workflow → **Run workflow**. Pick the branch, confirm.

## 5. Database migration procedure

```bash
# 1. back up first — always
mysqldump -u clinicflow -p clinicflow > ~/backups/clinicflow-$(date +%F-%H%M).sql

# 2. check what will run
php /var/www/uyiros.tech/app/database/migrate.php --status

# 3. apply (the CI/CD workflow does this automatically after a deploy)
php /var/www/uyiros.tech/app/database/migrate.php
```

Migrations are additive by design (add table / add column / add index), so the previous release
keeps working against the new schema during a deploy.

## 6. Rollback procedure

**Code:** re-run the GitHub Actions workflow against the previous good commit/tag — `rsync
--delete` means the deployed tree exactly matches whatever git ref was built, so redeploying an
older commit fully reverts the code.

**Database:**
```bash
mysql -u clinicflow -p clinicflow < ~/backups/clinicflow-<timestamp>.sql
```

**Verify after any rollback:** `curl -s https://uyiros.tech/api/health` returns
`{"status":"ok","db":"ok"}`, login works, and one invoice's `paid_amount` still equals the sum of
its payments.

## 7. Operational notes

* **Backups:** a weekly `mysqldump` cron on the VPS (`crontab -e` as root), retained 30 days, plus
  whatever Hostinger's own VPS snapshot feature provides.
* **Cron:** any scheduled job (follow-up reminders, low-stock digest) runs as
  `php /var/www/uyiros.tech/app/scripts/cron.php <job>` via a normal crontab entry — no daemon
  required.
* **Logs:** application errors → `storage/logs/app-YYYY-MM-DD.log`; PHP errors are **not**
  displayed (`APP_DEBUG=false`), only logged. nginx/PHP-FPM logs are in their usual system
  locations (`/var/log/nginx/`, `/var/log/php8.3-fpm.log`).
* **What's never on the server or in Git:** `node_modules/`, `src/`, `.git/`, test fixtures, demo
  seed data, the `.env` file (created once by hand, excluded from every sync).
