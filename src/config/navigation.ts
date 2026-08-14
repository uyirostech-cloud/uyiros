export type MobileIconKey =
  | 'dashboard' | 'patients' | 'appointments' | 'queue' | 'leads' | 'invoices' | 'tasks' | 'consultations'
  | 'followups' | 'prescriptions' | 'payments' | 'inventory' | 'vendors' | 'finance' | 'expenses'
  | 'dailyClosing' | 'reports' | 'doctors' | 'users' | 'branches' | 'roles' | 'services' | 'auditLogs' | 'settings';

export interface NavItem {
  label: string;
  path: string;
  permission?: string;
  permissionAny?: string[];
  icon?: MobileIconKey;
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
  { label: null, items: [{ label: 'Dashboard', path: '/app', permission: 'dashboard.view', icon: 'dashboard' }] },
  {
    label: 'CRM',
    items: [
      { label: 'Leads', path: '/app/leads', permission: 'lead.view', icon: 'leads' },
      { label: 'Follow-ups', path: '/app/leads/followups', permission: 'lead.view', icon: 'followups' },
    ],
  },
  {
    label: 'Front Desk',
    items: [
      { label: 'Appointments', path: '/app/appointments', permission: 'appointment.view', icon: 'appointments' },
      { label: 'Queue', path: '/app/queue', permission: 'queue.view', icon: 'queue' },
      { label: 'Patients', path: '/app/patients', permission: 'patient.view', icon: 'patients' },
    ],
  },
  {
    label: 'Clinical',
    items: [
      { label: 'Consultations', path: '/app/consultations', permission: 'consultation.view', icon: 'consultations' },
      { label: 'Prescriptions', path: '/app/prescriptions', permission: 'prescription.view', icon: 'prescriptions' },
    ],
  },
  {
    label: 'Billing',
    items: [
      { label: 'Invoices', path: '/app/invoices', permission: 'invoice.view', icon: 'invoices' },
      { label: 'Payments', path: '/app/payments', permission: 'payment.view', icon: 'payments' },
    ],
  },
  {
    label: 'Operations',
    items: [
      { label: 'Tasks', path: '/app/tasks', permission: 'task.view', icon: 'tasks' },
      { label: 'Inventory', path: '/app/inventory', permission: 'inventory.view', icon: 'inventory' },
      { label: 'Vendors', path: '/app/vendors', permission: 'vendor.view', icon: 'vendors' },
    ],
  },
  {
    label: 'Finance',
    items: [
      { label: 'Finance Dashboard', path: '/app/finance', permission: 'finance.view', icon: 'finance' },
      { label: 'Expenses', path: '/app/expenses', permission: 'expense.view', icon: 'expenses' },
      { label: 'Daily Closing', path: '/app/finance/closing', permission: 'finance.close_day', icon: 'dailyClosing' },
    ],
  },
  { label: null, items: [{ label: 'Reports', path: '/app/reports', permission: 'report.view', icon: 'reports' }] },
  {
    label: 'Administration',
    items: [
      { label: 'Doctors', path: '/app/administration/doctors', permission: 'doctor.view', icon: 'doctors' },
      { label: 'Users', path: '/app/administration/users', permission: 'user.view', icon: 'users' },
      { label: 'Branches', path: '/app/administration/branches', permission: 'branch.view', icon: 'branches' },
      { label: 'Roles', path: '/app/administration/roles', permission: 'role.view', icon: 'roles' },
      { label: 'Services', path: '/app/administration/services', permission: 'service.view', icon: 'services' },
      { label: 'Audit Logs', path: '/app/administration/audit-logs', permission: 'audit.view', icon: 'auditLogs' },
      { label: 'Settings', path: '/app/administration/settings', permission: 'settings.view', icon: 'settings' },
    ],
  },
];

export interface MobileNavItem extends NavItem {
  icon: MobileIconKey;
}

/**
 * Candidate tabs for the mobile bottom bar, in priority order. The bottom bar shows the
 * first 5 the signed-in user has permission for; "More" (always last) opens the full
 * Sidebar menu for everything else.
 */
export const mobileTabCandidates: MobileNavItem[] = [
  { label: 'Dashboard', path: '/app', permission: 'dashboard.view', icon: 'dashboard' },
  { label: 'Patients', path: '/app/patients', permission: 'patient.view', icon: 'patients' },
  { label: 'Appointments', path: '/app/appointments', permission: 'appointment.view', icon: 'appointments' },
  { label: 'Queue', path: '/app/queue', permission: 'queue.view', icon: 'queue' },
  { label: 'Leads', path: '/app/leads', permission: 'lead.view', icon: 'leads' },
  { label: 'Invoices', path: '/app/invoices', permission: 'invoice.view', icon: 'invoices' },
  { label: 'Tasks', path: '/app/tasks', permission: 'task.view', icon: 'tasks' },
  { label: 'Consultations', path: '/app/consultations', permission: 'consultation.view', icon: 'consultations' },
];

export const platformNavigation: NavItem[] = [
  { label: 'Overview', path: '/platform' },
  { label: 'Organizations', path: '/platform/organizations' },
  { label: 'Plans', path: '/platform/plans' },
  { label: 'Subscriptions', path: '/platform/subscriptions' },
  { label: 'Users', path: '/platform/users' },
];
