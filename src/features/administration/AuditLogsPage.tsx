import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card } from '@/components/ui/Card';
import { DataTable, Pagination, type Column } from '@/components/ui/Table';
import { TableSkeleton, EmptyState, ErrorState } from '@/components/ui/States';
import { auditApi } from '@/services/api';
import { formatDateTime, titleCase } from '@/utils/format';
import type { AuditLogEntry } from '@/types/models';

export default function AuditLogsPage() {
  const [page, setPage] = useState(1);
  const query = useQuery({ queryKey: ['audit-logs', page], queryFn: () => auditApi.list({ page, per_page: 30 }) });

  const columns: Column<AuditLogEntry>[] = [
    { key: 'created_at', header: 'When', render: (row) => formatDateTime(row.created_at) },
    { key: 'user_name', header: 'User', render: (row) => row.user_name ?? 'System' },
    {
      key: 'action', header: 'Action',
      render: (row) => <span className="font-mono text-xs text-ink-700">{titleCase(row.action)}</span>,
    },
    {
      key: 'entity', header: 'Record', hideOnMobile: true,
      render: (row) => row.entity_type ? `${titleCase(row.entity_type)} #${row.entity_id}` : '—',
    },
    { key: 'ip', header: 'IP', hideOnMobile: true, render: (row) => row.ip ?? '—' },
  ];

  return (
    <div>
      <h1 className="mb-4 text-lg font-semibold text-ink-900">Audit logs</h1>
      <Card>
        {query.isLoading ? <TableSkeleton rows={8} columns={5} />
          : query.isError ? <ErrorState onRetry={() => query.refetch()} />
            : (
              <>
                <DataTable
                  columns={columns}
                  rows={query.data?.data ?? []}
                  rowKey={(row) => row.id}
                  emptyState={<EmptyState title="No activity recorded yet" />}
                />
                {query.data && query.data.data.length > 0 && (
                  <Pagination page={query.data.meta.page} lastPage={query.data.meta.last_page}
                              total={query.data.meta.total} perPage={query.data.meta.per_page} onPageChange={setPage} />
                )}
              </>
            )}
      </Card>
    </div>
  );
}
