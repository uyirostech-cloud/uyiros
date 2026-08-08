import { http } from '@/services/http';
import type {
  AuditLogEntry,
  Branch,
  DashboardCharts,
  DashboardSummary,
  Patient,
  PermissionOption,
  PlatformOrganization,
  PlatformOverview,
  Role,
  User,
} from '@/types/models';

export interface ListParams {
  page?: number;
  per_page?: number;
  q?: string;
  sort?: string;
  dir?: 'asc' | 'desc';
  branch_id?: number | '';
  [key: string]: string | number | boolean | undefined | null;
}

export const dashboardApi = {
  summary: (branchId?: number | '') =>
    http.get<DashboardSummary>('/dashboard/summary', { query: { branch_id: branchId || undefined } }),
  charts: (branchId?: number | '', days = 30) =>
    http.get<DashboardCharts>('/dashboard/charts', {
      query: { branch_id: branchId || undefined, days },
    }),
};

export const patientsApi = {
  list: (params: ListParams) => http.getPaginated<Patient>('/patients', { query: params }),
  get: (id: number) => http.get<Patient>(`/patients/${id}`),
  create: (payload: Partial<Patient> & { force?: boolean }) => http.post<Patient>('/patients', payload),
  update: (id: number, payload: Partial<Patient> & { force?: boolean }) =>
    http.put<Patient>(`/patients/${id}`, payload),
  remove: (id: number) => http.delete<{ message: string }>(`/patients/${id}`),
  search: (q: string) => http.get<Patient[]>('/patients/search', { query: { q } }),
  checkDuplicate: (phone: string) =>
    http.get<{ duplicate: boolean; patient: Patient | null }>('/patients/check-duplicate', {
      query: { phone },
    }),
};

export const branchesApi = {
  list: (params: ListParams = {}) => http.getPaginated<Branch>('/branches', { query: params }),
  get: (id: number) => http.get<Branch>(`/branches/${id}`),
  create: (payload: Partial<Branch>) => http.post<Branch>('/branches', payload),
  update: (id: number, payload: Partial<Branch>) => http.put<Branch>(`/branches/${id}`, payload),
  remove: (id: number) => http.delete<{ message: string }>(`/branches/${id}`),
};

export const usersApi = {
  list: (params: ListParams = {}) => http.getPaginated<User>('/users', { query: params }),
  get: (id: number) => http.get<User>(`/users/${id}`),
  create: (payload: Record<string, unknown>) => http.post<User>('/users', payload),
  update: (id: number, payload: Record<string, unknown>) => http.put<User>(`/users/${id}`, payload),
  deactivate: (id: number) => http.post<{ message: string }>(`/users/${id}/deactivate`),
  resetPassword: (id: number, password: string) =>
    http.post<{ message: string }>(`/users/${id}/reset-password`, { password }),
};

export const rolesApi = {
  list: () => http.get<Role[]>('/roles'),
  permissions: () => http.get<Record<string, PermissionOption[]>>('/roles/permissions'),
  create: (payload: { name: string; description?: string; permissions: string[] }) =>
    http.post<Role>('/roles', payload),
  update: (id: number, payload: { name?: string; description?: string; permissions?: string[] }) =>
    http.put<{ message: string }>(`/roles/${id}`, payload),
  remove: (id: number) => http.delete<{ message: string }>(`/roles/${id}`),
};

export interface SettingsPayload {
  organization: Record<string, string | null>;
  settings: Record<string, string | null>;
  sequences: { key: string; prefix: string; padding: number; next_value: number; preview: string }[];
  allowed_keys: string[];
}

export const settingsApi = {
  get: () => http.get<SettingsPayload>('/settings'),
  updateOrganization: (payload: Record<string, unknown>) =>
    http.put<{ message: string }>('/settings/organization', payload),
  update: (settings: Record<string, string | null>) =>
    http.put<{ message: string }>('/settings', { settings }),
  updateNumbering: (key: string, prefix: string, padding: number) =>
    http.put<{ message: string }>('/settings/numbering', { key, prefix, padding }),
};

export const auditApi = {
  list: (params: ListParams = {}) => http.getPaginated<AuditLogEntry>('/audit-logs', { query: params }),
};

export const platformApi = {
  overview: () => http.get<PlatformOverview>('/platform/overview'),
  organizations: (params: ListParams = {}) =>
    http.getPaginated<PlatformOrganization>('/platform/organizations', { query: params }),
  createOrganization: (payload: Record<string, unknown>) =>
    http.post<{ id: number; code: string; name: string }>('/platform/organizations', payload),
  setOrganizationStatus: (id: number, status: string, reason?: string) =>
    http.put<{ message: string; status: string }>(`/platform/organizations/${id}/status`, { status, reason }),
  plans: () => http.get<Record<string, unknown>[]>('/platform/plans'),
  subscriptions: (params: ListParams = {}) =>
    http.getPaginated<Record<string, unknown>>('/platform/subscriptions', { query: params }),
  users: (params: ListParams = {}) =>
    http.getPaginated<Record<string, unknown>>('/platform/users', { query: params }),
};
