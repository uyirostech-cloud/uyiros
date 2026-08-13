import type { MobileIconKey } from '@/config/navigation';

const paths: Record<MobileIconKey, React.ReactNode> = {
  dashboard: (
    <>
      <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" />
      <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" />
      <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" />
      <rect x="13.5" y="13.5" width="7" height="7" rx="1.5" />
    </>
  ),
  patients: (
    <>
      <circle cx="12" cy="8" r="3.5" />
      <path d="M4.5 20c1.2-4 4-6 7.5-6s6.3 2 7.5 6" strokeLinecap="round" strokeLinejoin="round" />
    </>
  ),
  appointments: (
    <>
      <rect x="3.5" y="4.5" width="17" height="16" rx="2" />
      <path d="M3.5 9.5h17M8 3v3M16 3v3" strokeLinecap="round" />
    </>
  ),
  queue: (
    <>
      <path d="M4 6h16M4 12h16M4 18h10" strokeLinecap="round" />
    </>
  ),
  leads: (
    <>
      <path d="M4 4h16l-6 8v6l-4 2v-8L4 4Z" strokeLinecap="round" strokeLinejoin="round" />
    </>
  ),
  invoices: (
    <>
      <path d="M6 3.5h12v17l-2.5-1.5L13 20.5 10.5 19 8 20.5 5.5 19V6" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M8.5 9h7M8.5 12.5h7" strokeLinecap="round" />
    </>
  ),
  tasks: (
    <>
      <rect x="3.5" y="3.5" width="17" height="17" rx="2.5" />
      <path d="M8 12l2.5 2.5L16 9" strokeLinecap="round" strokeLinejoin="round" />
    </>
  ),
  consultations: (
    <>
      <path
        d="M12 3.5c-4.7 0-8.5 3.4-8.5 7.6 0 2.2 1 4.1 2.7 5.5-.1 1.2-.5 2.3-1.2 3.2 1.5-.1 2.9-.6 4-1.4 1 .3 2 .4 3 .4 4.7 0 8.5-3.4 8.5-7.7S16.7 3.5 12 3.5Z"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </>
  ),
};

export function MobileNavIcon({ icon, className }: { icon: MobileIconKey; className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" className={className} stroke="currentColor" strokeWidth="1.8">
      {paths[icon]}
    </svg>
  );
}

export function MoreIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" className={className} stroke="currentColor" strokeWidth="1.8">
      <circle cx="5" cy="12" r="1.4" fill="currentColor" stroke="none" />
      <circle cx="12" cy="12" r="1.4" fill="currentColor" stroke="none" />
      <circle cx="19" cy="12" r="1.4" fill="currentColor" stroke="none" />
    </svg>
  );
}
