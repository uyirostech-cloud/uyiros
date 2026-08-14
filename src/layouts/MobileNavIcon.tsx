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
  followups: (
    <>
      <path d="M12 3a5 5 0 0 0-5 5v3.5c0 .8-.3 1.6-.9 2.2L5 15h14l-1.1-1.3a3 3 0 0 1-.9-2.2V8a5 5 0 0 0-5-5Z" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M9.5 18a2.5 2.5 0 0 0 5 0" strokeLinecap="round" />
    </>
  ),
  prescriptions: (
    <>
      <rect x="4.5" y="3.5" width="15" height="17" rx="2" />
      <path d="M12 8v6M9 11h6" strokeLinecap="round" />
    </>
  ),
  payments: (
    <>
      <rect x="3" y="6" width="18" height="13" rx="2" />
      <path d="M3 10h18" />
      <circle cx="17" cy="14.5" r="1.1" fill="currentColor" stroke="none" />
    </>
  ),
  inventory: (
    <>
      <path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5v-9Z" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M3.5 7.5 12 12m0 0 8.5-4.5M12 12v9" strokeLinecap="round" strokeLinejoin="round" />
    </>
  ),
  vendors: (
    <>
      <path d="M4 21V10M20 21V10M2 10l2-6h16l2 6M2 10h20" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M9 21v-6h6v6" strokeLinecap="round" strokeLinejoin="round" />
    </>
  ),
  finance: (
    <>
      <path d="M4 20V10M10 20V4M16 20v-7M4 20h16" strokeLinecap="round" strokeLinejoin="round" />
    </>
  ),
  expenses: (
    <>
      <circle cx="12" cy="12" r="8.5" />
      <path d="M8 12h8" strokeLinecap="round" />
    </>
  ),
  dailyClosing: (
    <>
      <rect x="5" y="10.5" width="14" height="9" rx="2" />
      <path d="M8 10.5V7a4 4 0 0 1 8 0v3.5" strokeLinecap="round" />
    </>
  ),
  reports: (
    <>
      <rect x="5" y="3.5" width="14" height="17" rx="2" />
      <path d="M8.5 14.5v3M12 11.5v6M15.5 8.5v9" strokeLinecap="round" />
    </>
  ),
  doctors: (
    <>
      <rect x="4.5" y="4.5" width="15" height="15" rx="2.5" />
      <circle cx="12" cy="10.3" r="2.2" />
      <path d="M8 17c.6-2 2-3 4-3s3.4 1 4 3" strokeLinecap="round" />
    </>
  ),
  users: (
    <>
      <circle cx="9" cy="8" r="3" />
      <path d="M3.5 19c.7-3.2 2.8-5 5.5-5s4.8 1.8 5.5 5" strokeLinecap="round" strokeLinejoin="round" />
      <circle cx="17" cy="9" r="2.3" />
      <path d="M15.3 12.2c2 .2 3.4 1.7 4.2 4.3" strokeLinecap="round" />
    </>
  ),
  branches: (
    <>
      <path d="M12 21s7-6.1 7-11.5A7 7 0 0 0 5 9.5C5 14.9 12 21 12 21Z" strokeLinecap="round" strokeLinejoin="round" />
      <circle cx="12" cy="9.5" r="2.3" />
    </>
  ),
  roles: (
    <>
      <path d="M12 3.5 19 6v6c0 5-3 7.8-7 8.5-4-.7-7-3.5-7-8.5V6l7-2.5Z" strokeLinecap="round" strokeLinejoin="round" />
    </>
  ),
  services: (
    <>
      <path
        d="M14.5 6.5a4 4 0 0 1-5 5L5 16l3 3 4.5-4.5a4 4 0 0 1 5-5l-2.3 2.3-2-2 2.3-2.3Z"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </>
  ),
  auditLogs: (
    <>
      <path d="M4 12a8 8 0 1 0 2.5-5.8" strokeLinecap="round" />
      <path d="M4 4v4.5H8.5" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M12 8v4l3 2" strokeLinecap="round" strokeLinejoin="round" />
    </>
  ),
  settings: (
    <>
      <circle cx="12" cy="12" r="3" />
      <path
        d="M12 3v3M12 18v3M21 12h-3M6 12H3M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1M18.4 18.4l-2.1-2.1M7.7 7.7 5.6 5.6"
        strokeLinecap="round"
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
