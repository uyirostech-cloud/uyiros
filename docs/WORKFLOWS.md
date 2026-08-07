# ClinicFlow — Connected Workflows

Modules are not independent screens. Each step below writes a row the next step reads.

## 1. Master flow

```
Lead ──convert──► Patient ──book──► Appointment ──check-in──► Queue Entry
                                            │
                                            ▼
                                      Consultation ──► Vitals + Diagnoses
                                            │
                                            ▼
                                      Prescription (finalize)
                                            │
                                            ▼
                                        Invoice ──► Payment(s) / Refund(s)
                                            │
                                            ▼
                                    Finance ──► Dashboard ──► Reports
```

## 2. Lead → Patient conversion

1. Lead captured (`leads`), `phone_normalized` computed (digits only, last 10 kept for IN numbers).
2. Duplicate check on create: existing lead **or** patient in the same org with the same
   `phone_normalized` → API returns `409 DUPLICATE_PHONE` with the matching record, and the UI
   offers "open existing" or "create anyway" (`force=true`).
3. Follow-ups appended to `lead_followups`; each sets `leads.next_followup_at` and status.
4. Conversion (`POST /api/leads/{id}/convert`), in one transaction:
   * if a patient with the same `phone_normalized` exists → **link**, do not duplicate;
   * else create `patients` with a new `PAT-xxxxxx` from `number_sequences`;
   * set `leads.status='Converted'`, `converted_patient_id`, `converted_at`;
   * optionally create the appointment in the same transaction;
   * audit `lead.converted`.

Statuses: `New → Contacted → Follow-up → Interested → Appointment Booked → Converted`
with terminal `Not Interested` / `Lost`.

## 3. Appointment booking

`POST /api/appointments` validates, in order:
1. patient, doctor, branch all belong to `Ctx->organizationId` (re-loaded, not trusted);
2. `branch_id ∈ Ctx->branchIds`;
3. the doctor works at that branch and the slot lies inside `doctor_schedules` for that weekday
   (warning, not a hard block, when `allow_outside_schedule` setting is on);
4. **overlap check** — inside a transaction with the doctor's rows locked:
   ```sql
   SELECT id FROM appointments
    WHERE organization_id = :org AND doctor_id = :doc
      AND status NOT IN ('Cancelled','No Show','Rescheduled')
      AND scheduled_at < :ends_at
      AND DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > :starts_at
    FOR UPDATE
   ```
   any row → `409 DOCTOR_UNAVAILABLE`.
5. allocate `APT-xxxxxx`, insert, write `appointment_status_history` (`null → Scheduled`), audit.

Status machine:

```
Scheduled ─► Confirmed ─► Checked In ─► Waiting ─► In Consultation ─► Completed
     │            │            │            │              │
     └────────────┴────────────┴────────────┴──────────────┴──► Cancelled
Scheduled/Confirmed ──► No Show          Scheduled/Confirmed ──► Rescheduled (new appointment linked)
```
Illegal transitions are rejected with `409 INVALID_TRANSITION`. Every transition appends to
`appointment_status_history`.

## 4. Reception → check-in → queue

```
Patient arrives → search by phone/UHID/name → confirm or create appointment
 → POST /api/appointments/{id}/check-in
```
In one transaction: appointment → `Checked In` then `Waiting`; a `queue_entries` row is created
with the next token for `(organization, branch, today)` allocated under a row lock; `arrived_at`
recorded. Queue list returns token, patient, doctor, scheduled time, arrival time,
`waiting_minutes = TIMESTAMPDIFF(MINUTE, arrived_at, NOW())`, status.

Doctor dashboard reads the same `queue_entries` filtered by `doctor_id` and `status='waiting'`.
"Call next" sets `called_at`, queue status `in_consultation`, appointment `In Consultation`.

## 5. Consultation

Opening a consultation loads, tenant-scoped: patient summary, allergies, previous consultations
(diagnoses + advice), previous prescriptions, previous vitals, today's vitals.

Save as **draft** any number of times. **Finalize** sets `status='finalized'`, `finalized_at`,
appointment → `Completed`, queue entry → `completed`, and audits `consultation.finalized`.
A finalized consultation can still be amended (`consultation.update`) but each amendment is
audited with old/new values — history is never overwritten silently.

Vitals: BMI computed server-side `weight_kg / (height_cm/100)^2`, rounded to 2 dp; each recording
is a new `vitals` row (never an update of an old one).

## 6. Prescription

