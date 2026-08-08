import type { ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import { EmptyState } from '@/components/ui/States';

/**
 * Route guards are UX only — every request they gate is re-checked by the API's permission
 * middleware regardless. This just avoids flashing a screen the user cannot use.
 */
export function RequireAuth({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth();
  const location = useLocation();

  if (loading) return <FullScreenSpinner />;
  if (!user) return <Navigate to="/login" state={{ from: location.pathname }} replace />;
  if (user.is_platform_admin && !location.pathname.startsWith('/platform')) {
    return <Navigate to="/platform" replace />;
  }
  if (!user.is_platform_admin && location.pathname.startsWith('/platform')) {
    return <Navigate to="/app" replace />;
  }
  return <>{children}</>;
}

/**
 * The bare domain root. Signed out -> the marketing/landing page. Signed in -> straight into
 * the right console for that account, so nobody who is already authenticated ever sees the
 * landing page's "Sign in" / "Start free trial" buttons.
 */
export function RootRoute({ landing }: { landing: ReactNode }) {
  const { user, loading } = useAuth();
  if (loading) return <FullScreenSpinner />;
  if (user?.is_platform_admin) return <Navigate to="/platform" replace />;
  if (user) return <Navigate to="/app" replace />;
  return <>{landing}</>;
}

export function RequirePermission({ permission, children }: { permission: string; children: ReactNode }) {
  const { can } = useAuth();
  if (!can(permission)) {
    return (
      <EmptyState
        title="You don't have access to this page"
        description="Ask your clinic administrator if you believe this is a mistake."
      />
    );
  }
  return <>{children}</>;
}

export function FullScreenSpinner() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-ink-50">
      <span
        aria-label="Loading"
        className="h-6 w-6 animate-spin rounded-full border-2 border-brand-600 border-t-transparent"
      />
    </div>
  );
}
