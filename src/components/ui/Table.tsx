import type { ReactNode } from 'react';
import { cn } from './cn';
import { Button } from './Button';

export interface Column<T> {
  key: string;
  header: ReactNode;
  render: (row: T) => ReactNode;
  className?: string;
  headerClassName?: string;
  /** Hide on small screens — the row still shows it in the stacked mobile card. */
  hideOnMobile?: boolean;
  sortable?: boolean;
}

interface DataTableProps<T> {
  columns: Column<T>[];
  rows: T[];
  rowKey: (row: T) => string | number;
  onRowClick?: (row: T) => void;
  sort?: { key: string; dir: 'asc' | 'desc' };
  onSortChange?: (key: string) => void;
  emptyState?: ReactNode;
}

export function DataTable<T>({
  columns, rows, rowKey, onRowClick, sort, onSortChange, emptyState,
}: DataTableProps<T>) {
  if (rows.length === 0 && emptyState) {
    return <>{emptyState}</>;
  }

  return (
    <>
      {/* Desktop / tablet: a real table */}
      <div className="hidden overflow-x-auto md:block">
        <table className="w-full min-w-[640px] border-collapse text-sm">
          <thead>
            <tr className="border-b border-ink-200 bg-ink-50/60">
              {columns.map((column) => (
                <th
                  key={column.key}
                  scope="col"
                  className={cn(
                    'px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-ink-500',
                    column.headerClassName,
                  )}
                >
                  {column.sortable && onSortChange ? (
                    <button
                      type="button"
                      onClick={() => onSortChange(column.key)}
                      className="inline-flex items-center gap-1 hover:text-ink-800"
                    >
                      {column.header}
                      <span aria-hidden className="text-[10px]">
                        {sort?.key === column.key ? (sort.dir === 'asc' ? '▲' : '▼') : '↕'}
                      </span>
                    </button>
                  ) : (
                    column.header
                  )}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {rows.map((row) => (
              <tr
                key={rowKey(row)}
                onClick={onRowClick ? () => onRowClick(row) : undefined}
                className={cn('bg-white', onRowClick && 'cursor-pointer hover:bg-brand-50/40')}
              >
                {columns.map((column) => (
                  <td key={column.key} className={cn('px-4 py-3 align-middle text-ink-800', column.className)}>
                    {column.render(row)}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Mobile: stacked cards, because a 7-column table is unusable at 375px.
          Action columns render their own <button>s, so the row itself must NOT be a
          <button> — nested interactive elements are invalid HTML and React warns loudly. */}
      <ul className="divide-y divide-ink-100 md:hidden">
        {rows.map((row) => {
          const dl = (
            <dl className="space-y-1.5">
              {columns.filter((column) => !column.hideOnMobile).map((column) => (
                <div key={column.key} className="flex items-baseline justify-between gap-3">
                  <dt className="text-xs font-medium uppercase tracking-wide text-ink-400">{column.header}</dt>
                  <dd className="min-w-0 text-right text-sm text-ink-800">{column.render(row)}</dd>
                </div>
              ))}
            </dl>
          );
          return (
            <li key={rowKey(row)}>
              {onRowClick ? (
                <div
                  role="button"
                  tabIndex={0}
                  onClick={() => onRowClick(row)}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                      event.preventDefault();
                      onRowClick(row);
                    }
                  }}
                  className="w-full cursor-pointer px-4 py-3 text-left"
                >
                  {dl}
                </div>
              ) : (
                <div className="w-full px-4 py-3 text-left">{dl}</div>
              )}
            </li>
          );
        })}
      </ul>
    </>
  );
}

export function Pagination({
  page, lastPage, total, perPage, onPageChange,
}: {
  page: number;
  lastPage: number;
  total: number;
  perPage: number;
  onPageChange: (page: number) => void;
}) {
  const from = total === 0 ? 0 : (page - 1) * perPage + 1;
  const to = Math.min(page * perPage, total);

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-ink-200 px-4 py-3">
      <p className="text-xs text-ink-500">
        Showing <span className="font-medium text-ink-700">{from}</span>–
        <span className="font-medium text-ink-700">{to}</span> of{' '}
        <span className="font-medium text-ink-700">{total}</span>
      </p>
      <div className="flex items-center gap-2">
        <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => onPageChange(page - 1)}>
          Previous
        </Button>
        <span className="px-1 text-xs text-ink-500">
          Page {page} of {Math.max(1, lastPage)}
        </span>
        <Button size="sm" variant="secondary" disabled={page >= lastPage} onClick={() => onPageChange(page + 1)}>
          Next
        </Button>
      </div>
    </div>
  );
}
