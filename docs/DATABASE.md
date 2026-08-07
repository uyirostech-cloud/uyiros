# ClinicFlow — Database Design

**Engine:** MySQL 8 / MariaDB 10.6+ (InnoDB, `utf8mb4_unicode_ci`).
All SQL is written to be valid on both — no MySQL-8-only syntax (no CTE-only queries, no
`JSON_TABLE`, no window functions in V1 paths, no `CHECK` constraint reliance).

## Conventions

| Rule | Detail |
|---|---|
| Primary key | `id BIGINT UNSIGNED AUTO_INCREMENT` |
| Public id | `public_id CHAR(26)` (ULID) on records exposed in URLs where guessability matters |
| Tenant key | `organization_id BIGINT UNSIGNED NOT NULL` on **every** tenant table, indexed first in composite indexes |
| Branch key | `branch_id BIGINT UNSIGNED` where the record belongs to a location |
| Money | `DECIMAL(14,2)`; quantities `DECIMAL(12,3)`; tax/discount percent `DECIMAL(6,3)` |
| Timestamps | `created_at DATETIME NOT NULL`, `updated_at DATETIME NULL` (set by app, UTC) |
| Attribution | `created_by`, `updated_by` → `users.id` |
| Soft delete | `deleted_at DATETIME NULL` on master data only; transactional/medical rows are cancelled, never deleted |
| Enums | `VARCHAR(32)` + application constant, not MySQL `ENUM` (migration-friendly) |
| FKs | Declared with `ON DELETE RESTRICT` for tenant data, `ON DELETE CASCADE` only for owned child rows (e.g. `invoice_items`) |
| Unique | Always scoped: `UNIQUE (organization_id, <business key>)` |

Because MySQL has no RLS, **`organization_id` is a hard requirement on every tenant table** and
the repository layer injects it. The column is not optional and has no default.

## Table catalogue

### Platform
| Table | Key columns |
|---|---|
| `plans` | `code` UQ, `name`, `price_monthly` DEC, `max_branches`, `max_users`, `features` JSON-text, `is_active` |
| `organizations` | `code` UQ, `name`, `slug` UQ, `status` (active/trial/suspended/cancelled), `plan_id`, `trial_ends_at`, contact + address, `timezone`, `currency`, `logo_path` |
| `subscriptions` | `organization_id`, `plan_id`, `status`, `starts_at`, `ends_at`, `amount` DEC, `billing_cycle` |
| `organization_settings` | `organization_id`, `key`, `value` TEXT — UQ `(organization_id, key)` |
| `branches` | `organization_id`, `name`, `code` UQ`(org,code)`, phone/email/address/city/state/pin, `opening_time`, `closing_time`, `working_days`, `is_active` |

### Identity & access
| Table | Key columns |
|---|---|
| `users` | `organization_id` NULL for platform admins, `name`, `email` UQ, `phone`, `password_hash`, `role_id`, `is_platform_admin`, `is_active`, `must_change_password`, `last_login_at`, `failed_attempts`, `locked_until` |
| `roles` | `organization_id` NULL = system template, `name`, `slug`, `is_system`, UQ `(organization_id, slug)` |
| `permissions` | `slug` UQ (`patient.view`…), `group`, `label` |
| `role_permissions` | `role_id`, `permission_id`, UQ pair |
| `user_branches` | `user_id`, `branch_id`, UQ pair; empty set + `all_branches` flag on user = whole org |
| `sessions` | `id`, `user_id`, `token_hash` UQ, `csrf_token`, `ip`, `user_agent`, `expires_at`, `revoked_at`, `last_seen_at` |
| `password_resets` | `user_id`, `token_hash` UQ, `expires_at`, `used_at` |
| `login_attempts` | `email`, `ip`, `succeeded`, `created_at` (rate limiting) |

### Clinical staff
| Table | Key columns |
|---|---|
| `doctors` | `organization_id`, `user_id` NULL, `name`, `qualification`, `specialization`, `registration_number`, phone/email, `consultation_fee` DEC, `followup_fee` DEC, `default_duration_minutes`, `is_active` |
| `doctor_branches` | `doctor_id`, `branch_id` |
| `doctor_schedules` | `doctor_id`, `branch_id`, `day_of_week` 0-6, `start_time`, `end_time`, `slot_minutes`, `is_active` |

### CRM
| Table | Key columns |
|---|---|
| `leads` | `organization_id`, `branch_id`, `lead_number` UQ`(org,number)`, `name`, `phone`, `phone_normalized` (idx), `alt_phone`, `email`, `source`, `campaign`, `interested_service_id`, `assigned_user_id`, `priority`, `status`, `next_followup_at`, `notes`, `converted_patient_id`, `converted_at` |
| `lead_followups` | `lead_id`, `organization_id`, `user_id`, `followup_at`, `outcome`, `notes`, `next_followup_at` |

