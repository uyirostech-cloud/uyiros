import { useQuery } from '@tanstack/react-query';
import { CardSkeleton } from '@/components/ui/States';
import { platformApi } from '@/services/api';
import { formatNumber } from '@/utils/format';

export default function PlatformOverviewPage() {
  const query = useQuery({ queryKey: ['platform-overview'], queryFn: platformApi.overview });

  const cards = query.data ? [
    { label: 'Total clinics', value: query.data.organizations.total },
    { label: 'Active clinics', value: query.data.organizations.active },
    { label: 'Trial clinics', value: query.data.organizations.trial },
    { label: 'Suspended clinics', value: query.data.organizations.suspended },
    { label: 'Clinic users', value: query.data.clinic_users },
    { label: 'Active plans', value: query.data.plans },
    { label: 'Branches', value: query.data.branches },
  ] : [];

  return (
    <div>
      <h1 className="mb-4 text-lg font-semibold text-ink-900">Platform overview</h1>
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        {query.isLoading
          ? Array.from({ length: 7 }).map((_, i) => <CardSkeleton key={i} />)
          : cards.map((card) => (
            <div key={card.label} className="card p-4">
              <p className="text-xs font-medium text-ink-500">{card.label}</p>
              <p className="mt-1.5 text-2xl font-semibold text-ink-900">{formatNumber(card.value)}</p>
            </div>
          ))}
      </div>
    </div>
  );
}
