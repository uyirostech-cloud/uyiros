import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input, Select } from '@/components/ui/Input';
import { Badge, statusTone } from '@/components/ui/Badge';
import { DataTable, Pagination, type Column } from '@/components/ui/Table';
import { TableSkeleton, EmptyState, ErrorState } from '@/components/ui/States';
import { Modal } from '@/components/ui/Modal';
import { platformApi } from '@/services/api';
import { ApiError } from '@/types/api';
import { useToast } from '@/components/ui/Toast';
import { formatDate } from '@/utils/format';
import type { PlatformOrganization } from '@/types/models';

const emptyForm = {
  name: '', code: '', email: '', phone: '', city: '', state: '',
  admin_name: '', admin_email: '', admin_password: '', branch_name: '',
};

export default function PlatformOrganizationsPage() {
  const toast = useToast();
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [createOpen, setCreateOpen] = useState(false);
  const [statusOrg, setStatusOrg] = useState<PlatformOrganization | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const query = useQuery({
    queryKey: ['platform-orgs', page, search],
    queryFn: () => platformApi.organizations({ page, per_page: 20, q: search || undefined }),
  });

  const createMutation = useMutation({
    mutationFn: () => platformApi.createOrganization(form),
    onSuccess: () => {
      toast.success('Clinic created');
      setCreateOpen(false);
      setForm(emptyForm);
      void queryClient.invalidateQueries({ queryKey: ['platform-orgs'] });
    },
    onError: (cause) => {
      if (cause instanceof ApiError && cause.isValidation) setErrors(cause.fields);
      else if (cause instanceof ApiError) toast.error(cause.message);
    },
  });

  const statusMutation = useMutation({
    mutationFn: (status: string) => platformApi.setOrganizationStatus(statusOrg!.id, status),
    onSuccess: () => {
      toast.success('Clinic status updated');
      setStatusOrg(null);
      void queryClient.invalidateQueries({ queryKey: ['platform-orgs'] });
    },
    onError: (cause) => { if (cause instanceof ApiError) toast.error(cause.message); },
  });

  const columns: Column<PlatformOrganization>[] = [
    {
      key: 'name', header: 'Clinic', render: (row) => (
        <div>
          <p className="font-medium text-ink-900">{row.name}</p>
          <p className="text-xs text-ink-500">{row.code} · {row.city ?? 'no city'}</p>
        </div>
      ),
    },
    { key: 'plan', header: 'Plan', render: (row) => row.plan ?? '—' },
    { key: 'branches', header: 'Branches', hideOnMobile: true, render: (row) => row.branch_count },
    { key: 'users', header: 'Users', hideOnMobile: true, render: (row) => row.user_count },
    { key: 'patients', header: 'Patients', hideOnMobile: true, render: (row) => row.patient_count },
    { key: 'status', header: 'Status', render: (row) => <Badge tone={statusTone(row.status)}>{row.status}</Badge> },
    { key: 'created_at', header: 'Created', hideOnMobile: true, render: (row) => formatDate(row.created_at) },
    {
      key: 'actions', header: '', render: (row) => (
        <Button size="sm" variant="ghost" onClick={(e) => { e.stopPropagation(); setStatusOrg(row); }}>
          Change status
        </Button>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-lg font-semibold text-ink-900">Organizations</h1>
          <p className="text-sm text-ink-500">Clinics running on ClinicFlow.</p>
        </div>
        <Button onClick={() => { setForm(emptyForm); setErrors({}); setCreateOpen(true); }}>+ New clinic</Button>
      </div>

      <Card>
        <div className="border-b border-ink-200 p-3.5">
          <Input placeholder="Search clinics" value={search}
                 onChange={(e) => { setSearch(e.target.value); setPage(1); }} wrapperClassName="w-full sm:w-72" />
        </div>
        {query.isLoading ? <TableSkeleton rows={5} columns={7} />
          : query.isError ? <ErrorState onRetry={() => query.refetch()} />
            : (
              <>
                <DataTable columns={columns} rows={query.data?.data ?? []} rowKey={(r) => r.id}
                           emptyState={<EmptyState title="No clinics yet" />} />
                {query.data && query.data.data.length > 0 && (
                  <Pagination page={query.data.meta.page} lastPage={query.data.meta.last_page}
                              total={query.data.meta.total} perPage={query.data.meta.per_page} onPageChange={setPage} />
                )}
              </>
            )}
      </Card>

      <Modal
        open={createOpen} onClose={() => setCreateOpen(false)} title="Create a new clinic" size="lg"
        footer={<>
          <Button variant="secondary" onClick={() => setCreateOpen(false)}>Cancel</Button>
          <Button onClick={() => createMutation.mutate()} loading={createMutation.isPending}>Create clinic</Button>
        </>}
      >
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input label="Clinic name" required value={form.name} error={errors.name}
                 onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
          <Input label="Clinic code" required value={form.code} error={errors.code}
                 onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))} />
          <Input label="Email" value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} />
          <Input label="Phone" value={form.phone} onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))} />
          <Input label="City" value={form.city} onChange={(e) => setForm((f) => ({ ...f, city: e.target.value }))} />
          <Input label="State" value={form.state} onChange={(e) => setForm((f) => ({ ...f, state: e.target.value }))} />
          <Input label="First branch name" value={form.branch_name} placeholder="Main Branch"
                 onChange={(e) => setForm((f) => ({ ...f, branch_name: e.target.value }))} wrapperClassName="sm:col-span-2" />
          <hr className="sm:col-span-2 border-ink-200" />
          <Input label="Admin name" required value={form.admin_name} error={errors.admin_name}
                 onChange={(e) => setForm((f) => ({ ...f, admin_name: e.target.value }))} />
          <Input label="Admin email" required value={form.admin_email} error={errors.admin_email}
                 onChange={(e) => setForm((f) => ({ ...f, admin_email: e.target.value }))} />
          <Input label="Admin temporary password" type="password" required value={form.admin_password}
                 error={errors.admin_password} wrapperClassName="sm:col-span-2"
                 onChange={(e) => setForm((f) => ({ ...f, admin_password: e.target.value }))} />
        </div>
      </Modal>

      <Modal
        open={Boolean(statusOrg)} onClose={() => setStatusOrg(null)} title={`Change status — ${statusOrg?.name ?? ''}`}
      >
        <Select
          label="New status" value={statusOrg?.status ?? ''} placeholder={undefined}
          options={[
            { value: 'active', label: 'Active' }, { value: 'trial', label: 'Trial' },
            { value: 'suspended', label: 'Suspended' }, { value: 'cancelled', label: 'Cancelled' },
          ]}
          onChange={(e) => statusMutation.mutate(e.target.value)}
        />
        {statusMutation.isPending && <p className="mt-2 text-xs text-ink-500">Saving…</p>}
      </Modal>
    </div>
  );
}
