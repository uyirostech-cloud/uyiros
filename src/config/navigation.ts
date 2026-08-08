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
  { label: null, items: [{ label: 'Dashboard', path: '/', permission: 'dashboard.view' }] },
  {
    label: 'CRM',
    items: [
      { label: 'Leads', path: '/leads', permission: 'lead.view' },
      { label: 'Follow-ups', path: '/leads/followups', permission: 'lead.view' },
    ],
  },
  {
    label: 'Front Desk',
    items: [
      { label: 'Appointments', path: '/appointments', permission: 'appointment.view' },
      { label: 'Queue', path: '/queue', permission: 'queue.view' },
      { label: 'Patients', path: '/patients', permission: 'patient.view' },
    ],
  },
  {
    label: 'Clinical',
    items: [
      { label: 'Consultations', path: '/consultations', permission: 'consultation.view' },
      { label: 'Prescriptions', path: '/prescriptions', permission: 'prescription.view' },
    ],
  },
  {
    label: 'Billing',
    items: [
      { label: 'Invoices', path: '/invoices', permission: 'invoice.view' },
      { label: 'Payments', path: '/payments', permission: 'payment.view' },
    ],
  },
  {
    label: 'Operations',
    items: [
      { label: 'Tasks', path: '/tasks', permission: 'task.view' },
      { label: 'Inventory', path: '/inventory', permission: 'inventory.view' },
      { label: 'Vendors', path: '/vendors', permission: 'vendor.view' },
    ],
  },
  {
    label: 'Finance',
    items: [
      { label: 'Finance Dashboard', path: '/finance', permission: 'finance.view' },
      { label: 'Expenses', path: '/expenses', permission: 'expense.view' },
      { label: 'Daily Closing', path: '/finance/closing', permission: 'finance.close_day' },
    ],
  },
  { label: null, items: [{ label: 'Reports', path: '/reports', permission: 'report.view' }] },
  {
    label: 'Administration',
    items: [
      { label: 'Doctors', path: '/administration/doctors', permission: 'doctor.view' },
      { label: 'Users', path: '/administration/users', permission: 'user.view' },
      { label: 'Branches', path: '/administration/branches', permission: 'branch.view' },
      { label: 'Roles', path: '/administration/roles', permission: 'role.view' },
      { label: 'Services', path: '/administration/services', permission: 'service.view' },
      { label: 'Audit Logs', path: '/administration/audit-logs', permission: 'audit.view' },
      { label: 'Settings', path: '/administration/settings', permission: 'settings.view' },
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
