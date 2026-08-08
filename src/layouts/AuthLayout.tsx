import type { ReactNode } from 'react';

export function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-ink-50 px-4 py-10">
      <div className="w-full max-w-sm">
        <div className="mb-8 text-center">
          <div className="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-xl bg-brand-600 text-white">
            <svg viewBox="0 0 24 24" fill="none" className="h-6 w-6" stroke="currentColor" strokeWidth="1.8">
              <path d="M12 3v18M4 8l8-5 8 5M4 8v11a1 1 0 0 0 1 1h4v-6h6v6h4a1 1 0 0 0 1-1V8"
                    strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </div>
          <h1 className="text-lg font-semibold text-ink-900">ClinicFlow</h1>
          <p className="text-sm text-ink-500">Clinic operations, connected</p>
        </div>
        <div className="card p-6">{children}</div>
      </div>
    </div>
  );
}
