<?php
namespace App\Http\Controllers;

use App\Core\Ctx;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;

/**
 * Every figure here comes from a live tenant-scoped query over the same tables the detail
 * screens read, so a card and its drill-down list always reconcile.
 */
final class DashboardController
{
    public function summary(Request $request, Ctx $ctx): Response
    {
        $branchIds = $ctx->branchFilter($this->requestedBranch($request));
        [$branchSql, $branchParams] = $this->branchClause($branchIds, 'a');
        $today = Str::today();
        $org = $ctx->orgId();

        $appointmentsToday = (int) Database::scalar(
            "SELECT COUNT(*) FROM appointments a
              WHERE a.organization_id = :o AND DATE(a.scheduled_at) = :d {$branchSql}",
            ['o' => $org, 'd' => $today] + $branchParams
        );

        $patientsToday = (int) Database::scalar(
            "SELECT COUNT(DISTINCT a.patient_id) FROM appointments a
              WHERE a.organization_id = :o AND DATE(a.scheduled_at) = :d
                AND a.status NOT IN ('Cancelled','No Show') {$branchSql}",
            ['o' => $org, 'd' => $today] + $branchParams
        );

        [$qBranchSql, $qBranchParams] = $this->branchClause($branchIds, 'q');
        $waiting = (int) Database::scalar(
            "SELECT COUNT(*) FROM queue_entries q
              WHERE q.organization_id = :o AND q.queue_date = :d AND q.status = 'waiting' {$qBranchSql}",
            ['o' => $org, 'd' => $today] + $qBranchParams
        );

        [$cBranchSql, $cBranchParams] = $this->branchClause($branchIds, 'c');
        $completedConsultations = (int) Database::scalar(
            "SELECT COUNT(*) FROM consultations c
              WHERE c.organization_id = :o AND c.status = 'finalized'
                AND DATE(c.finalized_at) = :d {$cBranchSql}",
            ['o' => $org, 'd' => $today] + $cBranchParams
        );

        [$lBranchSql, $lBranchParams] = $this->branchClause($branchIds, 'l', true);
        $newLeads = (int) Database::scalar(
            "SELECT COUNT(*) FROM leads l
              WHERE l.organization_id = :o AND DATE(l.created_at) = :d AND l.deleted_at IS NULL {$lBranchSql}",
            ['o' => $org, 'd' => $today] + $lBranchParams
        );
        $followupsDue = (int) Database::scalar(
            "SELECT COUNT(*) FROM leads l
              WHERE l.organization_id = :o AND l.deleted_at IS NULL
                AND l.next_followup_at IS NOT NULL AND DATE(l.next_followup_at) <= :d
                AND l.status NOT IN ('Converted','Lost','Not Interested') {$lBranchSql}",
            ['o' => $org, 'd' => $today] + $lBranchParams
        );

        [$pBranchSql, $pBranchParams] = $this->branchClause($branchIds, 'p');
        $collectionToday = (string) (Database::scalar(
            "SELECT COALESCE(SUM(p.amount), 0) FROM payments p
              WHERE p.organization_id = :o AND p.status = 'completed'
                AND DATE(p.paid_at) = :d {$pBranchSql}",
            ['o' => $org, 'd' => $today] + $pBranchParams
        ) ?? '0.00');
        [$rBranchSql, $rBranchParams] = $this->branchClause($branchIds, 'r');
        $refundsToday = (string) (Database::scalar(
            "SELECT COALESCE(SUM(r.amount), 0) FROM refunds r
              WHERE r.organization_id = :o AND DATE(r.refunded_at) = :d {$rBranchSql}",
            ['o' => $org, 'd' => $today] + $rBranchParams
        ) ?? '0.00');

        [$iBranchSql, $iBranchParams] = $this->branchClause($branchIds, 'i');
        $outstanding = (string) (Database::scalar(
            "SELECT COALESCE(SUM(i.balance_amount), 0) FROM invoices i
              WHERE i.organization_id = :o AND i.status IN ('Unpaid','Partially Paid') {$iBranchSql}",
            ['o' => $org] + $iBranchParams
        ) ?? '0.00');

        [$eBranchSql, $eBranchParams] = $this->branchClause($branchIds, 'e');
        $expensesToday = (string) (Database::scalar(
            "SELECT COALESCE(SUM(e.amount), 0) FROM expenses e
              WHERE e.organization_id = :o AND e.expense_date = :d
                AND e.status IN ('Approved','Paid') {$eBranchSql}",
            ['o' => $org, 'd' => $today] + $eBranchParams
        ) ?? '0.00');

        return Response::json([
            'cards' => [
                'appointments_today'      => $appointmentsToday,
                'patients_today'          => $patientsToday,
                'waiting_patients'        => $waiting,
                'completed_consultations' => $completedConsultations,
                'new_leads'               => $newLeads,
                'followups_due'           => $followupsDue,
                'collection_today'        => number_format((float) $collectionToday - (float) $refundsToday, 2, '.', ''),
                'outstanding_amount'      => $outstanding,
                'expenses_today'          => $expensesToday,
            ],
            'as_of'  => Str::now(),
            'branch_scope' => $branchIds,
        ]);
    }

