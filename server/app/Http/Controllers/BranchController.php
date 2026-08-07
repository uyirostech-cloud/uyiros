<?php
namespace App\Http\Controllers;

use App\Core\Ctx;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Validator;
use App\Domain\Repositories\BranchRepository;
use App\Domain\Services\AuditService;

final class BranchController
{
    public function index(Request $request, Ctx $ctx): Response
    {
        $repo = new BranchRepository($ctx);
        [$page, $perPage] = $request->pagination(50);
        $filters = [];
        if (($q = trim((string) $request->query('q', ''))) !== '') {
            $filters['__raw'][] = ['(name LIKE :q1 OR code LIKE :q2 OR city LIKE :q3)', [
                'q1' => '%' . BranchRepository::escapeLike($q) . '%',
                'q2' => '%' . BranchRepository::escapeLike($q) . '%',
                'q3' => '%' . BranchRepository::escapeLike($q) . '%',
            ]];
        }
        if (($active = $request->query('is_active')) !== null && $active !== '') {
            $filters['is_active'] = (int) ((string) $active === '1' || $active === 'true');
        }
        // Users only ever see the branches they are assigned to.
        if (!$ctx->allBranches) {
            $filters['id'] = ['in', $ctx->branchIds ?: [0]];
        }
        [$rows, $total] = $repo->paginate($filters, $page, $perPage, 'name', 'asc');
        return Response::paginated(array_map([$this, 'present'], $rows), $total, $page, $perPage);
    }

    public function show(Request $request, Ctx $ctx): Response
    {
        $branch = (new BranchRepository($ctx))->findOrFail($request->intParam('id'));
        $ctx->assertBranch((int) $branch['id']);
        return Response::json($this->present($branch));
    }

    public function store(Request $request, Ctx $ctx): Response
    {
        $data = $this->validate($request, $ctx);
        $repo = new BranchRepository($ctx);

        $clash = Database::scalar(
            'SELECT id FROM branches WHERE organization_id = :o AND code = :c AND deleted_at IS NULL',
            ['o' => $ctx->orgId(), 'c' => $data['code']]
        );
        if ($clash !== null) {
            throw HttpException::validation(['code' => 'This branch code is already in use']);
        }

        $id = $repo->create($data);
        $branch = $repo->findOrFail($id);
        AuditService::log($ctx, 'branch.created', 'branch', $id, null, $this->present($branch));
        return Response::json($this->present($branch), 201);
    }

    public function update(Request $request, Ctx $ctx): Response
    {
        $repo = new BranchRepository($ctx);
        $id = $request->intParam('id');
        $before = $repo->findOrFail($id);
        $data = $this->validate($request, $ctx, $id);

        if (isset($data['code'])) {
            $clash = Database::scalar(
                'SELECT id FROM branches WHERE organization_id = :o AND code = :c AND id <> :id AND deleted_at IS NULL',
                ['o' => $ctx->orgId(), 'c' => $data['code'], 'id' => $id]
            );
            if ($clash !== null) {
                throw HttpException::validation(['code' => 'This branch code is already in use']);
            }
        }

        $after = $repo->update($id, $data);
        [$old, $new] = AuditService::diff($before, $after);
        if ($new !== []) {
            AuditService::log($ctx, 'branch.updated', 'branch', $id, $old, $new);
        }
        return Response::json($this->present($after));
    }

    public function destroy(Request $request, Ctx $ctx): Response
    {
        $repo = new BranchRepository($ctx);
        $id = $request->intParam('id');
        $branch = $repo->findOrFail($id);

        $inUse = (int) Database::scalar(
            'SELECT COUNT(*) FROM appointments WHERE organization_id = :o AND branch_id = :b',
            ['o' => $ctx->orgId(), 'b' => $id]
        );
        if ($inUse > 0) {
            // Never destroy history — deactivate instead.
            $repo->update($id, ['is_active' => 0]);
            AuditService::log($ctx, 'branch.deactivated', 'branch', $id, $this->present($branch), null);
            return Response::json(['message' => 'Branch has activity and was deactivated instead of deleted']);
        }
        $repo->softDelete($id);
        AuditService::log($ctx, 'branch.deleted', 'branch', $id, $this->present($branch), null);
        return Response::json(['message' => 'Branch removed']);
    }

    private function validate(Request $request, Ctx $ctx, ?int $id = null): array
    {
        $payload = $request->only([
            'name', 'code', 'phone', 'email', 'address', 'city', 'state', 'pin',
            'opening_time', 'closing_time', 'working_days', 'is_active',
        ]);
        $required = $id === null ? 'required|' : 'nullable|';
        $data = Validator::make($payload, [
            'name'         => $required . 'string|minlen:2|maxlen:120',
            'code'         => $required . 'string|minlen:2|maxlen:30',
            'phone'        => 'nullable|phone|maxlen:30',
            'email'        => 'nullable|email|maxlen:190',
            'address'      => 'nullable|string|maxlen:255',
            'city'         => 'nullable|string|maxlen:80',
            'state'        => 'nullable|string|maxlen:80',
            'pin'          => 'nullable|string|maxlen:12',
            'opening_time' => 'nullable|time',
            'closing_time' => 'nullable|time',
            'working_days' => 'nullable|string|maxlen:30',
            'is_active'    => 'nullable|boolean',
        ], $ctx);
        if (isset($data['code'])) {
            $data['code'] = strtoupper((string) $data['code']);
        }
        return $data;
    }

    private function present(array $row): array
    {
        unset($row['organization_id'], $row['deleted_at']);
        $row['id'] = (int) $row['id'];
        $row['is_active'] = (int) $row['is_active'] === 1;
        return $row;
    }
}
