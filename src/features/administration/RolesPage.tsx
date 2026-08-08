import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Checkbox } from '@/components/ui/Input';
import { ErrorState } from '@/components/ui/States';
import { useAuth } from '@/hooks/useAuth';
import { rolesApi } from '@/services/api';
import { ApiError } from '@/types/api';
import { useToast } from '@/components/ui/Toast';
import { titleCase } from '@/utils/format';

export default function RolesPage() {
  const { can } = useAuth();
  const toast = useToast();
  const queryClient = useQueryClient();
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [draft, setDraft] = useState<Set<string>>(new Set());

  const rolesQuery = useQuery({ queryKey: ['roles'], queryFn: rolesApi.list });
  const permissionsQuery = useQuery({ queryKey: ['role-permissions'], queryFn: rolesApi.permissions });

  const selected = rolesQuery.data?.find((r) => r.id === selectedId) ?? rolesQuery.data?.[0] ?? null;

  useEffect(() => {
    if (selected) setDraft(new Set(selected.permissions));
  }, [selected?.id]); // eslint-disable-line react-hooks/exhaustive-deps

  const mutation = useMutation({
    mutationFn: () => rolesApi.update(selected!.id, { permissions: Array.from(draft) }),
    onSuccess: () => {
      toast.success('Role permissions updated');
      void queryClient.invalidateQueries({ queryKey: ['roles'] });
    },
    onError: (cause) => {
      if (cause instanceof ApiError) toast.error(cause.message);
    },
  });

  if (rolesQuery.isError || permissionsQuery.isError) {
    return <ErrorState onRetry={() => { void rolesQuery.refetch(); void permissionsQuery.refetch(); }} />;
  }

  const dirty = selected ? [...selected.permissions].sort().join() !== [...draft].sort().join() : false;

  return (
    <div>
      <h1 className="mb-4 text-lg font-semibold text-ink-900">Roles & permissions</h1>
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-[240px_1fr]">
        <Card className="h-fit">
          <CardBody className="space-y-1 p-2">
            {(rolesQuery.data ?? []).map((role) => (
              <button
                key={role.id}
                type="button"
                onClick={() => setSelectedId(role.id)}
                className={`flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm ${
                  (selected?.id === role.id) ? 'bg-brand-50 text-brand-800' : 'text-ink-700 hover:bg-ink-100'
                }`}
              >
                <span>
                  {role.name}
                  {role.is_system && <Badge tone="neutral" className="ml-2">System</Badge>}
                </span>
                <span className="text-xs text-ink-400">{role.user_count}</span>
              </button>
            ))}
          </CardBody>
        </Card>

        <Card>
          <CardHeader
            title={selected?.name ?? ''}
            subtitle={selected?.description ?? undefined}
            action={can('role.manage') && dirty ? (
              <Button size="sm" onClick={() => mutation.mutate()} loading={mutation.isPending}>Save changes</Button>
            ) : undefined}
          />
          <CardBody>
            {!selected || permissionsQuery.isLoading ? (
              <div className="h-64 animate-pulse rounded bg-ink-100" />
            ) : (
              <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                {Object.entries(permissionsQuery.data ?? {}).map(([group, permissions]) => (
                  <div key={group}>
                    <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-400">
                      {titleCase(group)}
                    </p>
                    <div className="space-y-2">
                      {permissions.map((permission) => (
                        <Checkbox
                          key={permission.slug}
                          label={permission.label}
                          checked={draft.has(permission.slug)}
                          disabled={!can('role.manage')}
                          onChange={(event) => {
                            setDraft((current) => {
                              const next = new Set(current);
                              if (event.target.checked) next.add(permission.slug);
                              else next.delete(permission.slug);
                              return next;
                            });
                          }}
                        />
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}