### Patients & visits
| Table | Key columns |
|---|---|
| `patients` | `organization_id`, `branch_id`, `patient_number` (UHID) UQ`(org,number)`, `full_name`, `phone`, `phone_normalized` idx`(org,phone_normalized)`, `alt_phone`, `email`, `dob`, `age_years`, `gender`, `blood_group`, address/city/state/pin, `emergency_contact_name/phone`, `occupation`, `source`, `allergies` TEXT, `medical_notes` TEXT, `registered_at`, `lead_id` |
| `appointments` | `organization_id`, `branch_id`, `appointment_number` UQ, `patient_id`, `doctor_id`, `scheduled_at` DATETIME, `duration_minutes`, `ends_at` (generated at write), `reason`, `type`, `source`, `notes`, `status`, `checked_in_at`, `started_at`, `completed_at`, `cancelled_at`, `cancel_reason`, `rescheduled_from_id`. Idx `(organization_id, doctor_id, scheduled_at)` for overlap checks |
| `appointment_status_history` | `appointment_id`, `from_status`, `to_status`, `user_id`, `note`, `created_at` |
| `queue_entries` | `organization_id`, `branch_id`, `appointment_id` UQ, `patient_id`, `doctor_id`, `token_number`, `queue_date`, `status` (waiting/in_consultation/completed/skipped/left), `arrived_at`, `called_at`, `completed_at`. UQ `(organization_id, branch_id, queue_date, token_number)` |

### Clinical records
| Table | Key columns |
|---|---|
| `vitals` | `organization_id`, `patient_id`, `appointment_id`, `consultation_id` NULL, `height_cm` DEC, `weight_kg` DEC, `bmi` DEC, `temperature_c` DEC, `bp_systolic`, `bp_diastolic`, `pulse`, `spo2`, `blood_sugar` DEC, `recorded_by`, `recorded_at` |
| `consultations` | `organization_id`, `branch_id`, `appointment_id` UQ, `patient_id`, `doctor_id`, `chief_complaint`, `symptoms`, `history`, `examination`, `clinical_notes`, `advice`, `followup_required`, `followup_date`, `status` (draft/finalized), `finalized_at` |
| `diagnoses` | `consultation_id`, `organization_id`, `code` NULL, `name`, `type` (provisional/final), `notes` |
| `prescriptions` | `organization_id`, `consultation_id`, `patient_id`, `doctor_id`, `prescription_number` UQ, `status` (draft/finalized), `advice`, `followup_date`, `finalized_at`, `version` |
| `prescription_items` | `prescription_id`, `medicine_name`, `generic_name`, `strength`, `dosage`, `frequency`, `duration`, `timing`, `route`, `instructions`, `sort_order` |
| `prescription_revisions` | `prescription_id`, `version`, `snapshot` LONGTEXT (JSON), `changed_by`, `reason`, `created_at` |
| `documents` | `organization_id`, `patient_id` NULL, `entity_type`, `entity_id`, `file_name`, `stored_path`, `mime`, `size_bytes`, `uploaded_by` |

### Catalogue & billing
| Table | Key columns |
|---|---|
| `services` | `organization_id`, `name`, `category`, `code`, `price` DEC, `tax_percent` DEC, `is_taxable`, `is_active` |
| `invoices` | `organization_id`, `branch_id`, `invoice_number` UQ, `patient_id`, `appointment_id` NULL, `doctor_id` NULL, `invoice_date`, `subtotal`, `discount_amount`, `tax_amount`, `total`, `paid_amount`, `balance_amount`, `status`, `notes`, `cancelled_at`, `cancel_reason` |
| `invoice_items` | `invoice_id`, `organization_id`, `service_id` NULL, `description`, `quantity` DEC(12,3), `unit_price` DEC, `discount_amount` DEC, `tax_percent` DEC, `tax_amount` DEC, `line_total` DEC |
| `payments` | `organization_id`, `branch_id`, `payment_number` UQ, `invoice_id`, `patient_id`, `amount` DEC, `method`, `reference`, `financial_account_id`, `paid_at`, `received_by`, `notes`, `status` (completed/void) |
| `refunds` | `organization_id`, `branch_id`, `refund_number` UQ, `invoice_id`, `payment_id` NULL, `amount` DEC, `method`, `reason`, `refunded_at`, `created_by` |

`invoices.paid_amount` / `balance_amount` are **derived and rewritten inside the payment
transaction** from `SUM(payments.amount WHERE status='completed') - SUM(refunds.amount)`.
They are a cache of a query that must always reproduce them — the reconciliation test asserts this.

