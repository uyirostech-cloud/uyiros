import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/Button';

export function PublicNav() {
  return (
    <header className="sticky top-0 z-40 border-b border-ink-200 bg-white/90 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-6xl items-center gap-6 px-4 sm:px-6">
        <Link to="/" className="flex items-center gap-2 font-semibold text-ink-900">
          <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-sm text-white">CF</span>
          ClinicFlow
        </Link>
        <nav className="ml-2 hidden items-center gap-6 text-sm font-medium text-ink-600 md:flex">
          <a href="#workflow" className="hover:text-ink-900">How it works</a>
          <a href="#features" className="hover:text-ink-900">Features</a>
          <a href="#pricing" className="hover:text-ink-900">Pricing</a>
          <a href="#security" className="hover:text-ink-900">Security</a>
        </nav>
        <div className="ml-auto flex items-center gap-2">
          <Link to="/login">
            <Button variant="ghost" size="sm">Sign in</Button>
          </Link>
          <Link to="/signup">
            <Button size="sm">Start free trial</Button>
          </Link>
        </div>
      </div>
    </header>
  );
}
