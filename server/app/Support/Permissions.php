<?php
namespace App\Support;

/** The permission catalogue and the default role → permission matrix (docs/PERMISSIONS.md). */
final class Permissions
{
    /** @return array<string,array{0:string,1:string}> slug => [group, label] */
    public static function catalogue(): array
    {
        $defs = [
            'dashboard'   => ['view' => 'View dashboard'],
            'lead'        => ['view' => 'View leads', 'create' => 'Create leads', 'update' => 'Update leads',
                              'delete' => 'Delete leads', 'assign' => 'Assign leads', 'convert' => 'Convert leads'],
            'patient'     => ['view' => 'View patients', 'create' => 'Register patients',
                              'update' => 'Update patients', 'delete' => 'Delete patients'],
            'doctor'      => ['view' => 'View doctors', 'create' => 'Create doctors',
                              'update' => 'Update doctors', 'delete' => 'Delete doctors'],
            'appointment' => ['view' => 'View appointments', 'create' => 'Book appointments',
                              'update' => 'Update appointments', 'cancel' => 'Cancel appointments',
                              'reschedule' => 'Reschedule appointments'],
            'queue'       => ['view' => 'View queue', 'manage' => 'Manage queue and check-in'],
            'vitals'      => ['view' => 'View vitals', 'create' => 'Record vitals'],
            'consultation'=> ['view' => 'View consultations', 'create' => 'Create consultations',
                              'update' => 'Update consultations', 'finalize' => 'Finalize consultations'],
            'prescription'=> ['view' => 'View prescriptions', 'create' => 'Create prescriptions',
                              'update' => 'Update prescriptions', 'finalize' => 'Finalize prescriptions'],
            'service'     => ['view' => 'View services', 'manage' => 'Manage services'],
            'invoice'     => ['view' => 'View invoices', 'create' => 'Create invoices',
                              'update' => 'Update invoices', 'cancel' => 'Cancel invoices'],
            'payment'     => ['view' => 'View payments', 'create' => 'Record payments',
                              'refund' => 'Issue refunds', 'void' => 'Void payments'],
            'finance'     => ['view' => 'View finance', 'close_day' => 'Perform daily closing'],
            'expense'     => ['view' => 'View expenses', 'create' => 'Create expenses',
                              'update' => 'Update expenses', 'approve' => 'Approve expenses',
                              'delete' => 'Delete expenses'],
            'inventory'   => ['view' => 'View inventory', 'manage' => 'Manage inventory items',
                              'transact' => 'Record stock movements'],
            'vendor'      => ['view' => 'View vendors', 'manage' => 'Manage vendors'],
            'task'        => ['view' => 'View tasks', 'create' => 'Create tasks',
                              'update' => 'Update tasks', 'assign' => 'Assign tasks'],
            'report'      => ['view' => 'View reports', 'export' => 'Export reports'],
            'user'        => ['view' => 'View users', 'manage' => 'Manage users'],
            'branch'      => ['view' => 'View branches', 'manage' => 'Manage branches'],
            'role'        => ['view' => 'View roles', 'manage' => 'Manage roles and permissions'],
            'settings'    => ['view' => 'View settings', 'manage' => 'Change settings'],
            'audit'       => ['view' => 'View audit logs'],
        ];

        $out = [];
        foreach ($defs as $group => $actions) {
            foreach ($actions as $action => $label) {
                $out["{$group}.{$action}"] = [$group, $label];
            }
        }
        foreach ([
            'platform.organization.manage' => 'Manage organizations',
            'platform.plan.manage'         => 'Manage plans',
            'platform.subscription.manage' => 'Manage subscriptions',
            'platform.user.view'           => 'View users across organizations',
            'platform.settings.manage'     => 'Manage system settings',
        ] as $slug => $label) {
            $out[$slug] = ['platform', $label];
        }
        return $out;
    }

    public static function all(): array
    {
        return array_keys(self::catalogue());
    }

    /** @return array<string,array{name:string,permissions:string[]}> */
    public static function defaultRoles(): array
    {
        $all = self::all();
        $clinicAdmin = array_values(array_filter($all, static fn ($p) => !str_starts_with($p, 'platform.')));

        return [
            'clinic_admin' => [
                'name'        => 'Clinic Admin',
                'description' => 'Full access to this clinic including settings, users and finance',
                'permissions' => $clinicAdmin,
            ],
            'doctor' => [
                'name'        => 'Doctor',
                'description' => 'Clinical records, own queue and prescriptions',
                'permissions' => [
                    'dashboard.view', 'patient.view', 'doctor.view',
                    'appointment.view', 'queue.view',
                    'vitals.view', 'vitals.create',
                    'consultation.view', 'consultation.create', 'consultation.update', 'consultation.finalize',
                    'prescription.view', 'prescription.create', 'prescription.update', 'prescription.finalize',
                    'service.view', 'task.view', 'branch.view', 'report.view',
                ],
            ],
            'receptionist' => [
                'name'        => 'Receptionist',
                'description' => 'Front desk: patients, appointments, check-in, billing and payments',
                'permissions' => [
                    'dashboard.view', 'lead.view',
                    'patient.view', 'patient.create', 'patient.update',
                    'doctor.view',
                    'appointment.view', 'appointment.create', 'appointment.update',
                    'appointment.cancel', 'appointment.reschedule',
                    'queue.view', 'queue.manage',
                    'vitals.view', 'vitals.create',
                    'prescription.view',
                    'service.view',
                    'invoice.view', 'invoice.create', 'invoice.update',
                    'payment.view', 'payment.create',
                    'task.view', 'branch.view', 'report.view', 'report.export',
                ],
            ],
            'sales' => [
                'name'        => 'Sales / CRM',
                'description' => 'Lead capture, follow-ups and conversion',
                'permissions' => [
                    'dashboard.view',
                    'lead.view', 'lead.create', 'lead.update', 'lead.assign', 'lead.convert', 'lead.delete',
                    'patient.view', 'patient.create',
                    'doctor.view', 'appointment.view', 'appointment.create',
                    'service.view', 'task.view', 'branch.view',
                    'report.view', 'report.export',
                ],
            ],
            'operations' => [
                'name'        => 'Operations',
                'description' => 'Tasks, inventory and vendors',
                'permissions' => [
                    'dashboard.view',
                    'task.view', 'task.create', 'task.update', 'task.assign',
                    'inventory.view', 'inventory.manage', 'inventory.transact',
                    'vendor.view', 'vendor.manage',
                    'expense.view', 'expense.create', 'expense.update',
                    'doctor.view', 'service.view', 'branch.view',
                    'report.view', 'report.export',
                ],
            ],
            'finance' => [
                'name'        => 'Finance / Accountant',
                'description' => 'Billing, collections, expenses and daily closing',
                'permissions' => [
                    'dashboard.view',
                    'patient.view', 'doctor.view', 'appointment.view',
                    'service.view',
                    'invoice.view', 'invoice.create', 'invoice.update', 'invoice.cancel',
                    'payment.view', 'payment.create', 'payment.refund', 'payment.void',
                    'finance.view', 'finance.close_day',
                    'expense.view', 'expense.create', 'expense.update', 'expense.approve',
                    'inventory.view', 'vendor.view',
                    'branch.view', 'report.view', 'report.export',
                ],
            ],
        ];
    }
}