### Finance
| Table | Key columns |
|---|---|
| `financial_accounts` | `organization_id`, `branch_id` NULL, `name`, `type` (cash/bank/upi/card_clearing/other), `opening_balance` DEC, `is_active` |
| `expense_categories` | `organization_id`, `name`, `is_active` |
| `expenses` | `organization_id`, `branch_id`, `expense_number` UQ, `category_id`, `vendor_id` NULL, `amount` DEC, `payment_method`, `financial_account_id` NULL, `expense_date`, `description`, `attachment_path`, `status` (draft/pending/approved/rejected/paid), `created_by`, `approved_by`, `approved_at` |
| `daily_closings` | `organization_id`, `branch_id`, `closing_date`, `expected_cash` DEC, `actual_cash` DEC, `variance` DEC, `expected_upi/card/bank` DEC, `notes`, `closed_by`, `closed_at`. UQ `(organization_id, branch_id, closing_date)` |

### Operations
| Table | Key columns |
|---|---|
| `vendors` | `organization_id`, `name`, `contact_person`, `phone`, `email`, `gst_number`, `address`, `category`, `notes`, `is_active` |
| `inventory_categories` | `organization_id`, `name`, `is_active` |
| `inventory_items` | `organization_id`, `branch_id`, `name`, `category_id`, `sku` UQ`(org,sku)`, `unit`, `minimum_stock` DEC(12,3), `purchase_cost` DEC, `vendor_id`, `is_active` |
| `inventory_transactions` | `organization_id`, `branch_id`, `item_id`, `type` (in/out/adjustment), `quantity` DEC(12,3) signed by type, `unit_cost` DEC, `reference`, `notes`, `transacted_at`, `created_by` |
| `tasks` | `organization_id`, `branch_id`, `title`, `description`, `assigned_user_id`, `priority`, `due_date`, `status`, `completed_at`, `created_by` |

**Stock rule:** `current_stock(item) = SUM(CASE type WHEN 'in' THEN quantity WHEN 'out' THEN -quantity ELSE quantity END)`.
No cached stock column is authoritative; nothing changes stock outside this table.

### System
| Table | Key columns |
|---|---|
| `number_sequences` | `organization_id`, `key` (patient/lead/appointment/invoice/payment/expense/prescription/refund), `prefix`, `padding`, `next_value`. UQ `(organization_id, key)` — allocated with `SELECT … FOR UPDATE` |
| `audit_logs` | `organization_id` NULL, `user_id`, `action`, `entity_type`, `entity_id`, `old_values` LONGTEXT, `new_values` LONGTEXT, `ip`, `user_agent`, `metadata` LONGTEXT, `created_at`. Idx `(organization_id, created_at)`, `(entity_type, entity_id)` |
| `notifications` | `organization_id`, `user_id`, `type`, `title`, `body`, `entity_type`, `entity_id`, `read_at` |
| `migrations` | `migration` UQ, `batch`, `ran_at` |

## Indexing strategy

Every tenant table leads its composite indexes with `organization_id`, because **every** query is
tenant-scoped. Additional hot paths:

* `appointments (organization_id, doctor_id, scheduled_at)` — overlap detection & doctor day view
* `appointments (organization_id, branch_id, scheduled_at)` — branch/day views & dashboards
* `patients (organization_id, phone_normalized)` — duplicate detection
* `patients (organization_id, full_name)` — search
* `queue_entries (organization_id, branch_id, queue_date, status)`
* `payments (organization_id, paid_at, method)` — finance breakdown
* `invoices (organization_id, status, invoice_date)` — receivables
* `inventory_transactions (organization_id, item_id)` — stock derivation
* `audit_logs (organization_id, created_at)`

## Business numbering

`number_sequences` holds `next_value` per `(organization_id, key)`. Allocation runs inside the
caller's transaction:

```sql
SELECT prefix, padding, next_value FROM number_sequences
 WHERE organization_id = ? AND `key` = ? FOR UPDATE;
UPDATE number_sequences SET next_value = next_value + 1
 WHERE organization_id = ? AND `key` = ?;
```
producing `PAT-000001`, `LEAD-000001`, `APT-000001`, `INV-000001`, `PAY-000001`, `EXP-000001`,
`RX-000001`, `REF-000001`. Prefix and padding are configurable per organization in Settings.
Never generated in JavaScript.

## Migrations

Numbered PHP files in `server/database/migrations/`, applied in filename order, recorded in
`migrations` with a batch number.

```bash
php server/database/migrate.php            # apply pending
php server/database/migrate.php --fresh    # drop all + reapply (dev only)
php server/database/migrate.php --status   # list applied/pending
php server/database/seed.php --demo        # demo organizations + data
```

Rollback in production is by restoring a `mysqldump` taken immediately before migrating — see
`docs/HOSTINGER_DEPLOYMENT.md`. Migrations are written additively (add column / add table) so a
deploy can be reverted without data loss.
