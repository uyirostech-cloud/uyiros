import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { authApi } from '@/services/api/auth';
import { useAuth } from '@/hooks/useAuth';
import { ApiError } from '@/types/api';
import { passwordIssues } from '@/utils/validation';

export default function ChangePasswordPage() {
  const navigate = useNavigate();
  const { logout } = useAuth();
  const [current, setCurrent] = useState('');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setError(null);
    if (password !== confirmation) {
      setError('New passwords do not match.');
      return;
    }
    const issues = passwordIssues(password);
    if (issues.length > 0) {
      setError(`Password must contain ${issues.join(', ')}.`);
      return;
    }
    setLoading(true);
    try {
      await authApi.changePassword(current, password, confirmation);
      await logout();
      navigate('/login', { replace: true });
    } catch (cause) {
      setError(cause instanceof ApiError ? cause.message : 'Could not update your password.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="mx-auto max-w-md">
      <Card>
        <CardHeader title="Change password" subtitle="You will be signed out afterwards and need to sign in again." />
        <CardBody>
          <form onSubmit={onSubmit} noValidate className="space-y-4">
            {error && (
              <div role="alert" className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {error}
              </div>
            )}
            <Input label="Current password" type="password" required value={current}
                   onChange={(e) => setCurrent(e.target.value)} />
            <Input label="New password" type="password" required value={password}
                   hint="At least 10 characters, with a letter and a number."
                   onChange={(e) => setPassword(e.target.value)} />
            <Input label="Confirm new password" type="password" required value={confirmation}
                   onChange={(e) => setConfirmation(e.target.value)} />
            <Button type="submit" className="w-full" loading={loading}>Update password</Button>
          </form>
        </CardBody>
      </Card>
    </div>
  );
}
