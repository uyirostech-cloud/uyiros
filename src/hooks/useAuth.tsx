import {
  createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode,
} from 'react';
import { authApi } from '@/services/api/auth';
import { setUnauthenticatedHandler } from '@/services/http';
import type { AuthUser, BranchSummary } from '@/types/models';

interface AuthContextValue {
  user: AuthUser | null;
  branches: BranchSummary[];
  loading: boolean;
  activeBranchId: number | '';
  setActiveBranchId: (id: number | '') => void;
  login: (email: string, password: string) => Promise<{ mustChangePassword: boolean }>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
  can: (permission: string) => boolean;
  canAny: (permissions: string[]) => boolean;
}

const AuthContext = createContext<AuthContextValue | null>(null);
const BRANCH_STORAGE_KEY = 'clinicflow.active_branch';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [branches, setBranches] = useState<BranchSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeBranchId, setActiveBranchIdState] = useState<number | ''>(() => {
    const stored = window.localStorage.getItem(BRANCH_STORAGE_KEY);
    return stored ? Number(stored) : '';
  });

  const setActiveBranchId = useCallback((id: number | '') => {
    setActiveBranchIdState(id);
    if (id === '') {
      window.localStorage.removeItem(BRANCH_STORAGE_KEY);
    } else {
      window.localStorage.setItem(BRANCH_STORAGE_KEY, String(id));
    }
  }, []);

  const load = useCallback(async () => {
    try {
      const session = await authApi.me();
      setUser(session.user);
      setBranches(session.branches);
    } catch {
      setUser(null);
      setBranches([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    setUnauthenticatedHandler(() => {
      setUser(null);
      setBranches([]);
    });
    return () => setUnauthenticatedHandler(null);
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const result = await authApi.login(email, password);
    setUser(result.user);
    await load();
    return { mustChangePassword: result.must_change_password };
  }, [load]);

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } finally {
      setUser(null);
      setBranches([]);
    }
  }, []);

  const can = useCallback(
    (permission: string) => user?.permissions.includes(permission) ?? false,
    [user],
  );
  const canAny = useCallback(
    (permissions: string[]) => permissions.some((p) => user?.permissions.includes(p)),
    [user],
  );

  const value = useMemo<AuthContextValue>(() => ({
    user, branches, loading, activeBranchId, setActiveBranchId,
    login, logout, refresh: load, can, canAny,
  }), [user, branches, loading, activeBranchId, setActiveBranchId, login, logout, load, can, canAny]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used inside AuthProvider');
  return context;
}
