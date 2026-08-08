import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';
import { cn } from './cn';

type ToastTone = 'success' | 'error' | 'info';

interface Toast {
  id: number;
  tone: ToastTone;
  message: string;
}

interface ToastContextValue {
  push: (message: string, tone?: ToastTone) => void;
  success: (message: string) => void;
  error: (message: string) => void;
}

const ToastContext = createContext<ToastContextValue | null>(null);

const tones: Record<ToastTone, string> = {
  success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
  error: 'border-red-200 bg-red-50 text-red-900',
  info: 'border-ink-200 bg-white text-ink-900',
};

let nextId = 1;

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);

  const remove = useCallback((id: number) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const push = useCallback((message: string, tone: ToastTone = 'info') => {
    const id = nextId++;
    setToasts((current) => [...current, { id, tone, message }]);
    window.setTimeout(() => remove(id), tone === 'error' ? 6000 : 4000);
  }, [remove]);

  const value = useMemo<ToastContextValue>(() => ({
    push,
    success: (message: string) => push(message, 'success'),
    error: (message: string) => push(message, 'error'),
  }), [push]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div
        className="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:inset-x-auto sm:right-0 sm:top-0 sm:items-end"
        role="status"
        aria-live="polite"
      >
        {toasts.map((toast) => (
          <div
            key={toast.id}
            className={cn(
              'pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-overlay',
              tones[toast.tone],
            )}
          >
            <span className="flex-1">{toast.message}</span>
            <button
              type="button"
              onClick={() => remove(toast.id)}
              aria-label="Dismiss"
              className="-mr-1 -mt-0.5 rounded p-1 opacity-60 hover:opacity-100"
            >
              <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4" stroke="currentColor" strokeWidth="2">
                <path d="M6 6l12 12M18 6L6 18" strokeLinecap="round" />
              </svg>
            </button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}

export function useToast(): ToastContextValue {
  const context = useContext(ToastContext);
  if (!context) throw new Error('useToast must be used inside ToastProvider');
  return context;
}
