import { useQuery } from '@tanstack/react-query';
import { Card } from '@/components/ui/Card';
import { TableSkeleton, EmptyState, ErrorState } from '@/components/ui/States';
import { DataTable, type Column } from '@/components/ui/Table';
import { platformApi } from '@/services/api';
import { formatMoney } from '@/utils/format';

interface PlanRow {
  id: number; name: string; code: string; price_monthly: string; price_yearly: string;
  max_branches: number; max_users: number; is_active: boolean;
}

export default function PlatformPlansPage() {
  const query = useQuery({ queryKey: ['platform-plans'], queryFn: platformApi.plans });
  const rows = (query.data ?? []) as unknown as PlanRow[];

  const columns: Column<PlanRow>[] = [
    { key: 'name', header: 'Plan', render: (row) => <span className="font-medium text-ink-900">{row.name}</span> },
    { key: 'code', header: 'Code', render: (row) => row.code },
    { key: 'monthly', header: 'Monthly', render: (row) => formatMoney(row.price_monthly) },
    { key: 'yearly', header: 'Yearly', hideOnMobile: true, render: (row) => formatMoney(row.price_yearly) },
    { key: 'branches', header: 'Max branches', hideOnMobile: true, render: (row) => row.max_branches },
    { key: 'users', header: 'Max users', hideOnMobile: true, render: (row) => row.max_users },
  ];

  return (
    <div>
      <h1 className="mb-4 text-lg font-semibold text-ink-900">Plans</h1>
      <Card>
        {query.isLoading ? <TableSkeleton rows={3} columns={6} />
          : query.isError ? <ErrorState onRetry={() => query.refetch()} />
            : <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} emptyState={<EmptyState title="No plans configured" />} />}
      </Card>
    </div>
  );
}
