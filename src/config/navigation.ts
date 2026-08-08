export interface NavItem {
  label: string;
  path: string;
  permission?: string;
  permissionAny?: string[];
}

export interface NavSection {
  label: string | null;
  items: NavItem[];
}

/**
 * Sidebar visibility is convenience only — every route it points at is re-checked by the
 * API through the real permission system. Hiding a link here never substitutes for a
 * server-side authorization decision.
 */
export const navigation: NavSection[] = [
  { label: null, items: [{ label: 'Dashboard', path: '/app', permission: 'dashboard.view' }] },
  {
    label: 'CRM',
    items: [
      { label: 'Leads', path: '/app/leads', permission: 'lead.view' },
      { label: 'Follow-ups', path: '/app/leads/followups', permission: 'lead.view' },
    ],
  },
  {
    label: 'Front Desk',
    items: [
      { label: 'Appointments', path: '/app/appointments', permission: 'appointment.view' },
      { label: 'Queue', path: '/app/queue', permission: 'queue.view' },
      { label: 'Patients', path: '/app/patients', permission: 'patient.view' },
    ],
  },
  {
    label: 'Clinical',
    items: [
      { label: 'Consultations', path: '/app/consultations', permission: 'consultation.view' },
      { label: 'Prescriptions', path: '/app/prescriptions', permission: 'prescription.view' },
    ],
  },
  {
    label: 'Billing',
    items: [
      { label: 'Invoices', path: '/app/invoices', permission: 'invoice.view' },
      { label: 'Payments', path: '/app/payments', permission: 'payment.view' },
    ],
  },
  {
    label: 'Operations',
    items: [
      { label: 'Tasks', path: '/app/tasks', permission: 'task.view' },
      { label: 'Inventory', path: '/app/inventory', permission: 'inventory.view' },
      { label: 'Vendors', path: '/app/vendors', permission: 'vendor.view' },
    ],
  },
  {
    label: 'Finance',
    items: [
      { label: 'Finance Dashboard', path: '/app/finance', permission: 'finance.view' },
      { label: 'Expenses', path: '/app/expenses', permission: 'expense.view' },
      { label: 'Daily Closing', path: '/app/finance/closing', permission: 'finance.close_day' },
    ],
  },
  { label: null, items: [{ label: 'Reports', path: '/app/reports', permission: 'report.view' }] },
  {
    label: 'Administration',
    items: [
      { label: 'Doctors', path: '/app/administration/doctors', permission: 'doctor.view' },
      { label: 'Users', path: '/app/administration/users', permission: 'user.view' },
      { label: 'Branches', path: '/app/administration/branches', permission: 'branch.view' },
      { label: 'Roles', path: '/app/administration/roles', permission: 'role.view' },
      { label: 'Services', path: '/app/administration/services', permission: 'service.view' },
      { label: 'Audit Logs', path: '/app/administration/audit-logs', permission: 'audit.view' },
      { label: 'Settings', path: '/app/administration/settings', permission: 'settings.view' },
    ],
  },
];

export const platformNavigation: NavItem[] = [
  { label: 'Overview', path: '/platform' },
  { label: 'Organizations', path: '/platform/organizations' },
  { label: 'Plans', path: '/platform/plans' },
  { label: 'Subscriptions', path: '/platform/subscriptions' },
  { label: 'Users', path: '/platform/users' },
];
