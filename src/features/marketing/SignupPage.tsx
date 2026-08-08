import { useState, type FormEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { PublicNav } from './PublicNav';
import { PublicFooter } from './PublicFooter';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { cn } from '@/components/ui/cn';
import { useAuth } from '@/hooks/useAuth';
import { publicApi } from '@/services/api';
import { ApiError } from '@/types/api';
import { formatMoney } from '@/utils/format';
import { passwordIssues } from '@/utils/validation';

const emptyForm = {
  organization_name: '', admin_name: '', admin_email: '', admin_password: '',
  phone: '', city: '', state: '',
};

export default function SignupPage() {
  const { signup } = useAuth();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const preselectedPlan = params.get('plan') ?? '';

  const plansQuery = useQuery({ queryKey: ['public-plans'], queryFn: publicApi.plans });
  const [planCode, setPlanCode] = useState(preselectedPlan);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const set = (key: keyof typeof form) => (event: React.ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [key]: event.target.value }));

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setFormError(null);
    setErrors({});

    const issues = passwordIssues(form.admin_password);
    if (issues.length > 0) {
      setErrors({ admin_password: `Password must contain ${issues.join(', ')}.` });
      return;
    }

    setLoading(true);
    try {
      await signup({ ...form, plan_code: planCode || undefined });
      navigate('/app', { replace: true, state: { justSignedUp: true } });
    } catch (cause) {
      if (cause instanceof ApiError) {
        if (cause.isValidation) setErrors(cause.fields);
        else if (cause.status === 429) setFormError(cause.message);
        else setFormError(cause.message || 'Could not create your clinic. Please try again.');
      } else {
        setFormError('Could not create your clinic. Please try again.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-ink-50">
      <PublicNav />

      <div className="mx-auto grid max-w-5xl grid-cols-1 gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_360px] lg:py-16">
        <div className="card p-6 sm:p-8">
          <h1 className="text-xl font-semibold text-ink-900">Start your free trial</h1>
          <p className="mt-1 text-sm text-ink-500">
            14 days free, no credit card required. You&apos;ll be signed in as your clinic&apos;s
            first admin as soon as you submit this form.
          </p>

          <form onSubmit={onSubmit} noValidate className="mt-6 space-y-4">
            {formError && (
              <div role="alert" className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                {formError}
              </div>
            )}

            <Input
              label="Clinic name" required placeholder="e.g. Sunrise Family Clinic"
              value={form.organization_name} error={errors.organization_name}
              onChange={set('organization_name')}
            />
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Input label="City" value={form.city} error={errors.city} onChange={set('city')} />
              <Input label="State" value={form.state} error={errors.state} onChange={set('state')} />
            </div>

            <hr className="border-ink-200" />

            <Input
              label="Your full name" required placeholder="You'll be the Clinic Admin"
              value={form.admin_name} error={errors.admin_name} onChange={set('admin_name')}
            />
            <Input
              label="Work email" type="email" required
              value={form.admin_email} error={errors.admin_email} onChange={set('admin_email')}
            />
            <Input label="Phone" value={form.phone} error={errors.phone} onChange={set('phone')} />
            <Input
              label="Password" type="password" required
              hint="At least 10 characters, with a letter and a number."
              value={form.admin_password} error={errors.admin_password} onChange={set('admin_password')}
            />

            <Button type="submit" className="w-full" loading={loading}>Create my clinic</Button>
            <p className="text-center text-xs text-ink-400">
              Already have an account? <Link to="/login" className="font-medium text-brand-700 hover:underline">Sign in</Link>
            </p>
          </form>
        </div>

        <aside>
          <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-ink-400">Choose a plan</p>
          <div className="space-y-3">
            {plansQuery.isLoading ? (
              [0, 1, 2].map((i) => <div key={i} className="h-24 animate-pulse rounded-xl bg-ink-100" />)
            ) : (
              (plansQuery.data ?? []).map((plan) => (
                <button
                  key={plan.code}
                  type="button"
                  onClick={() => setPlanCode(plan.code)}
                  className={cn(
                    'w-full rounded-xl border p-4 text-left transition-colors',
                    planCode === plan.code
                      ? 'border-brand-600 bg-brand-50 ring-1 ring-brand-600'
                      : 'border-ink-200 bg-white hover:border-brand-300',
                  )}
                >
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-semibold text-ink-900">{plan.name}</span>
                    <span className="text-sm font-semibold text-ink-900">{formatMoney(plan.price_monthly)}/mo</span>
                  </div>
                  <p className="mt-1 text-xs text-ink-500">{plan.description}</p>
                </button>
              ))
            )}
            {!plansQuery.isLoading && (plansQuery.data ?? []).length === 0 && (
              <p className="text-xs text-ink-500">No plans configured — you can pick one later from Settings.</p>
            )}
          </div>
          <div className="card mt-5 p-4 text-xs text-ink-500">
            Your trial starts immediately and runs for 14 days. No card is charged, and you can
            invite your team as soon as you&apos;re in.
          </div>
        </aside>
      </div>

      <PublicFooter />
    </div>
  );
}
