# ClinicFlow — Hostinger Shared Hosting Deployment

**Target:** `https://clinic.drawlead.com` · Hostinger shared hosting (Apache + PHP-FPM) ·
Hostinger MySQL.

> **Nothing is deployed to production without explicit approval.** This document is the procedure,
> not an instruction to run it.

## 1. Choosing the deployment method

| Option | How it works | Pros | Cons | Verdict |
|---|---|---|---|---|
| **Hostinger Git integration** (hPanel → Website → GIT) | hPanel pulls a branch into the document root; optional auto-deploy webhook | No secrets in GitHub, one click, built in | **Pulls source only — it cannot run `npm run build`.** The compiled SPA would have to be committed to the repo | Good as a fallback if you commit `dist/`; not preferred |
| **GitHub Actions → SFTP/SSH deploy** | Actions builds the SPA, then rsync/SFTP uploads `dist/` + `server/` | Build happens in CI (no Node needed on the host), artifacts stay out of Git, full control, rollback friendly | Needs SSH/FTP credentials stored as GitHub secrets | **Recommended** |
| **Manual SFTP upload** | Build locally, drag files | No setup | Error-prone, no history | Emergency only |

**Recommendation: GitHub Actions + SSH/rsync** (SSH is available on Hostinger Business/Cloud plans;
on plans without SSH, use the same workflow with an SFTP action). A ready workflow is included at
`.github/workflows/deploy.yml` and is **disabled by default** (`workflow_dispatch` only, plus a
`DEPLOY_ENABLED` repository variable that must equal `true`).

Required GitHub secrets:

| Secret | Value |
|---|---|
| `HOSTINGER_SSH_HOST` | e.g. `123.45.67.89` |
| `HOSTINGER_SSH_PORT` | usually `65002` |
| `HOSTINGER_SSH_USER` | e.g. `u123456789` |
| `HOSTINGER_SSH_KEY` | private key whose public half is in hPanel → Advanced → SSH Access |
| `HOSTINGER_TARGET_PATH` | e.g. `/home/u123456789/domains/clinic.drawlead.com` |

No database credentials go to GitHub — they live only in `server/.env` on the host.

## 2. Server layout

Hostinger serves a subdomain from `~/domains/clinic.drawlead.com/public_html`.

```
~/domains/clinic.drawlead.com/
├── public_html/                 ← DOCUMENT ROOT (web-reachable)
│   ├── index.html               ← Vite build
│   ├── assets/                  ← Vite build (hashed js/css)
│   ├── .htaccess                ← SPA fallback + /api routing + security headers
│   └── api/
│       ├── index.php            ← copy of server/public/index.php
│       └── .htaccess            ← route everything under /api to index.php
├── app/                         ← NOT web-reachable
│   ├── bootstrap.php  config/  app/  routes/  database/
│   └── .env                     ← production credentials (chmod 600, never in Git)
└── storage/                     ← NOT web-reachable
    ├── uploads/<organization_id>/…
    └── logs/
```

`public_html/api/index.php` contains only:

```php
<?php
define('CLINICFLOW_BASE', dirname(__DIR__, 2) . '/app');
require CLINICFLOW_BASE . '/bootstrap.php';
(new App\Core\Kernel())->handle();
```

Everything else lives outside the document root, so no PHP class file, `.env`, migration or upload
is directly fetchable.

### `public_html/.htaccess`

```apache
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# /api/* is handled by public_html/api/.htaccess — leave it alone
RewriteCond %{REQUEST_URI} ^/api/ [NC]
RewriteRule ^ - [L]

# SPA fallback: real files pass through, everything else → index.html
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^ index.html [L]

<IfModule mod_headers.c>
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "DENY"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</IfModule>

<FilesMatch "^\.env">
  Require all denied
</FilesMatch>
```

### `public_html/api/.htaccess`

```apache
RewriteEngine On
RewriteBase /api/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
```

## 3. Environment configuration

`~/domains/clinic.drawlead.com/app/.env` (created on the server, `chmod 600`):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://clinic.drawlead.com
API_URL=https://clinic.drawlead.com/api
APP_KEY=<php scripts/genkey.php>
SESSION_SECRET=<php scripts/genkey.php>
SESSION_COOKIE_SECURE=true
SESSION_COOKIE_SAMESITE=Lax

DB_HOST=localhost
DB_PORT=3306
DB_NAME=u123456789_clinicflow
DB_USER=u123456789_clinic
DB_PASSWORD=<from hPanel>

