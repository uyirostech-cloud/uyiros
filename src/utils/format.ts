/**
 * Money arrives from the API as a DECIMAL string and is only ever formatted for display —
 * no arithmetic happens in the browser. Totals are computed server-side.
 */
export function formatMoney(value: string | number | null | undefined, symbol = '₹'): string {
  if (value === null || value === undefined || value === '') return `${symbol}0.00`;
  const numeric = typeof value === 'number' ? value : Number.parseFloat(value);
  if (Number.isNaN(numeric)) return `${symbol}0.00`;
  return `${symbol}${numeric.toLocaleString('en-IN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

export function formatNumber(value: number | string | null | undefined): string {
  if (value === null || value === undefined || value === '') return '0';
  const numeric = typeof value === 'number' ? value : Number.parseFloat(value);
  return Number.isNaN(numeric) ? '0' : numeric.toLocaleString('en-IN');
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—';
  const date = new Date(value.includes('T') ? value : value.replace(' ', 'T') + 'Z');
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—';
  const date = new Date(value.includes('T') ? value : value.replace(' ', 'T') + 'Z');
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('en-IN', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function formatDayLabel(day: string): string {
  const date = new Date(`${day}T00:00:00Z`);
  return Number.isNaN(date.getTime())
    ? day
    : date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
}

export function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');
}

export function titleCase(value: string): string {
  return value.replace(/[_.]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
