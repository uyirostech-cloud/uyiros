import { Link } from 'react-router-dom';

export function PublicFooter() {
  return (
    <footer className="border-t border-ink-200 bg-white">
      <div className="mx-auto flex max-w-6xl flex-col items-center gap-4 px-4 py-8 text-sm text-ink-500 sm:flex-row sm:justify-between sm:px-6">
        <div className="flex items-center gap-2 font-medium text-ink-700">
          <span className="flex h-6 w-6 items-center justify-center rounded-md bg-brand-600 text-[11px] text-white">CF</span>
          ClinicFlow
        </div>
        <p>&copy; {new Date().getFullYear()} ClinicFlow. Built for general practice clinics.</p>
        <div className="flex items-center gap-4">
          <a href="#pricing" className="hover:text-ink-800">Pricing</a>
          <Link to="/login" className="hover:text-ink-800">Sign in</Link>
          <Link to="/signup" className="hover:text-ink-800">Start free trial</Link>
        </div>
      </div>
    </footer>
  );
}
