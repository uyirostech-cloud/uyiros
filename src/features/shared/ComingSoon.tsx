import { Card, CardBody } from '@/components/ui/Card';

export function ComingSoon({ title, phase }: { title: string; phase: string }) {
  return (
    <div>
      <h1 className="mb-4 text-lg font-semibold text-ink-900">{title}</h1>
      <Card>
        <CardBody className="py-12 text-center">
          <p className="text-sm text-ink-500">This module is scheduled for <strong>{phase}</strong>.</p>
          <p className="mt-1 text-xs text-ink-400">
            The database schema and permissions for it already exist — see docs/DEVELOPMENT_PLAN.md.
          </p>
        </CardBody>
      </Card>
    </div>
  );
}
