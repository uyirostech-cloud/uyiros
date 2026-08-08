import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { AuthLayout } from '@/layouts/AuthLayout';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { authApi } from '@/services/api/auth';

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [sent, setSent] = useState(false);
  const [loading, setLoading] = useState(false);
  const [devToken, setDevToken] = useState<string | null>(null);

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setLoading(true);
    try {
      const result = await authApi.forgotPassword(email.trim());
      setSent(true);
      setDevToken(result.dev_reset_token ?? null);
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthLayout>
      {sent ? (
        <div>
          <h2 className="mb-2 text-base font-semibold text-ink-900">Check your email</h2>
          <p className="text-sm text-ink-600">
            If <strong>{email}</strong> is registered, a reset link has been sent.
          </p>
          {devToken && (
            <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
              Development mode — email delivery is not configured. Use this link:
              <br />
              <Link className="font-mono underline" to={`/reset-password?token=${devToken}`}>
                /reset-password?token={devToken}
              </Link>
            </div>
          )}
          <Link to="/login" className="mt-4 inline-block text-sm font-medium text-brand-700 hover:underline">
            Back to sign in
          </Link>
        </div>
      ) : (
        <form onSubmit={onSubmit} noValidate>
          <h2 className="mb-1 text-base font-semibold text-ink-900">Reset your password</h2>
          <p className="mb-4 text-sm text-ink-500">We'll send a reset link to your email.</p>
          <Input
            label="Email address"
            type="email"
            required
            value={email}
            onChange={(event) => setEmail(event.target.value)}
          />
          <Button type="submit" className="mt-5 w-full" loading={loading}>
            Send reset link
          </Button>
          <Link to="/login" className="mt-4 block text-center text-sm font-medium text-brand-700 hover:underline">
            Back to sign in
          </Link>
        </form>
      )}
    </AuthLayout>
  );
}
