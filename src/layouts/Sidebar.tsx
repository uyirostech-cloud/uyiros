import { NavLink } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import { navigation } from '@/config/navigation';
import { cn } from '@/components/ui/cn';

export function Sidebar({ mobileOpen, onCloseMobile }: { mobileOpen: boolean; onCloseMobile: () => void }) {
  const { canAny } = useAuth();

  const visibleSections = navigation
    .map((section) => ({
      ...section,
      items: section.items.filter((item) =>
        canAny(item.permissionAny ?? (item.permission ? [item.permission] : []))),
    }))
    .filter((section) => section.items.length > 0);

  const content = (
    <nav className="flex h-full flex-col gap-1 overflow-y-auto px-3 py-4">
      {visibleSections.map((section, index) => (
        <div key={section.label ?? `s${index}`} className={index > 0 ? 'mt-3' : undefined}>
          {section.label && (
            <p className="px-3 pb-1.5 pt-2 text-[11px] font-semibold uppercase tracking-wider text-ink-400">
              {section.label}
            </p>
          )}
          {section.items.map((item) => (
            <NavLink
              key={item.path}
              to={item.path}
              end={item.path === '/'}
              onClick={onCloseMobile}
              className={({ isActive }) =>
                cn(
                  'flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                  isActive ? 'bg-brand-50 text-brand-800' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900',
                )
              }
            >
              {item.label}
            </NavLink>
          ))}
        </div>
      ))}
    </nav>
  );

  return (
    <>
      <aside className="hidden w-60 shrink-0 border-r border-ink-200 bg-white md:block">{content}</aside>
      {mobileOpen && (
        <div className="fixed inset-0 z-40 md:hidden">
          <button
            type="button"
            aria-label="Close menu"
            className="absolute inset-0 bg-ink-900/40"
            onClick={onCloseMobile}
          />
          <aside className="absolute inset-y-0 left-0 w-64 bg-white shadow-overlay">{content}</aside>
        </div>
      )}
    </>
  );
}
