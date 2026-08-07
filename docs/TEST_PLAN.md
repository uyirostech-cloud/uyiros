# ClinicFlow — Test Plan

## Tooling

* **Backend:** a dependency-free PHP test runner, `php server/tests/run.php`. It boots the app
  kernel in-process, runs migrations + seeds against `DB_NAME` from `server/.env.testing`
  (default `clinicflow_test`), and dispatches real HTTP-shaped requests through the full middleware
  pipeline — so authentication, CSRF, tenancy and permissions are genuinely exercised, not mocked.
* **Frontend:** `npm run typecheck` (tsc) and `npm run build` gate the SPA; component tests are
  added per feature phase.
* No Composer/PHPUnit dependency, so the same suite runs on any box, including Hostinger.

```bash
php server/tests/run.php              # everything
php server/tests/run.php --filter=tenant
```

## Suites

### A. Unit
| ID | Test |
|---|---|
| U1 | `Money::parse/format/add/mul` — no float drift, 2-dp rounding half-up |
| U2 | Invoice line math (qty × price − discount + tax) |
| U3 | Phone normalization (`+91 98765 43210`, `098765 43210`, `9876543210` → same key) |
| U4 | BMI computation |
| U5 | Validator rules incl. `in`, `decimal`, `date`, `nullable` |
| U6 | Number formatting `PAT-000001` padding & prefix |
| U7 | Appointment status transition table |

### B. Security / tenancy (blocking — Phase 1 gate)
| ID | Test | Expect |
|---|---|---|
| S1 | Org A user GETs Org B patient | 404 |
| S2 | Org A user lists patients | only A's rows |
| S3 | Create with `organization_id` of B in body | stored under A |
| S4 | Org A user PUTs Org B record | 404, unchanged |
| S5 | Receptionist GET `/api/finance/summary` | 403 |
| S6 | Sales user GET `/api/consultations` | 403 |
| S7 | Doctor GET own-org patient | 200 |
| S8 | User requests unassigned branch | 403 |
| S9 | POST without `X-CSRF-Token` | 419 |
| S10 | Revoked/expired session cookie | 401 |
| S11 | Deactivated user | 403 |
| S12 | Suspended organization | 403 |
| S13 | SQL-injection payload in `q` and `sort` | no rows / 400, schema intact |
| S14 | 6 failed logins | locked, generic message |
| S15 | Platform admin on clinic endpoint | 403 |
| S16 | Unauthenticated request to protected route | 401 |

### C. Functional (per phase)
| ID | Test |
|---|---|
| F1 | Duplicate patient phone detection returns 409 + existing record |
| F2 | Doctor double-booking blocked (overlapping slot → 409) |
| F3 | Non-overlapping back-to-back slots allowed |
| F4 | Check-in creates queue entry with next token, per branch per day |
| F5 | Consultation finalize completes appointment + queue entry |
| F6 | Prescription finalize writes revision v1; edit writes v2, v1 preserved |
| F7 | Invoice totals correct incl. discount + tax |
| F8 | Partial payment → status `Partially Paid`, balance correct |
| F9 | Split payment (Cash 300 + UPI 200 on 500) → `Paid`, balance 0, finance shows 300/200/500 |
| F10 | Refund reduces paid, sets status, finance nets out |
| F11 | Overpayment rejected 422 |
| F12 | Daily closing expected vs actual variance correct |
| F13 | Lead → convert → patient linked (no duplicate when phone matches) |
| F14 | Inventory: stock == SUM(transactions); low-stock flag |
| F15 | Dashboard card totals == corresponding list endpoint totals |
| F16 | Numbering is gapless and unique per organization under concurrent inserts |
| F17 | Report totals == finance totals for the same range |

### D. End-to-end V1 acceptance
`php server/tests/run.php --filter=acceptance` walks the full chain in one run:

```
Lead → Convert → Patient → Appointment → Check-in → Queue → Consultation → Vitals
 → Diagnosis → Prescription → Invoice → Split payment → Finance → Dashboard → Reports
```
then re-runs every read as an Org B user and asserts 404/empty on all of it.

### E. Manual / UI checklist (per phase preview)
* login, wrong password, locked account, logout
* sidebar reflects permissions (receptionist sees no Finance section)
* protected route direct-URL access when unauthorized → redirected/403 page
* table search + filter + pagination
* form validation messages from the server render on the right fields
* loading skeletons, empty states, error states, toasts
* responsive at 375 px, 768 px, 1440 px

## Definition of done per phase

1. `php server/database/migrate.php --fresh && php server/database/seed.php --demo` succeeds.
2. `php server/tests/run.php` — all green, security suite included.
3. `npm run typecheck && npm run build` — clean.
4. App boots; the phase's screens load with seeded data and no console errors.
5. Docs updated if the schema or API changed.
