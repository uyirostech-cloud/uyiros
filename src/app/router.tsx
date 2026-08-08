import { createBrowserRouter, Navigate } from 'react-router-dom';
import { AppLayout } from '@/layouts/AppLayout';
import { RequireAuth, RequirePermission } from './Protected';

import LoginPage from '@/features/auth/LoginPage';
import ForgotPasswordPage from '@/features/auth/ForgotPasswordPage';
import ResetPasswordPage from '@/features/auth/ResetPasswordPage';
import ChangePasswordPage from '@/features/auth/ChangePasswordPage';
import DashboardPage from '@/features/dashboard/DashboardPage';
import PatientsListPage from '@/features/patients/PatientsListPage';
import PatientDetailPage from '@/features/patients/PatientDetailPage';
import BranchesPage from '@/features/administration/BranchesPage';
import UsersPage from '@/features/administration/UsersPage';
import RolesPage from '@/features/administration/RolesPage';
import SettingsPage from '@/features/administration/SettingsPage';
import AuditLogsPage from '@/features/administration/AuditLogsPage';
import { ComingSoon } from '@/features/shared/ComingSoon';

import PlatformLayout from '@/features/platform/PlatformLayout';
import PlatformOverviewPage from '@/features/platform/PlatformOverviewPage';
import PlatformOrganizationsPage from '@/features/platform/PlatformOrganizationsPage';
import PlatformPlansPage from '@/features/platform/PlatformPlansPage';
import PlatformSubscriptionsPage from '@/features/platform/PlatformSubscriptionsPage';
import PlatformUsersPage from '@/features/platform/PlatformUsersPage';

function guarded(permission: string, node: React.ReactNode) {
  return <RequirePermission permission={permission}>{node}</RequirePermission>;
}

export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  { path: '/forgot-password', element: <ForgotPasswordPage /> },
  { path: '/reset-password', element: <ResetPasswordPage /> },

  {
    path: '/platform',
    element: <RequireAuth><PlatformLayout /></RequireAuth>,
    children: [
      { index: true, element: <PlatformOverviewPage /> },
      { path: 'organizations', element: <PlatformOrganizationsPage /> },
      { path: 'plans', element: <PlatformPlansPage /> },
      { path: 'subscriptions', element: <PlatformSubscriptionsPage /> },
      { path: 'users', element: <PlatformUsersPage /> },
    ],
  },

  {
    path: '/',
    element: <RequireAuth><AppLayout /></RequireAuth>,
    children: [
      { index: true, element: guarded('dashboard.view', <DashboardPage />) },

      { path: 'leads', element: guarded('lead.view', <ComingSoon title="Leads" phase="Phase 5 — CRM" />) },
      { path: 'leads/followups', element: guarded('lead.view', <ComingSoon title="Follow-ups" phase="Phase 5 — CRM" />) },

      { path: 'appointments', element: guarded('appointment.view', <ComingSoon title="Appointments" phase="Phase 2 — Patient Flow" />) },
      { path: 'queue', element: guarded('queue.view', <ComingSoon title="Queue" phase="Phase 2 — Patient Flow" />) },
      { path: 'patients', element: guarded('patient.view', <PatientsListPage />) },
      { path: 'patients/:id', element: guarded('patient.view', <PatientDetailPage />) },

      { path: 'consultations', element: guarded('consultation.view', <ComingSoon title="Consultations" phase="Phase 3 — Clinical" />) },
      { path: 'prescriptions', element: guarded('prescription.view', <ComingSoon title="Prescriptions" phase="Phase 3 — Clinical" />) },

      { path: 'invoices', element: guarded('invoice.view', <ComingSoon title="Invoices" phase="Phase 4 — Billing" />) },
      { path: 'payments', element: guarded('payment.view', <ComingSoon title="Payments" phase="Phase 4 — Billing" />) },

      { path: 'tasks', element: guarded('task.view', <ComingSoon title="Tasks" phase="Phase 7 — Operations" />) },
      { path: 'inventory', element: guarded('inventory.view', <ComingSoon title="Inventory" phase="Phase 7 — Operations" />) },
      { path: 'vendors', element: guarded('vendor.view', <ComingSoon title="Vendors" phase="Phase 7 — Operations" />) },

      { path: 'finance', element: guarded('finance.view', <ComingSoon title="Finance Dashboard" phase="Phase 6 — Finance" />) },
      { path: 'expenses', element: guarded('expense.view', <ComingSoon title="Expenses" phase="Phase 6 — Finance" />) },
      { path: 'finance/closing', element: guarded('finance.close_day', <ComingSoon title="Daily Closing" phase="Phase 6 — Finance" />) },

      { path: 'reports', element: guarded('report.view', <ComingSoon title="Reports" phase="Phase 8 — Reports" />) },

      { path: 'administration/doctors', element: guarded('doctor.view', <ComingSoon title="Doctors" phase="Phase 2 — Patient Flow" />) },
      { path: 'administration/users', element: guarded('user.view', <UsersPage />) },
      { path: 'administration/branches', element: guarded('branch.view', <BranchesPage />) },
      { path: 'administration/roles', element: guarded('role.view', <RolesPage />) },
      { path: 'administration/services', element: guarded('service.view', <ComingSoon title="Services" phase="Phase 4 — Billing" />) },
      { path: 'administration/audit-logs', element: guarded('audit.view', <AuditLogsPage />) },
      { path: 'administration/settings', element: guarded('settings.view', <SettingsPage />) },

      { path: 'account/change-password', element: <ChangePasswordPage /> },
      { path: 'account', element: <Navigate to="/account/change-password" replace /> },
    ],
  },

  { path: '*', element: <Navigate to="/" replace /> },
]);
