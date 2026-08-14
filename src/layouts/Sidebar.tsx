import { NavLink } from 'react-router-dom';
import { useAuth } from '@/hooks/useAuth';
import { navigation } from '@/config/navigation';
import { cn } from '@/components/ui/cn';
import { MobileNavIcon } from './MobileNavIcon';

export function Sidebar({ mobileOpen, onCloseMobile }: { mobileOpen: boolean; onCloseMobile: () => void }) {
  const { canAny } = useAuth();

  const visibleSections = navigation
    .map((section) => ({
      ...section,
      items: section.items.filter((item) =>
        canAny(item.permissionAny ?? (item.permission ? [item.permission] : []))),
    }))
    .filter((section) => section.items.length > 0);

  const allVisibleItems = visibleSections.flatMap((section) => section.items);

  const desktopContent = (
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
      <aside className="hidden w-60 shrink-0 border-r border-ink-200 bg-white md:block">{desktopContent}</aside>
      {mobileOpen && (
        <div className="fixed inset-0 z-40 md:hidden">
          <button
            type="button"
            aria-label="Close menu"
            className="absolute inset-0 bg-ink-900/40"
            onClick={onCloseMobile}
          />
          <div className="absolute inset-x-4 top-16 flex max-h-[calc(100vh-6rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-overlay">
            <div className="flex h-12 shrink-0 items-center justify-between border-b border-ink-100 px-4">
              <span className="text-sm font-semibold text-ink-900">Menu</span>
              <button
                type="button"
                aria-label="Close menu"
                onClick={onCloseMobile}
                className="rounded-lg p-1 text-ink-500 hover:bg-ink-100"
              >
                <svg viewBox="0 0 24 24" fill="none" className="h-5 w-5" stroke="currentColor" strokeWidth="1.8">
                  <path d="M6 6l12 12M18 6L6 18" strokeLinecap="round" />
                </svg>
              </button>
            </div>
            <div className="grid grid-cols-4 gap-x-2 gap-y-3 overflow-y-auto p-3" style={{ maxHeight: '19rem' }}>
              {allVisibleItems.map((item) => (
                <NavLink
                  key={item.path}
                  to={item.path}
                  end={item.path === '/'}
                  onClick={onCloseMobile}
                  className={({ isActive }) =>
                    cn(
                      'flex flex-col items-center gap-1.5 rounded-xl px-1 py-2 text-center',
                      isActive ? 'text-brand-700' : 'text-ink-600',
                    )
                  }
                >
                  {({ isActive }) => (
                    <>
                      <span
                        className={cn(
                          'flex h-11 w-11 items-center justify-center rounded-xl',
                          isActive ? 'bg-brand-50 text-brand-700' : 'bg-ink-100 text-ink-600',
                        )}
                      >
                        <MobileNavIcon icon={item.icon ?? 'settings'} className="h-5 w-5" />
                      </span>
                      <span className="text-[11px] font-medium leading-tight">{item.label}</span>
                    </>
                  )}
                </NavLink>
              ))}
            </div>
          </div>
        </div>
      )}
    </>
  );
}
