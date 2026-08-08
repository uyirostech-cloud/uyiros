import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input, Select, Checkbox } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { DataTable, Pagination, type Column } from '@/components/ui/Table';
import { TableSkeleton, EmptyState, ErrorState } from '@/components/ui/States';
import { Modal, ConfirmDialog } from '@/components/ui/Modal';
import { useAuth } from '@/hooks/useAuth';
import { usersApi, rolesApi, branchesApi } from '@/services/api';
import { ApiError } from '@/types/api';
import { useToast } from '@/components/ui/Toast';
import { formatDateTime, titleCase } from '@/utils/format';
import type { User } from '@/types/models';

const emptyForm = {
  name: '', email: '', password: '', role_id: '', all_branches: true, branch_ids: [] as number[],
};

export default function UsersPage() {
  const { can, user: currentUser } = useAuth();
  const toast = useToast();
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<User | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [confirmDeactivate, setConfirmDeactivate] = useState<User | null>(null);

  const query = useQuery({ queryKey: ['users', page], queryFn: () => usersApi.list({ page, per_page: 25 }) });
  const roles = useQuery({ queryKey: ['roles'], queryFn: rolesApi.list });
  const allBranches = useQuery({ queryKey: ['branches-all'], queryFn: () => branchesApi.list({ per_page: 100 }) });

  useEffect(() => {
    if (!editing && roles.data && roles.data.length > 0 && !form.role_id) {
      const receptionist = roles.data.find((r) => r.slug === 'receptionist') ?? roles.data[0];
      setForm((f) => ({ ...f, role_id: String(receptionist!.id) }));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [roles.data]);

  const openCreate = () => {
    setEditing(null);
    setForm({ ...emptyForm, role_id: roles.data?.[0] ? String(roles.data[0].id) : '' });
    setErrors({});
    setModalOpen(true);
  };
  const openEdit = (u: User) => {
    setEditing(u);
    setForm({
      name: u.name, email: u.email, password: '', role_id: u.role_id ? String(u.role_id) : '',
      all_branches: u.all_branches, branch_ids: u.branches.map((b) => b.id),
    });
    setErrors({});
    setModalOpen(true);
  };

  const mutation = useMutation({
    mutationFn: () => {
      const payload = {
        name: form.name, email: form.email, role_id: Number(form.role_id),
        all_branches: form.all_branches, branch_ids: form.all_branches ? [] : form.branch_ids,
        ...(editing ? {} : { password: form.password }),
      };
      return editing ? usersApi.update(editing.id, payload) : usersApi.create(payload);
    },
    onSuccess: () => {
      toast.success(editing ? 'User updated' : 'User created');
      setModalOpen(false);
      void queryClient.invalidateQueries({ queryKey: ['users'] });
    },
    onError: (cause) => {
      if (cause instanceof ApiError && cause.isValidation) setErrors(cause.fields);
      else if (cause instanceof ApiError) toast.error(cause.message);
    },
  });

  const deactivateMutation = useMutation({
    mutationFn: (id: number) => usersApi.deactivate(id),
    onSuccess: () => {
      toast.success('User deactivated');
      setConfirmDeactivate(null);
      void queryClient.invalidateQueries({ queryKey: ['users'] });
    },
    onError: (cause) => { if (cause instanceof ApiError) toast.error(cause.message); },
  });

  const columns = useMemo<Column<User>[]>(() => [
    {
      key: 'name', header: 'User', render: (row) => (
        <div>
          <p className="font-medium text-ink-900">{row.name}</p>
          <p className="text-xs text-ink-500">{row.email}</p>
        </div>
      ),
    },
    { key: 'role', header: 'Role', render: (row) => row.role ? titleCase(row.role.slug) : '—' },
    {
      key: 'branches', header: 'Branches', hideOnMobile: true,
      render: (row) => row.all_branches ? 'All branches' : (row.branches.map((b) => b.name).join(', ') || '—'),
    },
    {
      key: 'last_login_at', header: 'Last login', hideOnMobile: true,
      render: (row) => row.last_login_at ? formatDateTime(row.last_login_at) : 'Never',
    },
    { key: 'is_active', header: 'Status', render: (row) => <Badge tone={row.is_active ? 'success' : 'neutral'}>{row.is_active ? 'Active' : 'Inactive'}</Badge> },
    ...(can('user.manage') ? [{
      key: 'actions', header: '', render: (row: User) => (
        <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
          <Button size="sm" variant="ghost" onClick={() => openEdit(row)}>Edit</Button>
          {row.is_active && row.id !== currentUser?.id && (
            <Button size="sm" variant="ghost" className="text-red-600" onClick={() => setConfirmDeactivate(row)}>
              Deactivate
            </Button>
          )}
        </div>
      ),
    } as Column<User>] : []),
  ], [can, currentUser]);

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-lg font-semibold text-ink-900">Users</h1>
          <p className="text-sm text-ink-500">Staff accounts, roles and branch access.</p>
        </div>
        {can('user.manage') && <Button onClick={openCreate}>+ Add user</Button>}
      </div>

      <Card>
        {query.isLoading ? <TableSkeleton rows={5} columns={5} />
          : query.isError ? <ErrorState onRetry={() => query.refetch()} />
            : (
              <>
                <DataTable
                  columns={columns}
                  rows={query.data?.data ?? []}
                  rowKey={(row) => row.id}
                  emptyState={<EmptyState title="No users yet" />}
                />
                {query.data && query.data.data.length > 0 && (
                  <Pagination page={query.data.meta.page} lastPage={query.data.meta.last_page}
                              total={query.data.meta.total} perPage={query.data.meta.per_page} onPageChange={setPage} />
                )}
              </>
            )}
      </Card>

      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editing ? 'Edit user' : 'Add user'}
        footer={
          <>
            <Button variant="secondary" onClick={() => setModalOpen(false)}>Cancel</Button>
            <Button onClick={() => mutation.mutate()} loading={mutation.isPending}>Save</Button>
          </>
        }
      >
        <div className="space-y-4">
          <Input label="Full name" required value={form.name} error={errors.name}
                 onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
          <Input label="Email" type="email" required value={form.email} error={errors.email}
                 disabled={Boolean(editing)}
                 onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} />
          {!editing && (
            <Input label="Temporary password" type="password" required value={form.password} error={errors.password}
                   hint="The user will be required to change this at first sign-in."
                   onChange={(e) => setForm((f) => ({ ...f, password: e.target.value }))} />
          )}
          <Select label="Role" required value={form.role_id} error={errors.role_id}
                  options={(roles.data ?? []).map((r) => ({ value: r.id, label: r.name }))}
                  onChange={(e) => setForm((f) => ({ ...f, role_id: e.target.value }))} />
          <Checkbox label="Access all branches" checked={form.all_branches}
                    onChange={(e) => setForm((f) => ({ ...f, all_branches: e.target.checked }))} />
          {!form.all_branches && (
            <div>
              <p className="label-base">Branch access</p>
              <div className="flex flex-wrap gap-3">
                {(allBranches.data?.data ?? []).map((b) => (
                  <Checkbox
                    key={b.id}
                    label={b.name}
                    checked={form.branch_ids.includes(b.id)}
                    onChange={(e) => setForm((f) => ({
                      ...f,
                      branch_ids: e.target.checked ? [...f.branch_ids, b.id] : f.branch_ids.filter((id) => id !== b.id),
                    }))}
                  />
                ))}
              </div>
              {errors.branch_ids && <p className="mt-1 text-xs text-red-600">{errors.branch_ids}</p>}
            </div>
          )}
        </div>
      </Modal>

      <ConfirmDialog
        open={Boolean(confirmDeactivate)}
        onClose={() => setConfirmDeactivate(null)}
        onConfirm={() => confirmDeactivate && deactivateMutation.mutate(confirmDeactivate.id)}
        title="Deactivate user"
        message={`${confirmDeactivate?.name} will immediately lose access and their active sessions will be ended.`}
        confirmLabel="Deactivate"
        loading={deactivateMutation.isPending}
      />
    </div>
  );
}
