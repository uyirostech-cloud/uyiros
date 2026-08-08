import { useQuery } from '@tanstack/react-query';
import {
  Area, AreaChart, Bar, BarChart, CartesianGrid, Cell, Legend, Pie, PieChart,
  ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import { CardSkeleton } from '@/components/ui/States';
import { useAuth } from '@/hooks/useAuth';
import { dashboardApi } from '@/services/api';
import { formatDayLabel, formatMoney, formatNumber } from '@/utils/format';
import type { DashboardCards } from '@/types/models';

const CHART_COLORS = ['#2a8781', '#45a29c', '#74c0ba', '#a8d9d5', '#1f6c68', '#d3ecea'];

interface CardDef {
  key: keyof DashboardCards;
  label: string;
  money?: boolean;
  tone?: 'default' | 'warning' | 'danger';
}

const CARD_DEFS: CardDef[] = [
  { key: 'appointments_today', label: "Today's Appointments" },
  { key: 'patients_today', label: "Today's Patients" },
  { key: 'waiting_patients', label: 'Waiting Patients', tone: 'warning' },
  { key: 'completed_consultations', label: 'Completed Consultations' },
  { key: 'new_leads', label: 'New Leads' },
  { key: 'followups_due', label: 'Follow-ups Due', tone: 'warning' },
  { key: 'collection_today', label: "Today's Collection", money: true },
  { key: 'outstanding_amount', label: 'Outstanding Amount', money: true, tone: 'danger' },
  { key: 'expenses_today', label: "Today's Expenses", money: true },
];

export default function DashboardPage() {
  const { user, activeBranchId } = useAuth();

  const summary = useQuery({
    queryKey: ['dashboard-summary', activeBranchId],
    queryFn: () => dashboardApi.summary(activeBranchId),
  });
  const charts = useQuery({
    queryKey: ['dashboard-charts', activeBranchId],
    queryFn: () => dashboardApi.charts(activeBranchId, 30),
  });

  return (
    <div>
      <div className="mb-5 flex flex-wrap items-baseline justify-between gap-2">
        <div>
          <h1 className="text-lg font-semibold text-ink-900">
            Welcome back{user ? `, ${user.name.split(' ')[0]}` : ''}
          </h1>
          <p className="text-sm text-ink-500">Here's what's happening across the clinic today.</p>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-3">
        {summary.isLoading
          ? Array.from({ length: 9 }).map((_, i) => <CardSkeleton key={i} />)
          : summary.isError
            ? (
              <div className="col-span-full rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                Could not load dashboard totals.
              </div>
            )
            : CARD_DEFS.map((def) => {
              const value = summary.data!.cards[def.key];
              return (
                <div key={def.key} className="card p-4">
                  <p className="text-xs font-medium text-ink-500">{def.label}</p>
                  <p className={
                    def.tone === 'danger' ? 'mt-1.5 text-2xl font-semibold text-red-600'
                      : def.tone === 'warning' ? 'mt-1.5 text-2xl font-semibold text-amber-600'
                        : 'mt-1.5 text-2xl font-semibold text-ink-900'
                  }>
                    {def.money ? formatMoney(value) : formatNumber(value)}
                  </p>
                </div>
              );
            })}
      </div>

      <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader title="Appointment trend" subtitle="Last 30 days" />
          <CardBody>
            {charts.isLoading ? (
              <div className="h-56 animate-pulse rounded bg-ink-100" />
            ) : (
              <ResponsiveContainer width="100%" height={224}>
                <AreaChart data={charts.data?.appointment_trend ?? []}>
                  <defs>
                    <linearGradient id="apptFill" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor="#2a8781" stopOpacity={0.25} />
                      <stop offset="100%" stopColor="#2a8781" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="#eceef1" vertical={false} />
                  <XAxis dataKey="day" tickFormatter={formatDayLabel} tick={{ fontSize: 11 }} interval={4} />
                  <YAxis tick={{ fontSize: 11 }} width={28} allowDecimals={false} />
                  <Tooltip labelFormatter={formatDayLabel} />
                  <Area type="monotone" dataKey="total" stroke="#2a8781" fill="url(#apptFill)" strokeWidth={2} />
                </AreaChart>
              </ResponsiveContainer>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="Revenue trend" subtitle="Last 30 days" />
          <CardBody>
            {charts.isLoading ? (
              <div className="h-56 animate-pulse rounded bg-ink-100" />
            ) : (
              <ResponsiveContainer width="100%" height={224}>
                <AreaChart data={charts.data?.revenue_trend ?? []}>
                  <defs>
                    <linearGradient id="revFill" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor="#1f6c68" stopOpacity={0.25} />
                      <stop offset="100%" stopColor="#1f6c68" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="#eceef1" vertical={false} />
                  <XAxis dataKey="day" tickFormatter={formatDayLabel} tick={{ fontSize: 11 }} interval={4} />
                  <YAxis tick={{ fontSize: 11 }} width={44} tickFormatter={(v) => formatMoney(v)} />
                  <Tooltip labelFormatter={formatDayLabel} formatter={(v: number) => formatMoney(v)} />
                  <Area type="monotone" dataKey="total" stroke="#1f6c68" fill="url(#revFill)" strokeWidth={2} />
                </AreaChart>
              </ResponsiveContainer>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="Lead conversion funnel" subtitle="Last 30 days" />
          <CardBody>
            {charts.isLoading ? (
              <div className="h-56 animate-pulse rounded bg-ink-100" />
            ) : (charts.data?.lead_funnel.length ?? 0) === 0 ? (
              <p className="py-16 text-center text-sm text-ink-500">No leads in this period yet.</p>
            ) : (
              <ResponsiveContainer width="100%" height={224}>
                <BarChart data={charts.data?.lead_funnel} layout="vertical" margin={{ left: 12 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#eceef1" horizontal={false} />
                  <XAxis type="number" tick={{ fontSize: 11 }} allowDecimals={false} />
                  <YAxis type="category" dataKey="status" tick={{ fontSize: 11 }} width={110} />
                  <Tooltip />
                  <Bar dataKey="total" fill="#45a29c" radius={[0, 4, 4, 0]} />
                </BarChart>
              </ResponsiveContainer>
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader title="Payment method breakdown" subtitle="Last 30 days" />
          <CardBody>
            {charts.isLoading ? (
              <div className="h-56 animate-pulse rounded bg-ink-100" />
            ) : (charts.data?.payment_methods.length ?? 0) === 0 ? (
              <p className="py-16 text-center text-sm text-ink-500">No payments recorded in this period.</p>
            ) : (
              <ResponsiveContainer width="100%" height={224}>
                <PieChart>
                  <Pie
                    data={charts.data?.payment_methods}
                    dataKey="total"
                    nameKey="method"
                    innerRadius={50}
                    outerRadius={80}
                    paddingAngle={2}
                  >
                    {(charts.data?.payment_methods ?? []).map((entry, index) => (
                      <Cell key={entry.method} fill={CHART_COLORS[index % CHART_COLORS.length]} />
                    ))}
                  </Pie>
                  <Legend iconType="circle" wrapperStyle={{ fontSize: 12 }} />
                  <Tooltip formatter={(v: number) => formatMoney(v)} />
                </PieChart>
              </ResponsiveContainer>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}
