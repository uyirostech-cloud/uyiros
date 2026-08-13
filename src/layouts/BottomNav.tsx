import { NavLink } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import { mobileTabCandidates } from '@/config/navigation';
import { cn } from '@/components/ui/cn';
import { MobileNavIcon, MoreIcon } from './MobileNavIcon';

export function BottomNav({ onOpenMore }: { onOpenMore: () => void }) {
  const { canAny } = useAuth();

  const tabs = mobileTabCandidates
    .filter((item) => canAny(item.permissionAny ?? (item.permission ? [item.permission] : [])))
    .slice(0, 5);

  return (
    <nav
      className="fixed inset-x-0 bottom-0 z-30 flex border-t border-ink-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden"
      aria-label="Primary"
    >
      {tabs.map((item) => (
        <NavLink
          key={item.path}
          to={item.path}
          end={item.path === '/app'}
          className={({ isActive }) =>
            cn(
              'flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px] font-medium',
              isActive ? 'text-brand-700' : 'text-ink-500',
            )
          }
        >
          <MobileNavIcon icon={item.icon} className="h-5 w-5" />
          {item.label}
        </NavLink>
      ))}
      <button
        type="button"
        onClick={onOpenMore}
        className="flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px] font-medium text-ink-500"
      >
        <MoreIcon className="h-5 w-5" />
        More
      </button>
    </nav>
  );
}
