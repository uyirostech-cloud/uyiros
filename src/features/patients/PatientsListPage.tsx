import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input, Select } from '@/components/ui/Input';
import { DataTable, Pagination, type Column } from '@/components/ui/Table';
import { TableSkeleton, EmptyState, ErrorState } from '@/components/ui/States';
import { Badge } from '@/components/ui/Badge';
import { useAuth } from '@/hooks/useAuth';
import { useDebounce } from '@/hooks/useDebounce';
import { patientsApi } from '@/services/api';
import { formatDate } from '@/utils/format';
import type { Patient } from '@/types/models';
import { PatientFormModal } from './PatientFormModal';

export default function PatientsListPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { can, activeBranchId, branches } = useAuth();

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [branchFilter, setBranchFilter] = useState<number | ''>('');
  const [formOpen, setFormOpen] = useState(false);
  const debouncedSearch = useDebounce(search, 350);

  const branchId = branchFilter || activeBranchId;

  const query = useQuery({
    queryKey: ['patients', page, debouncedSearch, branchId],
    queryFn: () => patientsApi.list({
      page, per_page: 25, q: debouncedSearch || undefined, branch_id: branchId || undefined,
      sort: 'created_at', dir: 'desc',
    }),
    placeholderData: (previous) => previous,
  });

  const columns = useMemo<Column<Patient>[]>(() => [
    {
      key: 'patient_number', header: 'UHID', sortable: false,
      render: (row) => <span className="font-mono text-xs text-ink-500">{row.patient_number}</span>,
    },
    {
      key: 'full_name', header: 'Name',
      render: (row) => (
        <div>
          <p className="font-medium text-ink-900">{row.full_name}</p>
          <p className="text-xs text-ink-500">{row.gender ?? '—'}{row.age_years ? `, ${row.age_years}y` : ''}</p>
        </div>
      ),
    },
    { key: 'phone', header: 'Phone', render: (row) => row.phone, hideOnMobile: false },
    {
      key: 'city', header: 'City', hideOnMobile: true,
      render: (row) => row.city ?? '—',
    },
    {
      key: 'registered_at', header: 'Registered', hideOnMobile: true,
      render: (row) => formatDate(row.registered_at),
    },
    {
      key: 'is_active', header: 'Status',
      render: (row) => <Badge tone={row.is_active ? 'success' : 'neutral'}>{row.is_active ? 'Active' : 'Inactive'}</Badge>,
    },
  ], []);

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-lg font-semibold text-ink-900">Patients</h1>
          <p className="text-sm text-ink-500">Every visit, invoice and prescription starts from this record.</p>
        </div>
        {can('patient.create') && (
          <Button onClick={() => setFormOpen(true)}>+ Register patient</Button>
        )}
      </div>

      <Card>
        <div className="flex flex-wrap items-center gap-3 border-b border-ink-200 p-3.5">
          <Input
            placeholder="Search by name, phone or UHID"
            value={search}
            onChange={(event) => { setSearch(event.target.value); setPage(1); }}
            wrapperClassName="w-full sm:w-72"
          />
          {branches.length > 1 && (
            <Select
              value={branchFilter}
              onChange={(event) => { setBranchFilter(event.target.value ? Number(event.target.value) : ''); setPage(1); }}
              placeholder="All branches"
              options={branches.map((b) => ({ value: b.id, label: b.name }))}
              wrapperClassName="w-full sm:w-48"
            />
          )}
        </div>

        {query.isLoading ? (
          <TableSkeleton rows={6} columns={6} />
        ) : query.isError ? (
          <ErrorState message="Could not load patients." onRetry={() => query.refetch()} />
        ) : (
          <>
            <DataTable
              columns={columns}
              rows={query.data?.data ?? []}
              rowKey={(row) => row.id}
              onRowClick={(row) => navigate(`/app/patients/${row.id}`)}
              emptyState={
                <EmptyState
                  title={debouncedSearch ? 'No patients match your search' : 'No patients yet'}
                  description={debouncedSearch ? 'Try a different name, phone number or UHID.' : 'Register your first patient to get started.'}
                  action={!debouncedSearch && can('patient.create')
                    ? <Button size="sm" onClick={() => setFormOpen(true)}>+ Register patient</Button>
                    : undefined}
                />
              }
            />
            {query.data && query.data.data.length > 0 && (
              <Pagination
                page={query.data.meta.page}
                lastPage={query.data.meta.last_page}
                total={query.data.meta.total}
                perPage={query.data.meta.per_page}
                onPageChange={setPage}
              />
            )}
          </>
        )}
      </Card>

      <PatientFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        onSaved={(patient) => {
          setFormOpen(false);
          void queryClient.invalidateQueries({ queryKey: ['patients'] });
          navigate(`/app/patients/${patient.id}`);
        }}
      />
    </div>
  );
}
