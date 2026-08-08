import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { PublicNav } from './PublicNav';
import { PublicFooter } from './PublicFooter';
import { Button } from '@/components/ui/Button';
import { publicApi } from '@/services/api';
import { formatMoney } from '@/utils/format';

const WORKFLOW_STEPS = [
  'Lead', 'Patient', 'Appointment', 'Check-in', 'Queue',
  'Consultation', 'Prescription', 'Invoice', 'Payment', 'Reports',
];

const FEATURES: { title: string; description: string }[] = [
  { title: 'CRM & lead conversion', description: 'Capture leads from every channel, track follow-ups, and convert straight into a patient and a booked appointment — with duplicate-phone detection built in.' },
  { title: 'Appointments & queue', description: 'Doctor-wise scheduling with automatic overlap prevention, live token queue, and a real-time waiting list for reception and doctors alike.' },
  { title: 'Clinical records', description: 'Vitals, consultation notes, diagnoses and prescriptions in one connected chart — full history preserved, nothing silently overwritten.' },
  { title: 'Billing & payments', description: 'Itemized invoices with tax and discount, partial and split payments across cash/UPI/card, and refunds that always reconcile to the last rupee.' },
  { title: 'Finance & daily closing', description: 'Live collection and outstanding tracking, expense approvals, and end-of-day cash reconciliation with expected-vs-actual variance.' },
  { title: 'Multi-branch, role-based', description: 'One clinic, many branches, one login per staff member — access scoped by branch and by a real permission system, not just a hidden sidebar link.' },
];

