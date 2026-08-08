import { http } from '@/services/http';
import type { AuthUser, SessionPayload } from '@/types/models';

export interface LoginResponse {
  user: AuthUser;
  csrf_token: string;
  expires_at: string;
  must_change_password: boolean;
}

export interface SignupPayload {
  organization_name: string;
  admin_name: string;
  admin_email: string;
  admin_password: string;
  phone?: string;
  city?: string;
  state?: string;
  plan_code?: string;
}

export interface SignupResponse extends LoginResponse {
  trial_ends_at: string;
}

export const authApi = {
  login: (email: string, password: string) =>
    http.post<LoginResponse>('/auth/login', { email, password }),

  signup: (payload: SignupPayload) => http.post<SignupResponse>('/auth/signup', payload),

  me: () => http.get<SessionPayload>('/auth/me'),

  logout: () => http.post<{ message: string }>('/auth/logout'),

  changePassword: (current_password: string, password: string, password_confirmation: string) =>
    http.post<{ message: string }>('/auth/change-password', {
      current_password,
      password,
      password_confirmation,
    }),

  forgotPassword: (email: string) =>
    http.post<{ message: string; dev_reset_token?: string }>('/auth/forgot-password', { email }),

  resetPassword: (token: string, password: string, password_confirmation: string) =>
    http.post<{ message: string }>('/auth/reset-password', { token, password, password_confirmation }),
};
