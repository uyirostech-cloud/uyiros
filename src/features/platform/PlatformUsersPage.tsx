import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card } from '@/components/ui/Card';
import { TableSkeleton, EmptyState, ErrorState } from '@/components/ui/States';
import { DataTable, Pagination, type Column } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { platformApi } from '@/services/api';
import { formatDateTime } from '@/utils/format';

interface UserRow {
  id: number; name: string; email: string; organization: string | null;
  role: string | null; is_active: boolean; last_login_at: string | null;
}

export default function PlatformUsersPage() {
  const [page, setPage] = useState(1);
  const query = useQuery({
    queryKey: ['platform-users', page],
    queryFn: () => platformApi.users({ page, per_page: 25 }),
  });
  const rows = (query.data?.data ?? []) as unknown as UserRow[];

  const columns: Column<UserRow>[] = [
    {
      key: 'name', header: 'User', render: (row) => (
        <div><p className="font-medium text-ink-900">{row.name}</p><p className="text-xs text-ink-500">{row.email}</p></div>
      ),
    },
    { key: 'organization', header: 'Clinic', render: (row) => row.organization ?? '—' },
    { key: 'role', header: 'Role', hideOnMobile: true, render: (row) => row.role ?? '—' },
    { key: 'status', header: 'Status', render: (row) => <Badge tone={row.is_active ? 'success' : 'neutral'}>{row.is_active ? 'Active' : 'Inactive'}</Badge> },
    { key: 'last_login', header: 'Last login', hideOnMobile: true, render: (row) => row.last_login_at ? formatDateTime(row.last_login_at) : 'Never' },
  ];

  return (
    <div>
      <h1 className="mb-4 text-lg font-semibold text-ink-900">Users across clinics</h1>
      <p className="mb-4 -mt-2 text-sm text-ink-500">Administrative overview only — clinical and patient records are never visible here.</p>
      <Card>
        {query.isLoading ? <TableSkeleton rows={6} columns={5} />
          : query.isError ? <ErrorState onRetry={() => query.refetch()} />
            : (
              <>
                <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} emptyState={<EmptyState title="No users yet" />} />
                {query.data && rows.length > 0 && (
                  <Pagination page={query.data.meta.page} lastPage={query.data.meta.last_page}
                              total={query.data.meta.total} perPage={query.data.meta.per_page} onPageChange={setPage} />
                )}
              </>
            )}
      </Card>
    </div>
  );
}
