import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { platformNavigation } from '@/config/navigation';
import { useAuth } from '@/hooks/useAuth';
import { cn } from '@/components/ui/cn';

export default function PlatformLayout() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  return (
    <div className="flex min-h-screen bg-ink-50">
      <aside className="hidden w-56 shrink-0 border-r border-ink-200 bg-ink-900 text-white md:block">
        <div className="flex h-14 items-center gap-2 border-b border-white/10 px-4 font-semibold">
          <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-500 text-xs">CF</span>
          Platform
        </div>
        <nav className="flex flex-col gap-1 p-3">
          {platformNavigation.map((item) => (
            <NavLink
              key={item.path}
              to={item.path}
              end={item.path === '/platform'}
              className={({ isActive }) => cn(
                'rounded-lg px-3 py-2 text-sm font-medium',
                isActive ? 'bg-white/10 text-white' : 'text-ink-300 hover:bg-white/5 hover:text-white',
              )}
            >
              {item.label}
            </NavLink>
          ))}
        </nav>
      </aside>
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-14 items-center justify-between border-b border-ink-200 bg-white px-4 sm:px-6">
          <p className="text-sm text-ink-500">Signed in as <strong className="text-ink-800">{user?.name}</strong></p>
          <button type="button" onClick={handleLogout} className="text-sm font-medium text-red-600 hover:underline">
            Sign out
          </button>
        </header>
        <main className="flex-1 p-4 sm:p-6"><Outlet /></main>
      </div>
    </div>
  );
}
