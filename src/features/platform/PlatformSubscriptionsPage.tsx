import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card } from '@/components/ui/Card';
import { TableSkeleton, EmptyState, ErrorState } from '@/components/ui/States';
import { DataTable, Pagination, type Column } from '@/components/ui/Table';
import { Badge, statusTone } from '@/components/ui/Badge';
import { platformApi } from '@/services/api';
import { formatMoney, formatDate } from '@/utils/format';

interface SubRow {
  id: number; organization: string; plan: string; status: string;
  billing_cycle: string; amount: string; starts_at: string; ends_at: string | null;
}

export default function PlatformSubscriptionsPage() {
  const [page, setPage] = useState(1);
  const query = useQuery({
    queryKey: ['platform-subs', page],
    queryFn: () => platformApi.subscriptions({ page, per_page: 20 }),
  });
  const rows = (query.data?.data ?? []) as unknown as SubRow[];

  const columns: Column<SubRow>[] = [
    { key: 'organization', header: 'Clinic', render: (row) => row.organization },
    { key: 'plan', header: 'Plan', render: (row) => row.plan },
    { key: 'status', header: 'Status', render: (row) => <Badge tone={statusTone(row.status)}>{row.status}</Badge> },
    { key: 'cycle', header: 'Billing cycle', hideOnMobile: true, render: (row) => row.billing_cycle },
    { key: 'amount', header: 'Amount', render: (row) => formatMoney(row.amount) },
    { key: 'starts', header: 'Started', hideOnMobile: true, render: (row) => formatDate(row.starts_at) },
  ];

  return (
    <div>
      <h1 className="mb-4 text-lg font-semibold text-ink-900">Subscriptions</h1>
      <Card>
        {query.isLoading ? <TableSkeleton rows={4} columns={6} />
          : query.isError ? <ErrorState onRetry={() => query.refetch()} />
            : (
              <>
                <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} emptyState={<EmptyState title="No subscriptions yet" />} />
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