Created from a consultation, multiple `prescription_items`. Draft is freely editable.
`POST /api/prescriptions/{id}/finalize` snapshots the full document into `prescription_revisions`
(version 1) and locks it. Any later edit requires a reason, bumps `version`, and writes another
revision row. Printable view (`GET /api/prescriptions/{id}/print`) renders clinic header, doctor
(name, qualification, registration no.), patient (UHID, name, age, gender), date, medicine table,
advice, follow-up date.

## 7. Billing

Invoice may be created from an appointment (pre-filled with the doctor's consultation/follow-up fee
and any services) or standalone.

Line math, computed **server-side only**:
```
line_taxable = quantity * unit_price - discount_amount
line_tax     = round(line_taxable * tax_percent / 100, 2)
line_total   = line_taxable + line_tax
subtotal     = Σ quantity * unit_price
discount     = Σ discount_amount        (+ invoice-level discount)
tax          = Σ line_tax
total        = subtotal - discount + tax
balance      = total - paid_amount
```
All in DECIMAL. Client-submitted totals are discarded. Status:
`Draft → Unpaid → Partially Paid → Paid`, plus `Cancelled` and `Refunded`.
A cancelled invoice must have zero completed payments (else `409`); cancellation is audited.

## 8. Payments (partial / split / multiple / refund)

Each payment is its own `payments` row with its own method — that is what makes splitting work:

```
Invoice INV-000001  total 500.00
  PAY-000001  Cash 300.00
  PAY-000002  UPI  200.00
  → paid_amount 500.00, balance 0.00, status Paid
Finance: Cash 300.00 | UPI 200.00 | Total collection 500.00
```

`PaymentService::record()` runs in a transaction: lock the invoice row, insert payment, recompute
```sql
paid = COALESCE((SELECT SUM(amount) FROM payments WHERE invoice_id=? AND status='completed'),0)
     - COALESCE((SELECT SUM(amount) FROM refunds  WHERE invoice_id=?),0)
```
write `paid_amount`/`balance_amount`, derive status, audit. Overpayment (`paid > total`) is
rejected with `422`. Refunds insert into `refunds`, reduce `paid_amount`, and set status
`Refunded` when the invoice nets to zero paid.

**Reconciliation invariant (tested):** for every invoice,
`paid_amount == Σ completed payments − Σ refunds` and `balance_amount == total − paid_amount`;
and for any date range, `finance.total_collection == Σ payments − Σ refunds ==` the sum of the
per-method breakdown.

## 9. Finance & daily closing

Finance dashboard queries `payments`, `refunds`, `invoices`, `expenses` for the selected range,
branch, doctor and method — the same rows the detail lists show.

Daily closing for `(branch, date)`:
```
expected_cash = Σ payments(method=Cash) − Σ refunds(method=Cash) − Σ expenses(paid, method=Cash)
variance      = actual_cash − expected_cash
```
stored in `daily_closings`; re-closing the same day updates the row and audits the change.

## 10. Expenses

`Draft → Pending → Approved → Paid`, or `Rejected`. Only `expense.approve` may move
Pending→Approved/Rejected, and the approver is recorded. Approved+Paid cash expenses feed the
daily closing expectation.

## 11. Inventory

Stock only ever changes by inserting an `inventory_transactions` row (`in`, `out`, `adjustment`).
`current_stock` is a SUM over that table. Low stock = `current_stock <= minimum_stock` → shown on
the operations dashboard and as a notification.

## 12. Reports

All reports are SQL aggregations executed server-side with the tenant filter, then paginated.
Export (`?export=csv|xlsx|pdf`) streams rows in chunks; the browser never receives an unpaginated
dataset. Every report accepts date range, branch, doctor, status, payment method and user filters
as applicable.

## 13. Dashboard reconciliation

Each card is a single scoped query over the same tables the detail screens use:

| Card | Source |
|---|---|
| Today's Appointments | `appointments` where `DATE(scheduled_at)=CURDATE()` |
| Today's Patients | distinct `patient_id` from today's appointments |
| Waiting Patients | `queue_entries` today, `status='waiting'` |
| Completed Consultations | `consultations` finalized today |
| New Leads | `leads` created today |
| Follow-ups | `leads` with `next_followup_at` today (+ overdue) |
| Today's Collection | `Σ payments − Σ refunds` today |
| Outstanding | `Σ invoices.balance_amount` where status in (Unpaid, Partially Paid) |
| Today's Expenses | `Σ expenses` today, status in (Approved, Paid) |

Clicking a card navigates to the list with the identical filter, so the numbers always agree.
