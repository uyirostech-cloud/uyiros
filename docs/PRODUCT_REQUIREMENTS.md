# ClinicFlow SaaS — Product Requirements (V1)

## 1. Summary

ClinicFlow is a **multi-tenant SaaS** for **general doctor clinics**. One deployment serves many
clinics (organizations), each with one or more branches. The product runs the whole daily clinic
operation from a single connected record set — no disconnected modules.

**Production target:** `https://uyiros.tech` on a **Hostinger KVM VPS** (nginx + PHP-FPM +
MySQL/MariaDB), deployed via a GitHub Actions CI/CD pipeline.

## 2. The core connected workflow

```
Lead → Patient → Appointment → Check-in → Queue → Consultation
     → Prescription → Invoice → Payment → Finance → Reports
```

Every step writes to a record that the next step reads. A lead that converts creates (or matches)
a patient; the appointment references that patient; check-in creates a queue entry that references
the appointment; the consultation references the appointment; the prescription references the
consultation; the invoice references the appointment and doctor; payments reference the invoice;
finance and reports aggregate the same rows. Dashboard totals and drill-down lists must reconcile
because they read the same tables.

## 3. Tenancy model

```
Platform
  └─ Organization (clinic)     e.g. "Demo General Clinic"
       └─ Branch               e.g. "Anna Nagar", "T Nagar"
            └─ Users
                 └─ Clinic data
```

* Every tenant-owned row carries `organization_id`; most also carry `branch_id`.
* **Clinic A must never read or write Clinic B data.** Enforced in the backend only
  (MySQL has no RLS): `organization_id` is taken from the authenticated session, never from
  the request body or query string.
* Users may be granted access to one branch, several branches, or all branches of their org.

## 4. Application levels

### Level 1 — Platform Admin (separate from clinic users)
Manages: organizations, plans, subscriptions, organization status (active / suspended / trial),
usage overview, cross-org user overview, system settings. A platform admin has
`is_platform_admin = 1` and **no** `organization_id`.

### Level 2 — Clinic users
Roles shipped by default per organization:

| Role | Purpose |
|---|---|
| Clinic Admin | Full clinic access incl. settings, users, finance |
| Doctor | Clinical records, own queue, prescriptions |
| Receptionist | Patients, appointments, check-in/queue, invoices, payments |
| Sales / CRM | Leads, follow-ups, conversion |
| Operations | Tasks, inventory, vendors |
| Finance / Accountant | Invoices, payments, expenses, finance, daily closing, reports |

Roles are **data**, not code. Permissions are granted through `role_permissions`; the UI never
decides access — the backend does.

## 5. V1 module scope

| # | Module | V1 content |
|---|---|---|
| 1 | Authentication | login, logout, session, forgot/reset password, inactive user + inactive clinic handling, CSRF |
| 2 | Dashboard | 9 KPI cards + 5 charts, all from live SQL |
| 3 | CRM / Sales | leads, sources, statuses, follow-ups, assignment, duplicate phone detection, conversion |
| 4 | Patients | UHID, demographics, medical flags, tabs (overview/appointments/consultations/prescriptions/vitals/invoices/payments/documents/timeline) |
| 5 | Doctors | profile, fees, duration, branch assignment, weekly schedule |
| 6 | Appointments | scheduling, 9 statuses, status history, overlap prevention, today/list/calendar/doctor/branch views |
| 7 | Reception / Queue | check-in, token generation, waiting list, waiting time |
| 8 | Consultation | patient summary, history, vitals, complaint→diagnosis→advice, follow-up |
| 9 | Prescription | multi-row medicines, printable, finalize + audit history |
| 10 | Services | catalogue with price/tax/category |
| 11 | Billing | invoices, line items, discount, tax, DECIMAL money, 6 statuses |
| 12 | Payments | 6 methods, partial/split/multiple, refunds, reconciliation |
| 13 | Finance | collection, revenue, method breakdown, receivables, refunds, expenses |
| 14 | Expenses | categories, approval workflow, attachments |
| 15 | Basic accounts | financial accounts, daily closing with expected vs actual cash + variance |
| 16 | Operations | tasks |
| 17 | Inventory | items, transaction-derived stock, low-stock alerts |
| 18 | Vendors | vendor master |
| 19 | Reports | 12 reports, server-side, CSV/Excel/PDF export |
| 20 | User management | create/edit/deactivate, role + branch assignment |
| 21 | Branch management | branch master |
| 22 | Settings | 16 settings areas incl. numbering formats and branding |
| 23 | Audit logs | critical actions with old/new values |

## 6. Non-functional requirements

* **Hosting:** runs on a Hostinger KVM VPS — nginx + PHP-FPM + MySQL/MariaDB, deployed by CI/CD.
  The backend has no shared-hosting-specific constraints (root access is available), but stays
  framework-free and dependency-light regardless, since that was never the reason for the choice.
  No long-running Node process, no VPS, no Docker in production.
* **Money:** `DECIMAL(14,2)` everywhere. Never float.
* **Numbering:** business numbers (`PAT-000001`, `INV-000001`, …) generated server-side under a
  row lock, per organization. Never in the browser.
* **Security:** hashed passwords (`password_hash` / Argon2id when available), HttpOnly + Secure +
  SameSite cookies, CSRF tokens on mutating requests, prepared statements only, centralized
  authorization.
* **Auditability:** medical and financial records are never silently rewritten — status history +
  audit log.
* **Responsiveness:** desktop, tablet, mobile.

## 7. Explicit non-goals for V1

* No enterprise double-entry ledger / trial balance.
* No insurance claims, lab/radiology integration, or pharmacy dispensing stock at batch level.
* No SMS/WhatsApp gateway integration (notification records are stored; delivery is stubbed).
* No realtime websockets (queue refreshes by polling).

## 8. V1 acceptance flow

Create Lead → Convert → Patient → Appointment → Check-in → Queue → Consultation → Vitals →
Diagnosis → Prescription → Invoice → Split Payment → Finance → Dashboard → Reports, then prove a
second organization can see **none** of it.
