import { cn } from './cn';

export interface TabItem {
  key: string;
  label: string;
  count?: number;
}

export function Tabs({
  tabs, active, onChange, className,
}: {
  tabs: TabItem[];
  active: string;
  onChange: (key: string) => void;
  className?: string;
}) {
  return (
    <div className={cn('overflow-x-auto border-b border-ink-200', className)}>
      <nav className="flex min-w-max gap-1" role="tablist">
        {tabs.map((tab) => {
          const isActive = tab.key === active;
          return (
            <button
              key={tab.key}
              type="button"
              role="tab"
              aria-selected={isActive}
              onClick={() => onChange(tab.key)}
              className={cn(
                'relative whitespace-nowrap px-3.5 py-2.5 text-sm font-medium transition-colors',
                isActive ? 'text-brand-700' : 'text-ink-500 hover:text-ink-800',
              )}
            >
              {tab.label}
              {tab.count !== undefined && (
                <span className="ml-1.5 rounded-full bg-ink-100 px-1.5 py-0.5 text-xs text-ink-600">
                  {tab.count}
                </span>
              )}
              {isActive && <span className="absolute inset-x-2 -bottom-px h-0.5 rounded-full bg-brand-600" />}
            </button>
          );
        })}
      </nav>
    </div>
  );
}