STORAGE_PATH=/home/u123456789/domains/clinic.drawlead.com/storage
CORS_ALLOWED_ORIGINS=
```

The frontend build is configured with `VITE_API_URL=/api` for production, so the SPA calls the API
same-origin — first-party cookies, no CORS.

## 4. MySQL setup (hPanel → Databases → Management)

1. Create database `u123456789_clinicflow` and user `u123456789_clinic` with a strong password.
2. Grant all privileges on that database to that user. Host stays `localhost`.
3. Character set `utf8mb4`, collation `utf8mb4_unicode_ci`.
4. Do **not** enable remote MySQL unless you must; if you do, whitelist a single IP.
5. Note Hostinger's connection limits — the app opens one PDO connection per request and closes it.

## 5. Domain & HTTPS

1. hPanel → Domains → Subdomains → create `clinic` under `drawlead.com`
   (document root `domains/clinic.drawlead.com/public_html`).
2. DNS: `clinic` A-record → the hosting IP (automatic when the subdomain is created on the same
   account). Allow up to 30 minutes to propagate.
3. hPanel → Security → SSL → install the free Let's Encrypt certificate for
   `clinic.drawlead.com`, then enable **Force HTTPS**.
4. Verify: `curl -I https://clinic.drawlead.com` → `200` and an HSTS header.

## 6. First deployment (manual, once)

```bash
# locally
npm ci && npm run build          # → dist/

# on the server (SSH)
mkdir -p ~/domains/clinic.drawlead.com/{app,storage/{uploads,logs}}
chmod 750 ~/domains/clinic.drawlead.com/app
chmod 770 ~/domains/clinic.drawlead.com/storage/{uploads,logs}

# upload:  dist/*            → public_html/
#          server/public/index.php → public_html/api/index.php  (adjust base path)
#          server/{bootstrap.php,config,app,routes,database} → app/
#          deploy/htaccess/*  → the two .htaccess files above

# create app/.env, then:
cd ~/domains/clinic.drawlead.com/app
php database/migrate.php
php database/seed.php            # permissions + roles only — NOT --demo in production
php scripts/create-admin.php     # interactive: first organization + clinic admin
```

File permissions: directories `755` (`750` for `app`), files `644`, `.env` `600`,
`storage/` writable by the PHP user (`770` on Hostinger).

## 7. Continuous deployment

`.github/workflows/deploy.yml` (disabled until you approve):

1. checkout → `npm ci` → `npm run build`
2. `rsync -az --delete dist/ $USER@$HOST:$TARGET/public_html/` (excluding `api/`, `.htaccess`)
3. `rsync -az --delete server/{bootstrap.php,config,app,routes,database,scripts} $USER@$HOST:$TARGET/app/`
4. `ssh … "cd $TARGET/app && php database/migrate.php"`
5. health check `curl -fsS https://clinic.drawlead.com/api/health`

Triggered by `workflow_dispatch` only. `.env`, `storage/` and `public_html/api/.htaccess` are never
touched by the sync (`--exclude`), so credentials and uploads survive every deploy.

## 8. Database migration procedure

```bash
# 1. back up first — always
mysqldump -u u123456789_clinic -p u123456789_clinicflow \
  > ~/backups/clinicflow-$(date +%F-%H%M).sql

# 2. check what will run
php database/migrate.php --status

# 3. apply
php database/migrate.php
```
Migrations are additive by design (add table / add column / add index), so the previous release
keeps working against the new schema during a deploy.

## 9. Rollback procedure

**Code:**
```bash
git checkout <previous-tag>
npm ci && npm run build
# re-run the rsync steps (or re-run the Actions workflow on the previous tag)
```
Keep the last good build as `public_html.bak` before syncing so the frontend can be restored by a
directory rename in seconds.

**Database:**
```bash
mysql -u u123456789_clinic -p u123456789_clinicflow < ~/backups/clinicflow-<timestamp>.sql
```
Restore the backup taken in step 8.1, then redeploy the matching code tag. Because migrations are
additive, a code-only rollback is usually sufficient and the extra columns are simply unused.

**Verification after any rollback:** `GET /api/health` returns `{"status":"ok","db":"ok"}`, login
works, and one invoice's `paid_amount` still equals the sum of its payments.

## 10. Operational notes

* **Backups:** Hostinger's automatic backups plus a weekly `mysqldump` cron
  (hPanel → Advanced → Cron Jobs) writing to `~/backups`, retained 30 days.
* **Cron:** any scheduled job (follow-up reminders, low-stock digest) runs as
  `php ~/domains/clinic.drawlead.com/app/scripts/cron.php <job>` — no daemon required.
* **Logs:** application errors → `storage/logs/app-YYYY-MM-DD.log`; PHP errors are **not** displayed
  (`APP_DEBUG=false`), only logged.
* **PHP version:** set to 8.1+ in hPanel → Advanced → PHP Configuration; enable `pdo_mysql`,
  `mbstring`, `openssl`, `json`, `fileinfo`. Argon2id is used when the build supports it,
  otherwise bcrypt via `PASSWORD_DEFAULT`.
* **What is never on the server:** `node_modules/`, `src/`, `.git/`, test fixtures, demo seed data.
