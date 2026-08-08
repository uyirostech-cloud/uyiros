import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import { initials, titleCase } from '@/utils/format';

export function Topbar({ onOpenMobile }: { onOpenMobile: () => void }) {
  const { user, branches, activeBranchId, setActiveBranchId, logout } = useAuth();
  const navigate = useNavigate();
  const [menuOpen, setMenuOpen] = useState(false);

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  return (
    <header className="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-ink-200 bg-white px-4">
      <button
        type="button"
        onClick={onOpenMobile}
        aria-label="Open menu"
        className="rounded-lg p-2 text-ink-600 hover:bg-ink-100 md:hidden"
      >
        <svg viewBox="0 0 24 24" fill="none" className="h-5 w-5" stroke="currentColor" strokeWidth="1.8">
          <path d="M4 6h16M4 12h16M4 18h16" strokeLinecap="round" />
        </svg>
      </button>

      <div className="flex items-center gap-2 font-semibold text-ink-900">
        <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-600 text-xs text-white">CF</span>
        <span className="hidden sm:inline">{user?.organization_name ?? 'ClinicFlow'}</span>
      </div>

      <div className="ml-auto flex items-center gap-3">
        {branches.length > 1 && (
          <select
            value={activeBranchId}
            onChange={(event) => setActiveBranchId(event.target.value ? Number(event.target.value) : '')}
            className="input-base h-8 w-auto py-0 text-xs"
            aria-label="Active branch"
          >
            <option value="">All branches</option>
            {branches.map((branch) => (
              <option key={branch.id} value={branch.id}>{branch.name}</option>
            ))}
          </select>
        )}

        <div className="relative">
          <button
            type="button"
            onClick={() => setMenuOpen((open) => !open)}
            className="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-ink-100"
          >
            <span className="flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800">
              {user ? initials(user.name) : ''}
            </span>
            <span className="hidden text-left sm:block">
              <span className="block text-sm font-medium leading-tight text-ink-900">{user?.name}</span>
              <span className="block text-xs leading-tight text-ink-500">
                {user?.role ? titleCase(user.role) : ''}
              </span>
            </span>
          </button>
          {menuOpen && (
            <>
              <button
                type="button"
                aria-label="Close menu"
                className="fixed inset-0 z-10 cursor-default"
                onClick={() => setMenuOpen(false)}
              />
              <div className="absolute right-0 z-20 mt-2 w-44 overflow-hidden rounded-lg border border-ink-200 bg-white shadow-overlay">
                <button
                  type="button"
                  onClick={() => { setMenuOpen(false); navigate('/app/account'); }}
                  className="block w-full px-3.5 py-2.5 text-left text-sm text-ink-700 hover:bg-ink-50"
                >
                  Account settings
                </button>
                <button
                  type="button"
                  onClick={handleLogout}
                  className="block w-full border-t border-ink-100 px-3.5 py-2.5 text-left text-sm text-red-600 hover:bg-red-50"
                >
                  Sign out
                </button>
              </div>
            </>
          )}
        </div>
      </div>
    </header>
  );
}
