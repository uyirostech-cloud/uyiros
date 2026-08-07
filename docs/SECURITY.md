# ClinicFlow — Security Architecture

MySQL gives us **no row-level security**. Therefore isolation is a property of the application,
and it must be implemented in exactly one place, not per endpoint.

## 1. Threat model (V1)

| Threat | Control |
|---|---|
| Clinic A reads/writes Clinic B data | Session-derived `organization_id` injected by the repository layer; 404 on cross-tenant lookup |
| User escalates by editing request body (`organization_id`, `role_id`, `is_platform_admin`) | Payload key stripping + explicit field allow-lists in validators |
| User accesses a branch they aren't assigned to | `Ctx->branchIds` intersection on every branch-scoped query |
| Role name spoofing | Authorization checks permission slugs from `role_permissions`, never role names |
| SQL injection | PDO prepared statements only; identifiers (sort columns, table names) resolved through allow-list maps |
| Session theft | Opaque 256-bit token, stored hashed, HttpOnly + Secure + SameSite cookie, absolute + idle expiry, revocable |
| CSRF | Per-session CSRF token required in `X-CSRF-Token` on every state-changing request + SameSite cookie |
| Brute force | Per-email and per-IP attempt counting, progressive lock-out |
| Password disclosure | `password_hash()` with `PASSWORD_ARGON2ID` when available, else `PASSWORD_DEFAULT` (bcrypt); never logged, never returned |
| Deactivated user / suspended clinic still working | Checked on **every** request, not just at login |
| XSS → token theft | Token is HttpOnly (unreachable from JS); React escapes by default; strict security headers |
| Mass data extraction | Every list endpoint paginated with a hard `per_page` ceiling |
| Silent tampering with medical/financial records | Append-only status history, prescription revisions, audit log |

## 2. Authentication

* **Login** `POST /api/auth/login` → verify email + `password_verify` → on success create a session.
* **Session record:** `token_hash = hash('sha256', token)`; the raw 32-byte token
  (`bin2hex(random_bytes(32))`) is only ever sent in the cookie.
* **Cookie:**
  ```
  clinicflow_session=<token>; Path=/; HttpOnly; SameSite=Lax; Secure (production); Max-Age=…
  ```
  `Secure` is switched on whenever `APP_ENV=production` or the request is HTTPS.
* **Expiry:** absolute lifetime 12 h, idle timeout 2 h (`last_seen_at`). Both enforced server-side.
* **Logout** revokes the row (`revoked_at`) — the token is dead immediately everywhere.
* **Rehash on login:** if `password_needs_rehash()`, the hash is upgraded transparently.
* **Password reset:** `POST /api/auth/forgot-password` always returns 200 (no account enumeration);
  stores `password_resets` row with hashed token, 60-minute expiry, single use; reset revokes all
  of that user's sessions.
* **Lock-out:** 5 failed attempts within 15 minutes → account locked 15 minutes
  (`users.locked_until`); the response is the same generic "invalid credentials" message.

## 3. Authorization pipeline

Every protected request passes through this, in order, with no way to skip a step:

```
Request
 → SecurityHeaders
 → CsrfGuard            (mutating verbs only)
 → Authenticate         → 401 if no/expired/revoked session
 → ResolveTenant        → 403 if user inactive, org suspended/cancelled
                        → builds Ctx { user, org, role, permissions[], branchIds[], isPlatformAdmin }
 → RequirePermission    → 403 unless Ctx has the route's permission slug
 → Controller
 → Repository (tenant-scoped SQL, always `AND organization_id = :ctx_org`)
 → Response
```

The route table declares the permission next to the route, so a new endpoint cannot be added
without stating what it requires:

```php
$r->get('/patients/{id}', [PatientController::class, 'show'], 'patient.view');
$r->post('/payments',     [PaymentController::class, 'store'], 'payment.create');
```

A route with permission `null` must be either public (`/auth/login`) or explicitly marked
`->authOnly()`. The kernel refuses to register a non-public route with no permission.

## 4. Tenant isolation rules (non-negotiable)

1. `organization_id` **always** comes from `Ctx->organizationId`, which comes from the `sessions`
   → `users` join. It is never read from body, query, header, or cookie.
2. Incoming payloads are filtered through `Request::only([...allowed fields])`; `organization_id`,
   `branch_id` (unless validated against `Ctx->branchIds`), `id`, `created_by`, `is_platform_admin`
   and `role_id` (outside user-management endpoints) are never mass-assignable.
3. `BaseRepository::find()/all()/update()/delete()` build SQL themselves and append
   `AND organization_id = :ctx_org`. There is no public method that runs raw SQL on a tenant table.
