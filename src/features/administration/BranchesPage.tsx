import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { DataTable, type Column } from '@/components/ui/Table';
import { TableSkeleton, EmptyState, ErrorState } from '@/components/ui/States';
import { Modal } from '@/components/ui/Modal';
import { useAuth } from '@/hooks/useAuth';
import { branchesApi } from '@/services/api';
import { ApiError } from '@/types/api';
import { useToast } from '@/components/ui/Toast';
import type { Branch } from '@/types/models';

const emptyForm = { name: '', code: '', phone: '', email: '', city: '', state: '', address: '', pin: '' };

export default function BranchesPage() {
  const { can } = useAuth();
  const toast = useToast();
  const queryClient = useQueryClient();
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<Branch | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const query = useQuery({ queryKey: ['branches'], queryFn: () => branchesApi.list({ per_page: 100 }) });

  const openCreate = () => { setEditing(null); setForm(emptyForm); setErrors({}); setModalOpen(true); };
  const openEdit = (branch: Branch) => {
    setEditing(branch);
    setForm({
      name: branch.name, code: branch.code, phone: branch.phone ?? '', email: branch.email ?? '',
      city: branch.city ?? '', state: branch.state ?? '', address: branch.address ?? '', pin: branch.pin ?? '',
    });
    setErrors({});
    setModalOpen(true);
  };

  const mutation = useMutation({
    mutationFn: () => editing ? branchesApi.update(editing.id, form) : branchesApi.create(form),
    onSuccess: () => {
      toast.success(editing ? 'Branch updated' : 'Branch created');
      setModalOpen(false);
      void queryClient.invalidateQueries({ queryKey: ['branches'] });
    },
    onError: (cause) => {
      if (cause instanceof ApiError && cause.isValidation) setErrors(cause.fields);
      else if (cause instanceof ApiError) toast.error(cause.message);
    },
  });

  const columns = useMemo<Column<Branch>[]>(() => [
    { key: 'name', header: 'Branch', render: (row) => <span className="font-medium text-ink-900">{row.name}</span> },
    { key: 'code', header: 'Code', render: (row) => <span className="font-mono text-xs">{row.code}</span> },
    { key: 'city', header: 'City', hideOnMobile: true, render: (row) => row.city ?? '—' },
    { key: 'phone', header: 'Phone', hideOnMobile: true, render: (row) => row.phone ?? '—' },
    { key: 'is_active', header: 'Status', render: (row) => <Badge tone={row.is_active ? 'success' : 'neutral'}>{row.is_active ? 'Active' : 'Inactive'}</Badge> },
    ...(can('branch.manage') ? [{
      key: 'actions', header: '', render: (row: Branch) => (
        <Button size="sm" variant="ghost" onClick={(e: React.MouseEvent) => { e.stopPropagation(); openEdit(row); }}>Edit</Button>
      ),
    } as Column<Branch>] : []),
  ], [can]);

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-lg font-semibold text-ink-900">Branches</h1>
          <p className="text-sm text-ink-500">Locations your clinic operates from.</p>
        </div>
        {can('branch.manage') && <Button onClick={openCreate}>+ Add branch</Button>}
      </div>

      <Card>
        {query.isLoading ? <TableSkeleton rows={4} columns={5} />
          : query.isError ? <ErrorState onRetry={() => query.refetch()} />
            : (
              <DataTable
                columns={columns}
                rows={query.data?.data ?? []}
                rowKey={(row) => row.id}
                emptyState={<EmptyState title="No branches yet" description="Add your first branch to start scheduling." />}
              />
            )}
      </Card>

      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editing ? 'Edit branch' : 'Add branch'}
        footer={
          <>
            <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
            <Button onClick={() => mutation.mutate()} loading={mutation.isPending}>Save</Button>
          </>
        }
      >
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input label="Branch name" required value={form.name} error={errors.name}
                 onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
          <Input label="Branch code" required value={form.code} error={errors.code}
                 onChange={(e) => setForm((f) => ({ ...f, code: e.target.value }))} />
          <Input label="Phone" value={form.phone} error={errors.phone}
                 onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))} />
          <Input label="Email" type="email" value={form.email} error={errors.email}
                 onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} />
          <Input label="City" value={form.city} onChange={(e) => setForm((f) => ({ ...f, city: e.target.value }))} />
          <Input label="State" value={form.state} onChange={(e) => setForm((f) => ({ ...f, state: e.target.value }))} />
          <Input label="PIN code" value={form.pin} onChange={(e) => setForm((f) => ({ ...f, pin: e.target.value }))} />
          <Input label="Address" value={form.address} wrapperClassName="sm:col-span-2"
                 onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))} />
        </div>
      </Modal>
    </div>
  );
}
