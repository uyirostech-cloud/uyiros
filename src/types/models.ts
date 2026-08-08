export type Permission = string;

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  organization_id: number | null;
  organization_name: string | null;
  role: string | null;
  role_id: number | null;
  permissions: Permission[];
  branch_ids: number[];
  all_branches: boolean;
  is_platform_admin: boolean;
  doctor_id: number | null;
}

export interface BranchSummary {
  id: number;
  name: string;
  code: string;
}

export interface SessionPayload {
  user: AuthUser;
  branches: BranchSummary[];
  csrf_token: string;
}

export interface Branch {
  id: number;
  name: string;
  code: string;
  phone: string | null;
  email: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
  pin: string | null;
  opening_time: string | null;
  closing_time: string | null;
  working_days: string;
  is_active: boolean;
  created_at: string;
}

export interface Role {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  is_system: boolean;
  user_count: number;
  permissions: Permission[];
}

export interface PermissionOption {
  slug: string;
  label: string;
}

export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role_id: number | null;
  role: { id: number; name: string; slug: string } | null;
  branches: { id: number; name: string }[];
  is_active: boolean;
  all_branches: boolean;
  last_login_at: string | null;
  created_at: string;
}

export interface Patient {
  id: number;
  public_id: string;
  patient_number: string;
  branch_id: number | null;
  branch_name?: string | null;
  full_name: string;
  phone: string;
  alt_phone: string | null;
  email: string | null;
  dob: string | null;
  age_years: number | null;
  gender: string | null;
  blood_group: string | null;
  address: string | null;
  city: string | null;
  state: string | null;
  pin: string | null;
  emergency_contact_name: string | null;
  emergency_contact_phone: string | null;
  occupation: string | null;
  source: string | null;
  allergies: string | null;
  medical_notes: string | null;
  registered_at: string;
  is_active: boolean;
  created_at: string;
}

export interface DashboardCards {
  appointments_today: number;
  patients_today: number;
  waiting_patients: number;
  completed_consultations: number;
  new_leads: number;
  followups_due: number;
  collection_today: string;
  outstanding_amount: string;
  expenses_today: string;
}

export interface DashboardSummary {
  cards: DashboardCards;
  as_of: string;
  branch_scope: number[] | null;
}

export interface TrendPoint {
  day: string;
  total: number | string;
}

export interface DashboardCharts {
  appointment_trend: TrendPoint[];
  patient_trend: TrendPoint[];
  revenue_trend: TrendPoint[];
  payment_methods: { method: string; total: string; count: number }[];
  lead_funnel: { status: string; total: number }[];
  from: string;
  days: number;
}

export interface AuditLogEntry {
  id: number;
  user_id: number | null;
  user_name: string | null;
  action: string;
  entity_type: string | null;
  entity_id: number | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  ip: string | null;
  created_at: string;
}

export interface PlatformOrganization {
  id: number;
  code: string;
  name: string;
  slug: string;
  status: 'active' | 'trial' | 'suspended' | 'cancelled';
  email: string | null;
  phone: string | null;
  city: string | null;
  plan: string | null;
  plan_code: string | null;
  trial_ends_at: string | null;
  branch_count: number;
  user_count: number;
  patient_count: number;
  created_at: string;
}

export interface PlatformOverview {
  organizations: { total: number; active: number; trial: number; suspended: number };
  clinic_users: number;
  plans: number;
  branches: number;
}