    public function charts(Request $request, Ctx $ctx): Response
    {
        $branchIds = $ctx->branchFilter($this->requestedBranch($request));
        $org = $ctx->orgId();
        $days = max(7, min(90, (int) $request->query('days', 30)));
        $from = gmdate('Y-m-d', time() - ($days - 1) * 86400);

        [$aBranch, $aParams] = $this->branchClause($branchIds, 'a');
        $appointmentTrend = Database::select(
            "SELECT DATE(a.scheduled_at) AS day, COUNT(*) AS total
               FROM appointments a
              WHERE a.organization_id = :o AND DATE(a.scheduled_at) >= :f {$aBranch}
              GROUP BY DATE(a.scheduled_at) ORDER BY day",
            ['o' => $org, 'f' => $from] + $aParams
        );

        [$pBranch, $pParams] = $this->branchClause($branchIds, 'p');
        $patientTrend = Database::select(
            "SELECT p.registered_at AS day, COUNT(*) AS total
               FROM patients p
              WHERE p.organization_id = :o AND p.registered_at >= :f AND p.deleted_at IS NULL {$pBranch}
              GROUP BY p.registered_at ORDER BY day",
            ['o' => $org, 'f' => $from] + $pParams
        );

        [$payBranch, $payParams] = $this->branchClause($branchIds, 'pay');
        $revenueTrend = Database::select(
            "SELECT DATE(pay.paid_at) AS day, COALESCE(SUM(pay.amount), 0) AS total
               FROM payments pay
              WHERE pay.organization_id = :o AND pay.status = 'completed'
                AND DATE(pay.paid_at) >= :f {$payBranch}
              GROUP BY DATE(pay.paid_at) ORDER BY day",
            ['o' => $org, 'f' => $from] + $payParams
        );

        $methodBreakdown = Database::select(
            "SELECT pay.method, COALESCE(SUM(pay.amount), 0) AS total, COUNT(*) AS count
               FROM payments pay
              WHERE pay.organization_id = :o AND pay.status = 'completed'
                AND DATE(pay.paid_at) >= :f {$payBranch}
              GROUP BY pay.method ORDER BY total DESC",
            ['o' => $org, 'f' => $from] + $payParams
        );

        [$lBranch, $lParams] = $this->branchClause($branchIds, 'l', true);
        $leadFunnel = Database::select(
            "SELECT l.status, COUNT(*) AS total
               FROM leads l
              WHERE l.organization_id = :o AND DATE(l.created_at) >= :f AND l.deleted_at IS NULL {$lBranch}
              GROUP BY l.status",
            ['o' => $org, 'f' => $from] + $lParams
        );

        return Response::json([
            'appointment_trend' => $this->fillDays($appointmentTrend, $from, $days),
            'patient_trend'     => $this->fillDays($patientTrend, $from, $days),
            'revenue_trend'     => $this->fillDays($revenueTrend, $from, $days, true),
            'payment_methods'   => array_map(static fn ($r) => [
                'method' => $r['method'], 'total' => (string) $r['total'], 'count' => (int) $r['count'],
            ], $methodBreakdown),
            'lead_funnel'       => array_map(static fn ($r) => [
                'status' => $r['status'], 'total' => (int) $r['total'],
            ], $leadFunnel),
            'from' => $from,
            'days' => $days,
        ]);
    }

    private function requestedBranch(Request $request): ?int
    {
        $branchId = $request->query('branch_id');
        return ($branchId === null || $branchId === '') ? null : (int) $branchId;
    }

    /** @return array{0:string,1:array} */
    private function branchClause(?array $branchIds, string $alias, bool $nullable = false): array
    {
        if ($branchIds === null || $branchIds === []) {
            return ['', []];
        }
        $names = [];
        $params = [];
        foreach (array_values($branchIds) as $i => $id) {
            $key = $alias . '_b' . $i;
            $names[] = ':' . $key;
            $params[$key] = (int) $id;
        }
        $in = "{$alias}.branch_id IN (" . implode(', ', $names) . ')';
        if ($nullable) {
            $in = "({$in} OR {$alias}.branch_id IS NULL)";
        }
        return [' AND ' . $in, $params];
    }

    private function fillDays(array $rows, string $from, int $days, bool $money = false): array
    {
        $byDay = [];
        foreach ($rows as $r) {
            $byDay[(string) $r['day']] = $money
                ? number_format((float) $r['total'], 2, '.', '')
                : (int) $r['total'];
        }
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = gmdate('Y-m-d', strtotime($from) + $i * 86400);
            $out[] = ['day' => $day, 'total' => $byDay[$day] ?? ($money ? '0.00' : 0)];
        }
        return $out;
    }
}
