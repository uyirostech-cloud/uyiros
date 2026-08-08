import { http } from '@/services/http';
import type { AuthUser, SessionPayload } from '@/types/models';

export interface LoginResponse {
  user: AuthUser;
  csrf_token: string;
  expires_at: string;
  must_change_password: boolean;
}

export const authApi = {
  login: (email: string, password: string) =>
    http.post<LoginResponse>('/auth/login', { email, password }),

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
