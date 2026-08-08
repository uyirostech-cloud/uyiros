import { cn } from './cn';

export type BadgeTone = 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'brand';

const tones: Record<BadgeTone, string> = {
  neutral: 'bg-ink-100 text-ink-700 ring-ink-200',
  success: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  warning: 'bg-amber-50 text-amber-800 ring-amber-200',
  danger: 'bg-red-50 text-red-700 ring-red-200',
  info: 'bg-sky-50 text-sky-700 ring-sky-200',
  brand: 'bg-brand-50 text-brand-800 ring-brand-200',
};

/** Shared status vocabulary so a status reads the same colour on every screen. */
const statusTones: Record<string, BadgeTone> = {
  // appointments
  Scheduled: 'info', Confirmed: 'info', 'Checked In': 'brand', Waiting: 'warning',
  'In Consultation': 'brand', Completed: 'success', Cancelled: 'danger',
  'No Show': 'danger', Rescheduled: 'neutral',
  // invoices
  Draft: 'neutral', Unpaid: 'warning', 'Partially Paid': 'warning', Paid: 'success', Refunded: 'neutral',
  // leads
  New: 'info', Contacted: 'info', 'Follow-up': 'warning', Interested: 'brand',
  'Appointment Booked': 'brand', Converted: 'success', 'Not Interested': 'neutral', Lost: 'danger',
  // expenses / general
  Pending: 'warning', Approved: 'success', Rejected: 'danger',
  Open: 'info', 'In Progress': 'warning',
  active: 'success', trial: 'info', suspended: 'danger', cancelled: 'neutral',
  waiting: 'warning', in_consultation: 'brand', completed: 'success',
};

export function statusTone(status: string): BadgeTone {
  return statusTones[status] ?? 'neutral';
}

export function Badge({
  children, tone = 'neutral', className,
}: {
  children: React.ReactNode;
  tone?: BadgeTone;
  className?: string;
}) {
  return (
    <span className={cn(
      'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
      tones[tone], className,
    )}>
      {children}
    </span>
  );
}

export function StatusBadge({ status, className }: { status: string; className?: string }) {
  return <Badge tone={statusTone(status)} className={className}>{status}</Badge>;
}
