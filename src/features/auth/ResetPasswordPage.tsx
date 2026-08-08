import { useState, type FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { AuthLayout } from '@/layouts/AuthLayout';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { authApi } from '@/services/api/auth';
import { ApiError } from '@/types/api';
import { passwordIssues } from '@/utils/validation';

export default function ResetPasswordPage() {
  const [params] = useSearchParams();
  const navigate = useNavigate();
  const token = params.get('token') ?? '';

  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setError(null);
    if (password !== confirmation) {
      setError('Passwords do not match.');
      return;
    }
    const issues = passwordIssues(password);
    if (issues.length > 0) {
      setError(`Password must contain ${issues.join(', ')}.`);
      return;
    }
    setLoading(true);
    try {
      await authApi.resetPassword(token, password, confirmation);
      navigate('/login', { replace: true, state: { resetDone: true } });
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : 'Could not reset your password.');
    } finally {
      setLoading(false);
    }
  };

  if (!token) {
    return (
      <AuthLayout>
        <p className="text-sm text-ink-600">This reset link is missing its token.</p>
        <Link to="/forgot-password" className="mt-3 inline-block text-sm font-medium text-brand-700 hover:underline">
          Request a new link
        </Link>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout>
      <form onSubmit={onSubmit} noValidate>
        <h2 className="mb-4 text-base font-semibold text-ink-900">Choose a new password</h2>
        {error && (
          <div role="alert" className="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {error}
          </div>
        )}
        <div className="space-y-4">
          <Input
            label="New password"
            type="password"
            required
            hint="At least 10 characters, with a letter and a number."
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />
          <Input
            label="Confirm new password"
            type="password"
            required
            value={confirmation}
            onChange={(event) => setConfirmation(event.target.value)}
          />
        </div>
        <Button type="submit" className="mt-5 w-full" loading={loading}>
          Reset password
        </Button>
      </form>
    </AuthLayout>
  );
}
