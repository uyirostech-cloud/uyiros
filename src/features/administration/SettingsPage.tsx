import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { ErrorState } from '@/components/ui/States';
import { useAuth } from '@/hooks/useAuth';
import { settingsApi } from '@/services/api';
import { ApiError } from '@/types/api';
import { useToast } from '@/components/ui/Toast';

export default function SettingsPage() {
  const { can } = useAuth();
  const toast = useToast();
  const queryClient = useQueryClient();
  const query = useQuery({ queryKey: ['settings'], queryFn: settingsApi.get });

  const [org, setOrg] = useState({ name: '', email: '', phone: '', address: '', city: '', state: '', pin: '' });
  const [sequences, setSequences] = useState<Record<string, { prefix: string; padding: number }>>({});

  useEffect(() => {
    if (!query.data) return;
    setOrg({
      name: query.data.organization.name ?? '', email: query.data.organization.email ?? '',
      phone: query.data.organization.phone ?? '', address: query.data.organization.address ?? '',
      city: query.data.organization.city ?? '', state: query.data.organization.state ?? '',
      pin: query.data.organization.pin ?? '',
    });
    setSequences(Object.fromEntries(
      query.data.sequences.map((s) => [s.key, { prefix: s.prefix, padding: s.padding }]),
    ));
  }, [query.data]);

  const orgMutation = useMutation({
    mutationFn: () => settingsApi.updateOrganization(org),
    onSuccess: () => { toast.success('Clinic information saved'); void queryClient.invalidateQueries({ queryKey: ['settings'] }); },
    onError: (cause) => { if (cause instanceof ApiError) toast.error(cause.message); },
  });

  const numberingMutation = useMutation({
    mutationFn: (key: string) => settingsApi.updateNumbering(key, sequences[key]!.prefix, sequences[key]!.padding),
    onSuccess: () => { toast.success('Numbering format saved'); void queryClient.invalidateQueries({ queryKey: ['settings'] }); },
    onError: (cause) => { if (cause instanceof ApiError) toast.error(cause.message); },
  });

  if (query.isError) return <ErrorState onRetry={() => query.refetch()} />;
  const readOnly = !can('settings.manage');

  return (
    <div className="space-y-4">
      <h1 className="text-lg font-semibold text-ink-900">Settings</h1>

      <Card>
        <CardHeader title="Clinic information" subtitle="Shown on invoices, prescriptions and printed documents." />
        <CardBody>
          {query.isLoading ? <div className="h-40 animate-pulse rounded bg-ink-100" /> : (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Input label="Clinic name" value={org.name} disabled={readOnly}
                     onChange={(e) => setOrg((o) => ({ ...o, name: e.target.value }))} />
              <Input label="Email" value={org.email} disabled={readOnly}
                     onChange={(e) => setOrg((o) => ({ ...o, email: e.target.value }))} />
              <Input label="Phone" value={org.phone} disabled={readOnly}
                     onChange={(e) => setOrg((o) => ({ ...o, phone: e.target.value }))} />
              <Input label="City" value={org.city} disabled={readOnly}
                     onChange={(e) => setOrg((o) => ({ ...o, city: e.target.value }))} />
              <Input label="State" value={org.state} disabled={readOnly}
                     onChange={(e) => setOrg((o) => ({ ...o, state: e.target.value }))} />
              <Input label="PIN code" value={org.pin} disabled={readOnly}
                     onChange={(e) => setOrg((o) => ({ ...o, pin: e.target.value }))} />
              <Input label="Address" value={org.address} disabled={readOnly} wrapperClassName="sm:col-span-2"
                     onChange={(e) => setOrg((o) => ({ ...o, address: e.target.value }))} />
            </div>
          )}
        </CardBody>
        {!readOnly && (
          <div className="flex justify-end border-t border-ink-200 px-5 py-3.5">
            <Button size="sm" onClick={() => orgMutation.mutate()} loading={orgMutation.isPending}>Save</Button>
          </div>
        )}
      </Card>

      <Card>
        <CardHeader title="Numbering formats" subtitle="Business numbers are generated on the server, never in the browser." />
        <CardBody className="space-y-3">
          {query.isLoading ? <div className="h-40 animate-pulse rounded bg-ink-100" /> : (
            (query.data?.sequences ?? []).map((seq) => (
              <div key={seq.key} className="flex flex-wrap items-end gap-3 border-b border-ink-100 pb-3 last:border-0 last:pb-0">
                <div className="w-28 text-sm font-medium capitalize text-ink-700">{seq.key}</div>
                <Input
                  label="Prefix" wrapperClassName="w-28"
                  value={sequences[seq.key]?.prefix ?? ''} disabled={readOnly}
                  onChange={(e) => setSequences((s) => ({ ...s, [seq.key]: { ...s[seq.key]!, prefix: e.target.value.toUpperCase() } }))}
                />
                <Input
                  label="Digits" type="number" min={3} max={10} wrapperClassName="w-24"
                  value={sequences[seq.key]?.padding ?? 6} disabled={readOnly}
                  onChange={(e) => setSequences((s) => ({ ...s, [seq.key]: { ...s[seq.key]!, padding: Number(e.target.value) } }))}
                />
                <p className="pb-2 text-xs text-ink-400">next: {seq.preview}</p>
                {!readOnly && (
                  <Button size="sm" variant="secondary" onClick={() => numberingMutation.mutate(seq.key)} loading={numberingMutation.isPending}>
                    Save
                  </Button>
                )}
              </div>
            ))
          )}
        </CardBody>
      </Card>
    </div>
  );
}
