import { useState, type FormEvent } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { AuthLayout } from '@/layouts/AuthLayout';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useAuth } from '@/hooks/useAuth';
import { ApiError } from '@/types/api';

export default function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation() as { state?: { from?: string } };

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const { mustChangePassword } = await login(email.trim(), password);
      navigate(mustChangePassword ? '/app/account/change-password' : location.state?.from ?? '/', {
        replace: true,
      });
    } catch (cause) {
      if (cause instanceof ApiError) {
        setError(
          cause.status === 429
            ? cause.message
            : cause.status === 403
              ? cause.message
              : 'Invalid email or password.',
        );
      } else {
        setError('Could not sign in. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthLayout>
      <form onSubmit={onSubmit} noValidate>
        <h2 className="mb-4 text-base font-semibold text-ink-900">Sign in</h2>
        {error && (
          <div role="alert" className="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {error}
          </div>
        )}
        <div className="space-y-4">
          <Input
            label="Email address"
            type="email"
            autoComplete="username"
            required
            value={email}
            onChange={(event) => setEmail(event.target.value)}
          />
          <Input
            label="Password"
            type="password"
            autoComplete="current-password"
            required
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />
        </div>
        <div className="mt-2 text-right">
          <a href="/forgot-password" className="text-xs font-medium text-brand-700 hover:underline">
            Forgot password?
          </a>
        </div>
        <Button type="submit" className="mt-5 w-full" loading={loading}>
          Sign in
        </Button>
      </form>
    </AuthLayout>
  );
}
