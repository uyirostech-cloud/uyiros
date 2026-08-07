# ClinicFlow — Development Plan

Phases are gated: a phase is not "done" until its migrations run clean, its tests pass, and the
screens load with seed data.

| Phase | Scope | Gate |
|---|---|---|
| **0 — Architecture** | 9 docs: requirements, architecture, database, security, permissions, workflows, deployment, test plan, this plan | Schema + API + security model written down before code |
| **1 — Foundation** | Vite/React/TS/Tailwind shell, PHP kernel + router + middleware, MySQL connection, migrations, seeders, auth (login/logout/me/forgot/reset), organizations, branches, roles, permissions, users, tenant middleware, sidebar, route protection, dashboard shell | **Tenant isolation suite green** before Phase 2 |
| **2 — Patient flow** | patients (+tabs), doctors, doctor schedules, appointments (+overlap, status history, views), check-in, queue | `Patient → Appointment → Check-in → Queue` E2E |
| **3 — Clinical** | vitals, consultations, diagnoses, prescriptions (+revisions, print), medical history | Full doctor workflow E2E |
| **4 — Billing** | services, invoices, invoice items, payments, partial/split, receipts, refunds | Reconciliation tests green |
| **5 — CRM** | leads, follow-ups, assignment, duplicate detection, conversion | `Lead → Patient → Appointment` E2E |
| **6 — Finance** | expenses (+approval), financial accounts, finance dashboard, daily closing, outstanding, collection reporting | Closing variance + collection reconcile |
| **7 — Operations** | tasks, inventory items + transactions, low-stock, vendors | Stock == Σ transactions |
| **8 — Reports** | 12 reports + CSV/Excel/PDF export, server-side | Report totals == finance totals |
| **9 — SaaS admin** | platform admin area: organizations, plans, subscriptions, activation, usage | Platform admin cannot read clinic data |
| **10 — Hardening** | full security sweep, injection/CSRF/session tests, reconciliation, responsive, performance, indexes | Whole suite + manual checklist |

## Phase 1 breakdown (current)

1. Repo scaffolding, `.gitignore`, `.env.example`, npm + Vite + Tailwind config
2. PHP core: autoloader, env, config, PDO wrapper, router, request/response, kernel, exceptions
3. Migrations for the full V1 schema (written once, so later phases only add data + endpoints)
4. Permission + role seeder, demo data seeder
5. Middleware: security headers, CSRF, authenticate, resolve tenant, require permission
6. `BaseRepository` with mandatory tenant scoping + `NumberingService` + `AuditService`
7. Auth endpoints + session cookies
8. Org/branch/role/permission/user endpoints
9. Dashboard endpoint (real queries)
10. Frontend: http client, auth context, login page, app layout + sidebar (permission-driven),
    protected routes, dashboard, users/branches/roles admin screens, UI kit
11. Test runner + unit + security suites
12. Boot both servers, seed, verify, preview

## Working agreements

* No endpoint ships without a declared permission.
* No SQL string concatenation of user input — ever.
* Money never touches a float.
* Every list endpoint is paginated.
* Every mutation that changes clinical or financial state writes an audit row.
* Frontend validation never substitutes for backend validation.
* Nothing is reported as working that has not been run.

## Local commands

```bash
# database (local MariaDB/MySQL)
mysql -uroot -e "CREATE DATABASE clinicflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
# or: docker compose up -d db

cp .env.example server/.env      # then edit credentials
php server/database/migrate.php  # create schema
php server/database/seed.php --demo

php -S localhost:8000 -t server/public   # API   → http://localhost:8000/api
npm install && npm run dev               # SPA   → http://localhost:5173
php server/tests/run.php                 # tests
```
