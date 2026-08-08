import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Card, CardBody } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Tabs } from '@/components/ui/Tabs';
import { ErrorState } from '@/components/ui/States';
import { useAuth } from '@/hooks/useAuth';
import { patientsApi } from '@/services/api';
import { formatDate } from '@/utils/format';
import { initials } from '@/utils/format';
import { PatientFormModal } from './PatientFormModal';
import { ComingSoon } from '@/features/shared/ComingSoon';

const TABS = [
  { key: 'overview', label: 'Overview' },
  { key: 'appointments', label: 'Appointments' },
  { key: 'consultations', label: 'Consultations' },
  { key: 'prescriptions', label: 'Prescriptions' },
  { key: 'vitals', label: 'Vitals' },
  { key: 'invoices', label: 'Invoices' },
  { key: 'payments', label: 'Payments' },
  { key: 'documents', label: 'Documents' },
  { key: 'timeline', label: 'Timeline' },
];

export default function PatientDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { can } = useAuth();
  const [tab, setTab] = useState('overview');
  const [editOpen, setEditOpen] = useState(false);

  const query = useQuery({
    queryKey: ['patient', id],
    queryFn: () => patientsApi.get(Number(id)),
    enabled: Boolean(id),
  });

  if (query.isLoading) {
    return <div className="h-64 animate-pulse rounded-xl bg-ink-100" />;
  }
  if (query.isError || !query.data) {
    return (
      <ErrorState
        title="Patient not found"
        message="It may have been removed, or it belongs to a different clinic."
        onRetry={() => navigate('/app/patients')}
      />
    );
  }

  const patient = query.data;

  return (
    <div>
      <button
        type="button"
        onClick={() => navigate('/app/patients')}
        className="mb-3 text-sm font-medium text-ink-500 hover:text-ink-800"
      >
        ← Back to patients
      </button>

      <Card>
        <CardBody className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-start gap-4">
            <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-brand-100 text-lg font-semibold text-brand-800">
              {initials(patient.full_name)}
            </span>
            <div>
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-lg font-semibold text-ink-900">{patient.full_name}</h1>
                <Badge tone={patient.is_active ? 'success' : 'neutral'}>
                  {patient.is_active ? 'Active' : 'Inactive'}
                </Badge>
              </div>
              <p className="mt-0.5 text-sm text-ink-500">
                {patient.patient_number} · {patient.gender ?? 'Unspecified'}
                {patient.age_years ? `, ${patient.age_years} years` : ''}
                {patient.blood_group ? ` · ${patient.blood_group}` : ''}
              </p>
              <p className="mt-1 text-sm text-ink-700">{patient.phone}{patient.email ? ` · ${patient.email}` : ''}</p>
            </div>
          </div>
          {can('patient.update') && (
            <Button variant="secondary" size="sm" onClick={() => setEditOpen(true)}>Edit patient</Button>
          )}
        </CardBody>
      </Card>

      {patient.allergies && (
        <div className="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">
          <strong>Allergies:</strong> {patient.allergies}
        </div>
      )}

      <Card className="mt-4">
        <Tabs tabs={TABS} active={tab} onChange={setTab} />
        <CardBody>
          {tab === 'overview' && (
            <dl className="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
              <Detail label="Date of birth" value={patient.dob ? formatDate(patient.dob) : '—'} />
              <Detail label="Alternate phone" value={patient.alt_phone ?? '—'} />
              <Detail label="Address" value={[patient.address, patient.city, patient.state, patient.pin].filter(Boolean).join(', ') || '—'} />
              <Detail label="Occupation" value={patient.occupation ?? '—'} />
              <Detail label="Source" value={patient.source ?? '—'} />
              <Detail label="Emergency contact" value={patient.emergency_contact_name
                ? `${patient.emergency_contact_name} (${patient.emergency_contact_phone ?? 'no phone'})` : '—'} />
              <Detail label="Registered" value={formatDate(patient.registered_at)} />
              <Detail label="Medical notes" value={patient.medical_notes ?? '—'} full />
            </dl>
          )}
          {tab !== 'overview' && (
            <div className="-mx-5 -mb-5">
              <ComingSoon
                title={TABS.find((t) => t.key === tab)?.label ?? ''}
                phase={
                  tab === 'appointments' ? 'Phase 2 — Patient Flow'
                    : tab === 'consultations' || tab === 'vitals' || tab === 'prescriptions' ? 'Phase 3 — Clinical'
                      : tab === 'invoices' || tab === 'payments' ? 'Phase 4 — Billing'
                        : 'a future phase'
                }
              />
            </div>
          )}
        </CardBody>
      </Card>

      <PatientFormModal
        open={editOpen}
        onClose={() => setEditOpen(false)}
        patient={patient}
        onSaved={() => {
          setEditOpen(false);
          void queryClient.invalidateQueries({ queryKey: ['patient', id] });
        }}
      />
    </div>
  );
}

function Detail({ label, value, full }: { label: string; value: string; full?: boolean }) {
  return (
    <div className={full ? 'sm:col-span-2' : undefined}>
      <dt className="text-xs font-medium uppercase tracking-wide text-ink-400">{label}</dt>
      <dd className="mt-0.5 text-sm text-ink-800">{value}</dd>
    </div>
  );
}
