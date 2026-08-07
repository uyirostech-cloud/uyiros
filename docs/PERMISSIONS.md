# ClinicFlow — Roles & Permissions

Permissions are **data**. `roles` → `role_permissions` → `permissions`. Code checks slugs
(`$ctx->can('invoice.create')`), never role names. Sidebar visibility is UX; the API is the
security boundary.

## Permission catalogue

| Group | Slug | Meaning |
|---|---|---|
| dashboard | `dashboard.view` | Clinic dashboard |
| lead | `lead.view` `lead.create` `lead.update` `lead.delete` `lead.assign` `lead.convert` | CRM |
| patient | `patient.view` `patient.create` `patient.update` `patient.delete` | Patient master |
| doctor | `doctor.view` `doctor.create` `doctor.update` `doctor.delete` | Doctor master |
| appointment | `appointment.view` `appointment.create` `appointment.update` `appointment.cancel` `appointment.reschedule` | Scheduling |
| queue | `queue.view` `queue.manage` | Check-in / token / calling |
| consultation | `consultation.view` `consultation.create` `consultation.update` `consultation.finalize` | Clinical notes |
| vitals | `vitals.view` `vitals.create` | Vitals |
| prescription | `prescription.view` `prescription.create` `prescription.update` `prescription.finalize` | Rx |
| service | `service.view` `service.manage` | Service catalogue |
| invoice | `invoice.view` `invoice.create` `invoice.update` `invoice.cancel` | Billing |
| payment | `payment.view` `payment.create` `payment.refund` `payment.void` | Payments |
| finance | `finance.view` `finance.close_day` | Finance dashboard, daily closing |
| expense | `expense.view` `expense.create` `expense.update` `expense.approve` `expense.delete` | Expenses |
| inventory | `inventory.view` `inventory.manage` `inventory.transact` | Stock |
| vendor | `vendor.view` `vendor.manage` | Vendors |
| task | `task.view` `task.create` `task.update` `task.assign` | Operations tasks |
| report | `report.view` `report.export` | Reports |
| user | `user.view` `user.manage` | Clinic users |
| branch | `branch.view` `branch.manage` | Branches |
| role | `role.view` `role.manage` | Roles/permissions |
| settings | `settings.view` `settings.manage` | Settings |
| audit | `audit.view` | Audit logs |
| platform | `platform.organization.manage` `platform.plan.manage` `platform.subscription.manage` `platform.user.view` `platform.settings.manage` | Level 1 only |

## Default role matrix

`✔` = granted.

| Permission group | Clinic Admin | Doctor | Receptionist | Sales/CRM | Operations | Finance |
|---|:--:|:--:|:--:|:--:|:--:|:--:|
| dashboard.view | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| lead.* | ✔ | | view | ✔ | | |
| patient.view | ✔ | ✔ | ✔ | ✔ | | ✔ |
| patient.create / update | ✔ | | ✔ | create | | |
| doctor.view | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| doctor.create/update/delete | ✔ | | | | | |
| appointment.view | ✔ | ✔ | ✔ | ✔ | | ✔ |
| appointment.create/update/cancel/reschedule | ✔ | | ✔ | create | | |
| queue.view / queue.manage | ✔ | view | ✔ | | | |
| vitals.view / vitals.create | ✔ | ✔ | ✔ | | | |
| consultation.view | ✔ | ✔ | | | | |
| consultation.create/update/finalize | | ✔ | | | | |
| prescription.view | ✔ | ✔ | ✔ | | | |
| prescription.create/update/finalize | | ✔ | | | | |
| service.view / service.manage | ✔ | view | view | view | view | view |
| invoice.view | ✔ | | ✔ | | | ✔ |
| invoice.create/update | ✔ | | ✔ | | | ✔ |
| invoice.cancel | ✔ | | | | | ✔ |
| payment.view / payment.create | ✔ | | ✔ | | | ✔ |
| payment.refund / payment.void | ✔ | | | | | ✔ |
| finance.view / finance.close_day | ✔ | | | | | ✔ |
| expense.view/create/update | ✔ | | | | ✔ | ✔ |
| expense.approve | ✔ | | | | | ✔ |
| inventory.* | ✔ | | | | ✔ | view |
| vendor.* | ✔ | | | | ✔ | view |
| task.* | ✔ | view | view | view | ✔ | |
| report.view / report.export | ✔ | own-scope | ✔ | ✔ | ✔ | ✔ |
| user.view / user.manage | ✔ | | | | | |
| branch.view | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ |
| branch.manage | ✔ | | | | | |
| role.view / role.manage | ✔ | | | | | |
| settings.view / settings.manage | ✔ | | | | | |
| audit.view | ✔ | | | | | |
| platform.* | — | — | — | — | — | — |

Notable denials the test suite asserts:

* **Receptionist → `finance.view` = denied** → `/api/finance/*` returns 403.
* **Sales/CRM → `consultation.view` = denied** → `/api/consultations/*` returns 403.
* **Doctor → `invoice.create` = denied**, and `user.manage` denied.
* **Operations → `patient.view` denied** (no clinical or patient data).
* **Nobody except Clinic Admin can change roles or settings.**

## Branch authorization

Independent of permissions:

* `user_branches` lists the branches a user may touch; `users.all_branches = 1` means the whole org.
* Every branch-scoped request validates `branch_id ∈ Ctx->branchIds`, else 403.
* List endpoints without an explicit `branch_id` are filtered to `Ctx->branchIds` automatically.

## Platform admin

`users.is_platform_admin = 1`, `organization_id = NULL`, no clinic role. Platform admins:

* may use `/api/platform/*` only,
* are refused (403) on every clinic endpoint by `ResolveTenant`,
* cannot read patient/clinical rows through the API at all.

## Changing permissions

Clinic Admin may create custom roles and toggle permissions (`role.manage`). System roles
(`is_system = 1`) can be cloned but their permission set is protected from removal of
`settings.manage`/`user.manage` on the last admin role, so an org cannot lock itself out.
Every change writes an audit log entry with old and new permission sets.