export default function LandingPage() {
  const plansQuery = useQuery({ queryKey: ['public-plans'], queryFn: publicApi.plans });

  return (
    <div className="min-h-screen bg-white">
      <PublicNav />

      {/* Hero */}
      <section className="mx-auto max-w-6xl px-4 pb-16 pt-16 sm:px-6 sm:pt-24">
        <div className="mx-auto max-w-3xl text-center">
          <span className="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-800 ring-1 ring-inset ring-brand-200">
            Built for general practice clinics
          </span>
          <h1 className="mt-5 text-4xl font-semibold tracking-tight text-ink-900 sm:text-5xl">
            Run your whole clinic from one connected app
          </h1>
          <p className="mt-4 text-lg text-ink-600">
            ClinicFlow takes a patient from lead to payment — appointments, queue, consultations,
            prescriptions, billing and finance all reading from the same record. No disconnected
            spreadsheets, no re-typing the same patient three times.
          </p>
          <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <Link to="/signup">
              <Button size="lg" className="w-full sm:w-auto">Start your free 14-day trial</Button>
            </Link>
            <a href="#pricing">
              <Button size="lg" variant="secondary" className="w-full sm:w-auto">See pricing</Button>
            </a>
          </div>
          <p className="mt-3 text-xs text-ink-400">No credit card required. Set up in under two minutes.</p>
        </div>
      </section>

      {/* Connected workflow */}
      <section id="workflow" className="border-y border-ink-200 bg-ink-50/60 py-14">
        <div className="mx-auto max-w-6xl px-4 sm:px-6">
          <h2 className="text-center text-sm font-semibold uppercase tracking-wide text-brand-700">
            One record, start to finish
          </h2>
          <p className="mx-auto mt-2 max-w-2xl text-center text-ink-600">
            Every module reads and writes the same connected data — so your dashboard, your
            reports and your front desk always agree.
          </p>
          <div className="mt-8 flex flex-wrap items-center justify-center gap-2">
            {WORKFLOW_STEPS.map((step, index) => (
              <div key={step} className="flex items-center gap-2">
                <span className="rounded-full border border-ink-200 bg-white px-3.5 py-1.5 text-sm font-medium text-ink-700 shadow-card">
                  {step}
                </span>
                {index < WORKFLOW_STEPS.length - 1 && (
                  <span aria-hidden className="text-ink-300">&rarr;</span>
                )}
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Features */}
      <section id="features" className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div className="mx-auto max-w-2xl text-center">
          <h2 className="text-3xl font-semibold text-ink-900">Everything the front desk, the doctor and the accountant need</h2>
          <p className="mt-3 text-ink-600">Twenty-plus modules, one permission system, zero disconnected screens.</p>
        </div>
        <div className="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {FEATURES.map((feature) => (
            <div key={feature.title} className="card p-5">
              <h3 className="text-sm font-semibold text-ink-900">{feature.title}</h3>
              <p className="mt-1.5 text-sm text-ink-600">{feature.description}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Pricing */}
      <section id="pricing" className="border-t border-ink-200 bg-ink-50/60 py-16">
        <div className="mx-auto max-w-6xl px-4 sm:px-6">
          <div className="mx-auto max-w-2xl text-center">
            <h2 className="text-3xl font-semibold text-ink-900">Simple, per-clinic pricing</h2>
            <p className="mt-3 text-ink-600">Every plan includes a 14-day free trial. Cancel any time.</p>
          </div>

          {plansQuery.isLoading ? (
            <div className="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-3">
              {[0, 1, 2].map((i) => <div key={i} className="h-72 animate-pulse rounded-xl bg-ink-100" />)}
            </div>
          ) : plansQuery.isError ? (
            <p className="mt-10 text-center text-sm text-ink-500">Could not load pricing right now — please try again shortly.</p>
          ) : (
            <div className="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-3">
              {(plansQuery.data ?? []).map((plan, index) => {
                const featured = index === 1;
                return (
                  <div
                    key={plan.code}
                    className={
                      featured
                        ? 'relative rounded-xl border-2 border-brand-600 bg-white p-6 shadow-overlay'
                        : 'relative rounded-xl border border-ink-200 bg-white p-6 shadow-card'
                    }
                  >
                    {featured && (
                      <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-600 px-3 py-1 text-xs font-medium text-white">
                        Most popular
                      </span>
                    )}
                    <h3 className="text-base font-semibold text-ink-900">{plan.name}</h3>
                    <p className="mt-1 text-sm text-ink-500">{plan.description}</p>
                    <p className="mt-4 flex items-baseline gap-1">
                      <span className="text-3xl font-semibold text-ink-900">{formatMoney(plan.price_monthly)}</span>
                      <span className="text-sm text-ink-500">/ month</span>
                    </p>
                    <ul className="mt-5 space-y-2 text-sm text-ink-600">
                      <li>&#10003; Up to {plan.max_branches} {plan.max_branches === 1 ? 'branch' : 'branches'}</li>
                      <li>&#10003; Up to {plan.max_users} staff users</li>
                      <li>&#10003; All clinical, billing and finance modules</li>
                      <li>&#10003; Role-based access control</li>
                    </ul>
                    <Link to={`/signup?plan=${plan.code}`} className="mt-6 block">
                      <Button className="w-full" variant={featured ? 'primary' : 'secondary'}>
                        Start free trial
                      </Button>
                    </Link>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </section>

      {/* Security */}
      <section id="security" className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div className="grid grid-cols-1 items-center gap-10 lg:grid-cols-2">
          <div>
            <h2 className="text-3xl font-semibold text-ink-900">Your clinic&apos;s data stays your clinic&apos;s</h2>
            <p className="mt-3 text-ink-600">
              ClinicFlow is multi-tenant by design, and tenant isolation is enforced in the
              backend on every request — never trusted from the browser.
            </p>
            <ul className="mt-6 space-y-3 text-sm text-ink-700">
              <li className="flex gap-2"><span className="text-brand-600">&#10003;</span> Every query is scoped to your organization by the authenticated session, not a value the browser can change.</li>
              <li className="flex gap-2"><span className="text-brand-600">&#10003;</span> Passwords are hashed with Argon2id; sessions are HttpOnly, CSRF-protected, and revoked instantly on sign-out.</li>
              <li className="flex gap-2"><span className="text-brand-600">&#10003;</span> Every critical action — from a cancelled invoice to a changed permission — is written to an audit log.</li>
              <li className="flex gap-2"><span className="text-brand-600">&#10003;</span> Role- and branch-based access control, enforced on the server for every single request.</li>
            </ul>
          </div>
          <div className="card p-6">
            <p className="text-xs font-semibold uppercase tracking-wide text-ink-400">Tenant-scoped query, always</p>
            <pre className="mt-3 overflow-x-auto rounded-lg bg-ink-900 p-4 text-xs leading-relaxed text-ink-100">
{`SELECT *
FROM patients
WHERE id = ?
  AND organization_id = ?  -- from session,
                           -- never the request`}
            </pre>
          </div>
        </div>
      </section>

      {/* Final CTA */}
      <section className="border-t border-ink-200 bg-brand-700 py-16">
        <div className="mx-auto max-w-3xl px-4 text-center sm:px-6">
          <h2 className="text-3xl font-semibold text-white">Ready to connect your clinic?</h2>
          <p className="mt-3 text-brand-100">Start your 14-day free trial — no credit card, no setup call needed.</p>
          <Link to="/signup" className="mt-7 inline-block">
            <Button size="lg" variant="secondary" className="bg-white text-brand-800 hover:bg-brand-50">
              Start free trial
            </Button>
          </Link>
        </div>
      </section>

      <PublicFooter />
    </div>
  );
}
