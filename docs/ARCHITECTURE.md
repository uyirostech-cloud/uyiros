# ClinicFlow — Architecture

## 1. Why this stack

The production target is **Hostinger shared hosting**. That constrains everything:

| Constraint on shared hosting | Consequence |
|---|---|
| No guaranteed persistent Node.js process | Backend **cannot** be Express/Nest/Next SSR. Use PHP, which shared hosting runs per-request. |
| No root, no systemd, no Docker | No sidecars, no queue workers, no Redis assumption. Cron is available via hPanel only. |
| Apache + `.htaccess` | Routing via `mod_rewrite` front controller; SPA fallback via `.htaccess`. |
| PHP 8.x with PDO MySQL available | PDO + prepared statements, `password_hash`, `random_bytes`. |
| MySQL 8 / MariaDB, no RLS | Tenant isolation must be **application-enforced**, centrally. |
| Composer may not be runnable on the box | Zero runtime Composer dependencies; own PSR-4 autoloader. |

**Verified suitability:** the frontend compiles to static assets (Vite → `dist/`), which Apache
serves directly; the API is plain PHP files behind one front controller. Both live on the same
domain, so cookies are first-party and no CORS/preflight is needed in production.

**Result:**

```
React 18 + TypeScript + Vite + Tailwind   →  static build, served by Apache
PHP 8 REST API (no framework, PSR-4)      →  /api/*  front controller
MySQL 8 / MariaDB (Hostinger)             →  PDO, prepared statements
DB-backed sessions + HttpOnly cookie      →  no Supabase, no JWT-in-localStorage
```

## 2. Deployment pipeline

```
VS Code → Git → GitHub → (GitHub Actions build + SFTP/SSH deploy) → Hostinger shared hosting
       → clinic.drawlead.com → Hostinger MySQL
```

See `docs/HOSTINGER_DEPLOYMENT.md`.

## 3. Runtime topology

```
                    https://clinic.drawlead.com
                               │
                    ┌──────────┴───────────┐
                    │   Apache (Hostinger) │
                    └──────────┬───────────┘
             /api/*  ──────────┤            /*  ────────► index.html + /assets/*
                               │                          (React SPA, client routing)
                    server/public/index.php
                               │
                        Router → Kernel
                               │
   ┌───────────────────────────┴──────────────────────────────┐
   │ Middleware pipeline (centralized, non-negotiable)         │
   │  1. SecurityHeaders  2. JsonBody  3. Session/Auth         │
   │  4. CsrfGuard        5. TenantResolver                    │
   │  6. Permission(...)  7. BranchScope                       │
   └───────────────────────────┬──────────────────────────────┘
                               │
                    Controller → Service → Repository
                               │
                    TenantQuery (auto org scoping)
                               │
                          PDO / MySQL
```

## 4. Request lifecycle

```
Request
 → Router matches method+path, collects route params
 → Middleware chain (see above); any layer may abort with 401/403/419/422
 → Controller (thin: parse + delegate + respond)
 → Validator (server-side, always — browser validation is UX only)
 → Service (business rules, transactions, numbering, audit)
 → Repository (SQL; every tenant table filtered by organization_id from the session)
 → JSON response { data, meta } or { error: { code, message, fields } }
```

`Ctx` (request context) is built once by the auth + tenant middleware and carries:
`user`, `organization`, `role`, `permissions[]`, `branchIds[]`, `isPlatformAdmin`.
Controllers receive `Ctx`; they cannot construct one themselves.

## 5. Backend folder structure

```
server/
  public/
    index.php              front controller (the only web-reachable PHP entry)
    .htaccess              rewrite everything to index.php
  bootstrap.php            autoloader + env + error handling
  config/
    app.php  database.php  cors.php  session.php
  routes/
    api.php                the whole route table, with per-route permissions
  app/
    Core/
      Autoloader.php Env.php Config.php Database.php Router.php Request.php
      Response.php Ctx.php Kernel.php Migrator.php Seeder.php Validator.php
      HttpException.php Str.php Money.php
    Http/
      Middleware/  SecurityHeaders CsrfGuard Authenticate ResolveTenant
                   RequirePermission RequirePlatformAdmin RateLimit
      Controllers/ AuthController DashboardController PatientController ...
    Domain/
      Repositories/  BaseRepository PatientRepository ...
      Services/      AuthService NumberingService AuditService ...
    Support/
      Audit.php Permissions.php Roles.php
  database/
    migrate.php            CLI: php server/database/migrate.php [--fresh]
    seed.php               CLI: php server/database/seed.php [--demo]
    migrations/            0001_..._create_core_tables.php, ...
    seeders/               PermissionSeeder, DemoDataSeeder
  tests/
    run.php                CLI test runner (no PHPUnit dependency)
    Feature/  Unit/
```

