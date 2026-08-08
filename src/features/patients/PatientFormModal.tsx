import { useEffect, useState, type FormEvent } from 'react';
import { Modal } from '@/components/ui/Modal';
import { Button } from '@/components/ui/Button';
import { Input, Select, Textarea } from '@/components/ui/Input';
import { useAuth } from '@/hooks/useAuth';
import { patientsApi } from '@/services/api';
import { ApiError } from '@/types/api';
import { GENDERS, BLOOD_GROUPS, LEAD_SOURCES } from '@/utils/constants';
import type { Patient } from '@/types/models';
import { useToast } from '@/components/ui/Toast';

interface PatientFormModalProps {
  open: boolean;
  onClose: () => void;
  onSaved: (patient: Patient) => void;
  patient?: Patient | null;
}

const emptyForm = {
  full_name: '', phone: '', alt_phone: '', email: '', dob: '', gender: '', blood_group: '',
  address: '', city: '', state: '', pin: '', emergency_contact_name: '', emergency_contact_phone: '',
  occupation: '', source: '', allergies: '', medical_notes: '', branch_id: '',
};

export function PatientFormModal({ open, onClose, onSaved, patient }: PatientFormModalProps) {
  const { branches } = useAuth();
  const toast = useToast();
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [saving, setSaving] = useState(false);
  const [duplicate, setDuplicate] = useState<Patient | null>(null);

  useEffect(() => {
    if (!open) return;
    if (patient) {
      setForm({
        full_name: patient.full_name, phone: patient.phone, alt_phone: patient.alt_phone ?? '',
        email: patient.email ?? '', dob: patient.dob ?? '', gender: patient.gender ?? '',
        blood_group: patient.blood_group ?? '', address: patient.address ?? '', city: patient.city ?? '',
        state: patient.state ?? '', pin: patient.pin ?? '',
        emergency_contact_name: patient.emergency_contact_name ?? '',
        emergency_contact_phone: patient.emergency_contact_phone ?? '', occupation: patient.occupation ?? '',
        source: patient.source ?? '', allergies: patient.allergies ?? '', medical_notes: patient.medical_notes ?? '',
        branch_id: patient.branch_id ? String(patient.branch_id) : '',
      });
    } else {
      setForm({ ...emptyForm, branch_id: branches.length === 1 ? String(branches[0]!.id) : '' });
    }
    setErrors({});
    setDuplicate(null);
  }, [open, patient, branches]);

  const set = (key: keyof typeof form) => (
    event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>,
  ) => setForm((f) => ({ ...f, [key]: event.target.value }));

  const submit = async (event: FormEvent, force = false) => {
    event.preventDefault();
    setErrors({});
    setSaving(true);
    try {
      const payload = {
        ...form,
        branch_id: form.branch_id ? Number(form.branch_id) : null,
        force: force || undefined,
      };
      const saved = patient
        ? await patientsApi.update(patient.id, payload)
        : await patientsApi.create(payload);
      toast.success(patient ? 'Patient updated' : 'Patient registered');
      onSaved(saved);
    } catch (cause) {
      if (cause instanceof ApiError) {
        if (cause.code === 'DUPLICATE_PHONE') {
          setDuplicate((cause.fields as unknown as { existing?: Patient })?.existing as Patient ?? null);
        } else if (cause.isValidation) {
          setErrors(cause.fields);
        } else {
          toast.error(cause.message);
        }
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={patient ? 'Edit patient' : 'Register patient'}
      size="lg"
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={saving}>Cancel</Button>
          <Button onClick={(e) => submit(e as unknown as FormEvent)} loading={saving}>
            {patient ? 'Save changes' : 'Register patient'}
          </Button>
        </>
      }
    >
      {duplicate && (
        <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
          <p className="font-medium">A patient with this phone number already exists:</p>
          <p className="mt-1">{duplicate.patient_number} — {duplicate.full_name} ({duplicate.city ?? 'no city on file'})</p>
          <div className="mt-2 flex gap-2">
            <Button size="sm" variant="secondary" onClick={(e) => submit(e as unknown as FormEvent, true)}>
              Register as new patient anyway
            </Button>
          </div>
        </div>
      )}
      <form onSubmit={submit} noValidate className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input label="Full name" required value={form.full_name} onChange={set('full_name')} error={errors.full_name} />
        <Input label="Phone" required value={form.phone} onChange={set('phone')} error={errors.phone} />
        <Input label="Alternate phone" value={form.alt_phone} onChange={set('alt_phone')} error={errors.alt_phone} />
        <Input label="Email" type="email" value={form.email} onChange={set('email')} error={errors.email} />
        <Input label="Date of birth" type="date" value={form.dob} onChange={set('dob')} error={errors.dob} />
        <Select label="Gender" value={form.gender} onChange={set('gender')} placeholder="Select"
                options={GENDERS.map((g) => ({ value: g, label: g }))} error={errors.gender} />
        <Select label="Blood group" value={form.blood_group} onChange={set('blood_group')} placeholder="Unknown"
                options={BLOOD_GROUPS.map((g) => ({ value: g, label: g }))} error={errors.blood_group} />
        {branches.length > 1 && (
          <Select label="Branch" value={form.branch_id} onChange={set('branch_id')} placeholder="Select branch"
                  options={branches.map((b) => ({ value: b.id, label: b.name }))} error={errors.branch_id} />
        )}
        <Input label="City" value={form.city} onChange={set('city')} error={errors.city} />
        <Input label="State" value={form.state} onChange={set('state')} error={errors.state} />
        <Input label="PIN code" value={form.pin} onChange={set('pin')} error={errors.pin} />
        <Select label="Source" value={form.source} onChange={set('source')} placeholder="Select"
                options={LEAD_SOURCES.map((s) => ({ value: s, label: s }))} error={errors.source} />
        <Input label="Emergency contact name" value={form.emergency_contact_name} onChange={set('emergency_contact_name')} />
        <Input label="Emergency contact phone" value={form.emergency_contact_phone} onChange={set('emergency_contact_phone')} />
        <Input label="Occupation" value={form.occupation} onChange={set('occupation')} wrapperClassName="sm:col-span-2" />
        <Textarea label="Address" value={form.address} onChange={set('address')} wrapperClassName="sm:col-span-2" />
        <Textarea label="Known allergies" value={form.allergies} onChange={set('allergies')} wrapperClassName="sm:col-span-2"
                  hint="Shown prominently during consultation." />
        <Textarea label="Important medical notes" value={form.medical_notes} onChange={set('medical_notes')} wrapperClassName="sm:col-span-2" />
      </form>
    </Modal>
  );
}