4. A cross-tenant `id` returns **404, not 403** — a 403 would confirm the record exists.
5. Branch-scoped endpoints validate `branch_id ∈ Ctx->branchIds` (or the user has `all_branches`),
   else 403.
6. Platform admins have `organization_id = NULL` and are **denied** all clinic endpoints; they use
   `/api/platform/*` exclusively. Cross-org data they see is aggregate/administrative.
7. Foreign keys supplied by the client (`patient_id`, `doctor_id`, `invoice_id`, …) are re-loaded
   through the tenant-scoped repository before use, so a foreign id fails as "not found".

Forbidden pattern:
```sql
SELECT * FROM patients WHERE id = ?
```
Required pattern:
```sql
SELECT * FROM patients WHERE id = ? AND organization_id = ?   -- org from session
```

## 5. Input validation

* **Frontend validation is UX only.** Every field is re-validated on the server.
* `Validator` rules: `required, string, int, decimal, email, phone, date, datetime, in:a,b,c,
  min, max, maxlen, minlen, exists:table, unique:table,column, boolean, array, nullable`.
* `exists:` and `unique:` are themselves tenant-scoped.
* Failure → `422` with `{ error: { code: "VALIDATION_FAILED", fields: { field: "message" } } }`.
* Money is parsed to a scaled integer (paise/cents) via `Money::parse()` then stored as DECIMAL —
  no float arithmetic anywhere in PHP or JS. Totals are computed server-side and the client's
  submitted totals are ignored.

## 6. Transport & headers

Set on every response by `SecurityHeaders`:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=()
Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline';
                         script-src 'self'; connect-src 'self'; frame-ancestors 'none'
Strict-Transport-Security: max-age=31536000; includeSubDomains   (HTTPS only)
```

CORS is **disabled in production** (same-origin). In development the API allows exactly
`http://localhost:5173` with `Access-Control-Allow-Credentials: true`.

## 7. CSRF

* On login the server generates a per-session CSRF token and returns it in the JSON body and in a
  readable (non-HttpOnly) `clinicflow_csrf` cookie.
* The SPA sends it back as `X-CSRF-Token` on POST/PUT/PATCH/DELETE.
* `CsrfGuard` compares with `hash_equals` against the value stored on the session row → mismatch
  = `419`. The frontend treats 419 by refreshing `/api/auth/me` once, then forcing re-login.
* `SameSite=Lax` on the session cookie is defence in depth, not the primary control.

## 8. Uploads

* Stored **outside** the web root (`storage/uploads/<organization_id>/…`), served only through
  `GET /api/documents/{id}/download` after the normal auth + tenant + permission pipeline.
* Extension and MIME allow-list (`pdf,jpg,jpeg,png,webp`), 10 MB cap, randomized stored filename,
  original name kept in the DB only.
* `.htaccess` in `storage/` denies direct access as a second layer.

## 9. Audit logging

`AuditService::log($ctx, $action, $entityType, $entityId, $old, $new, $meta)` is called from the
service layer inside the same transaction for: patient create/update, appointment
create/cancel/reschedule, consultation finalize/update, prescription finalize/revise,
invoice create/cancel, payment create/void, refund, expense approve/reject, user create/update/
deactivate, role & permission change, settings change, login success/failure, logout.

Old/new values are JSON, with `password_hash`, tokens and secrets redacted before writing.

## 10. Secrets

* `server/.env` is git-ignored; `.env.example` carries placeholder keys only.
* Production credentials live only on the Hostinger box (and in GitHub Actions secrets for deploy).
* `APP_KEY` / `SESSION_SECRET` generated per environment with `php scripts/genkey.php`.
* No credential is ever logged or returned by the API.

## 11. Security test matrix (automated — `php server/tests/run.php`)

| # | Test | Expectation |
|---|---|---|
| 1 | Org A user requests Org B patient by id | 404 |
| 2 | Org A user lists patients | only Org A rows |
| 3 | Org A user posts `organization_id: B` on create | record created under A |
| 4 | Org A user updates Org B invoice | 404, row unchanged |
| 5 | Receptionist calls `/api/finance/summary` | 403 |
| 6 | Sales user calls `/api/consultations/{id}` | 403 |
| 7 | Doctor reads own-org patient | 200 |
| 8 | User requests branch outside `user_branches` | 403 |
| 9 | Mutating request without `X-CSRF-Token` | 419 |
| 10 | Request with revoked session cookie | 401 |
| 11 | Request after user deactivated | 403 |
| 12 | Request while organization suspended | 403 |
| 13 | `q=' OR 1=1 --` in patient search | 0 rows, no error |
| 14 | `sort=id; DROP TABLE patients` | 400, table intact |
| 15 | 6 bad logins | account locked, generic error |
| 16 | Platform admin hits `/api/patients` | 403 |