**Rule:** no API logic in `public/index.php`. It only boots the kernel.

## 6. Frontend structure

```
src/
  app/          App.tsx, router.tsx, providers.tsx, queryClient.ts
  components/   ui/ (Button, Input, Select, Modal, Table, Badge, Toast, Skeleton,
                     EmptyState, ErrorState, Pagination, ConfirmDialog, Card, Tabs)
                common/ (PageHeader, DataTable, FilterBar, StatCard, Chart)
  layouts/      AppLayout, AuthLayout, Sidebar, Topbar
  features/     auth dashboard crm patients doctors appointments queue consultations
                prescriptions billing finance expenses operations inventory reports
                administration platform
  services/     http.ts (fetch wrapper + CSRF + 401 handling), api/*.ts per resource
  hooks/        useAuth, usePermission, useBranch, useDebounce, useToast
  types/        api.ts, models.ts
  utils/        format.ts (money/date), validation.ts, constants.ts
  config/       env.ts, navigation.ts (sidebar definition + required permissions)
```

State: React Query for server state, a small `AuthContext` for session/permissions.
Routing: React Router v6 with `<Protected permission="patient.view">` guards
(**UX only** — the API re-checks every time).

## 7. API conventions

* Base: `/api`. All responses JSON. UTF-8.
* Success: `{ "data": ..., "meta": { ...pagination } }`
* Error: `{ "error": { "code": "FORBIDDEN", "message": "...", "fields": {...} } }`
* Status codes: 200/201, 400 bad request, 401 unauthenticated, 403 unauthorized,
  404 not found (also used to mask cross-tenant reads), 409 conflict (e.g. double booking),
  419 CSRF/session expired, 422 validation, 429 rate limited, 500 server error.
* Lists: `?page=1&per_page=25&q=&sort=&dir=&branch_id=&from=&to=` — always paginated.
* Mutating verbs (POST/PUT/PATCH/DELETE) require the `X-CSRF-Token` header.

Endpoint groups: `/api/auth`, `/dashboard`, `/leads`, `/patients`, `/doctors`, `/appointments`,
`/queue`, `/consultations`, `/prescriptions`, `/services`, `/invoices`, `/payments`, `/expenses`,
`/finance`, `/inventory`, `/vendors`, `/tasks`, `/reports`, `/users`, `/branches`, `/roles`,
`/settings`, `/audit-logs`, `/platform/*`.

## 8. Tenant isolation mechanism

1. `Authenticate` resolves the session token cookie → `sessions` row → `users` row.
2. `ResolveTenant` loads the user's organization, verifies `organizations.status = 'active'`
   and `users.is_active = 1`, loads role + permissions + allowed branch ids into `Ctx`.
3. `BaseRepository` requires a `Ctx` and **injects `organization_id = :ctx_org` into every
   query it builds**. Repositories expose no way to run an unscoped query on a tenant table.
4. Any incoming `organization_id` field is stripped from request payloads before validation.
5. Branch-scoped reads intersect the requested branch with `Ctx->branchIds`.

See `docs/SECURITY.md` for the full rules and the test matrix.

## 9. Environments

| | Local | Production |
|---|---|---|
| Frontend | `npm run dev` (Vite, :5173), proxies `/api` → :8000 | static `dist/` served by Apache |
| Backend | `php -S localhost:8000 -t server/public` | Apache + PHP-FPM |
| DB | local MySQL/MariaDB (or `docker compose up db`) | Hostinger MySQL |
| Cookies | `SameSite=Lax`, not Secure (http) | `Secure; HttpOnly; SameSite=Lax` |
| Env file | `server/.env` from `.env.example` | `server/.env` created on the server, never in Git |

## 10. Key architectural decisions

| Decision | Rationale |
|---|---|
| No PHP framework | Shared hosting + zero-dependency deploy; a 400-line router/kernel is enough and auditable. |
| DB-backed sessions, not JWT | Instant revocation (deactivate user / suspend clinic), no token in JS-readable storage. |
| Opaque session token, hashed at rest | DB leak does not yield usable sessions. |
| SPA + JSON API (not PHP-rendered pages) | Matches the requested React/Vite stack and keeps the API reusable. |
| Numbering in DB with `FOR UPDATE` | Concurrency-safe, gapless per-organization business numbers. |
| Stock derived from `inventory_transactions` | Balance is always provable; no drifting cached quantity. |
| Reports computed in SQL, paginated/streamed | Shared hosting has small memory limits; never ship whole tables to the browser. |
