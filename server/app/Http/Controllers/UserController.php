<?php
namespace App\Http\Controllers;

use App\Core\Ctx;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Validator;
use App\Domain\Repositories\UserRepository;
use App\Domain\Services\AuditService;
use App\Domain\Services\AuthService;

final class UserController
{
    public function index(Request $request, Ctx $ctx): Response
    {
        [$page, $perPage] = $request->pagination(25);
        $filters = [];
        if (($q = trim((string) $request->query('q', ''))) !== '') {
            $like = '%' . UserRepository::escapeLike($q) . '%';
            $filters['__raw'][] = ['(name LIKE :q1 OR email LIKE :q2)', ['q1' => $like, 'q2' => $like]];
        }
        if (($role = $request->query('role_id')) !== null && $role !== '') {
            $filters['role_id'] = (int) $role;
        }
        if (($active = $request->query('is_active')) !== null && $active !== '') {
            $filters['is_active'] = (int) ((string) $active === '1' || $active === 'true');
        }
        // Platform admins are never listed inside a clinic.
        $filters['is_platform_admin'] = 0;

        [$rows, $total] = (new UserRepository($ctx))->listWithRole($filters, $page, $perPage);
        return Response::paginated(array_map([$this, 'present'], $rows), $total, $page, $perPage);
    }

    public function show(Request $request, Ctx $ctx): Response
    {
        $repo = new UserRepository($ctx);
        $user = $repo->findOrFail($request->intParam('id'),
            'id,name,email,phone,role_id,is_active,all_branches,last_login_at,created_at,is_platform_admin');
        if ((int) $user['is_platform_admin'] === 1) {
            throw HttpException::notFound('User not found');
        }
        $user['branches'] = Database::select(
            'SELECT b.id, b.name FROM user_branches ub
               JOIN branches b ON b.id = ub.branch_id
              WHERE ub.user_id = :u AND b.organization_id = :o',
            ['u' => $user['id'], 'o' => $ctx->orgId()]
        );
        $user['role'] = $user['role_id'] === null ? null : Database::selectOne(
            'SELECT id, name, slug FROM roles WHERE id = :r', ['r' => $user['role_id']]
        );
        return Response::json($this->present($user));
    }

    public function store(Request $request, Ctx $ctx): Response
    {
        $data = $this->validate($request, $ctx);

        if (Database::scalar('SELECT id FROM users WHERE email = :e', ['e' => $data['email']]) !== null) {
            throw HttpException::validation(['email' => 'This email address is already registered']);
        }
        $this->assertRoleBelongsToOrg($ctx, (int) $data['role_id']);
        $branchIds = $this->validateBranches($ctx, $request->input('branch_ids', []), (bool) ($data['all_branches'] ?? false));
        AuthService::validatePasswordStrength((string) $data['password']);

        $repo = new UserRepository($ctx);
        $id = Database::transaction(function () use ($repo, $data, $branchIds, $ctx) {
            $userId = $repo->create([
                'name'                 => $data['name'],
                'email'                => $data['email'],
                'phone'                => $data['phone'] ?? null,
                'password_hash'        => AuthService::hash((string) $data['password']),
                'role_id'              => (int) $data['role_id'],
                'is_active'            => $data['is_active'] ?? 1,
                'all_branches'         => $data['all_branches'] ?? 0,
                'must_change_password' => 1,
                'public_id'            => Str::ulid(),
            ]);
            $this->syncBranches($userId, $branchIds);
            return $userId;
        });

        $user = $repo->findOrFail($id, 'id,name,email,phone,role_id,is_active,all_branches,created_at');
        AuditService::log($ctx, 'user.created', 'user', $id, null,
            ['name' => $data['name'], 'email' => $data['email'], 'role_id' => $data['role_id']]);
        return Response::json($this->present($user), 201);
    }

    public function update(Request $request, Ctx $ctx): Response
    {
        $repo = new UserRepository($ctx);
        $id = $request->intParam('id');
        $before = $repo->findOrFail($id, 'id,name,email,phone,role_id,is_active,all_branches,is_platform_admin');
        if ((int) $before['is_platform_admin'] === 1) {
            throw HttpException::notFound('User not found');
        }

        $data = $this->validate($request, $ctx, $id);
        if (isset($data['email'])
            && Database::scalar('SELECT id FROM users WHERE email = :e AND id <> :id',
                ['e' => $data['email'], 'id' => $id]) !== null) {
            throw HttpException::validation(['email' => 'This email address is already registered']);
        }
        if (isset($data['role_id'])) {
            $this->assertRoleBelongsToOrg($ctx, (int) $data['role_id']);
        }

        // An organization must never be able to lock itself out of user management.
        if ($id === $ctx->userId && array_key_exists('is_active', $data) && (int) $data['is_active'] === 0) {
            throw HttpException::badRequest('You cannot deactivate your own account', 'SELF_DEACTIVATION');
        }
        if (isset($data['role_id']) && $id === $ctx->userId && (int) $data['role_id'] !== (int) $before['role_id']) {
            throw HttpException::badRequest('You cannot change your own role', 'SELF_ROLE_CHANGE');
        }

        $update = array_intersect_key($data, array_flip(['name', 'email', 'phone', 'role_id', 'is_active', 'all_branches']));
        $after = Database::transaction(function () use ($repo, $id, $update, $request, $ctx, $data) {
            $result = $repo->update($id, $update);
            if ($request->input('branch_ids') !== null) {
                $branchIds = $this->validateBranches(
                    $ctx,
                    $request->input('branch_ids', []),
                    (bool) ($data['all_branches'] ?? (int) $result['all_branches'] === 1)
                );
                $this->syncBranches($id, $branchIds);
            }
            return $result;
        });

        // Deactivating a user must end their sessions immediately.
        if (array_key_exists('is_active', $update) && (int) $update['is_active'] === 0) {
            AuthService::revokeAllForUser($id);
        }

        [$old, $new] = AuditService::diff($before, $after);
        if ($new !== []) {
            AuditService::log($ctx, 'user.updated', 'user', $id, $old, $new);
        }
        return Response::json($this->present($after));
    }

    public function deactivate(Request $request, Ctx $ctx): Response
    {
        $id = $request->intParam('id');
        if ($id === $ctx->userId) {
            throw HttpException::badRequest('You cannot deactivate your own account', 'SELF_DEACTIVATION');
        }
        $repo = new UserRepository($ctx);
        $repo->findOrFail($id, 'id');
        $repo->update($id, ['is_active' => 0]);
        AuthService::revokeAllForUser($id);
        AuditService::log($ctx, 'user.deactivated', 'user', $id);
        return Response::json(['message' => 'User deactivated']);
    }

    public function resetPassword(Request $request, Ctx $ctx): Response
    {
        $id = $request->intParam('id');
        $repo = new UserRepository($ctx);
        $repo->findOrFail($id, 'id');

        $data = Validator::make($request->only(['password']), ['password' => 'required|string|maxlen:200']);
        AuthService::validatePasswordStrength((string) $data['password']);

        Database::run(
            'UPDATE users SET password_hash = :h, must_change_password = 1, failed_attempts = 0,
                    locked_until = NULL, updated_at = :u WHERE id = :id AND organization_id = :o',
            ['h' => AuthService::hash((string) $data['password']), 'u' => Str::now(),
             'id' => $id, 'o' => $ctx->orgId()]
        );
        AuthService::revokeAllForUser($id);
        AuditService::log($ctx, 'user.password_reset', 'user', $id);
        return Response::json(['message' => 'Password reset. The user must change it at next sign-in.']);
    }

    private function validate(Request $request, Ctx $ctx, ?int $id = null): array
    {
        $payload = $request->only(['name', 'email', 'phone', 'password', 'role_id', 'is_active', 'all_branches']);
        $required = $id === null ? 'required|' : 'nullable|';
        return Validator::make($payload, [
            'name'         => $required . 'string|minlen:2|maxlen:120',
            'email'        => $required . 'email|maxlen:190',
            'phone'        => 'nullable|phone|maxlen:30',
            'password'     => ($id === null ? 'required|' : 'nullable|') . 'string|maxlen:200',
            'role_id'      => $required . 'int',
            'is_active'    => 'nullable|boolean',
            'all_branches' => 'nullable|boolean',
        ], $ctx);
    }

    /** A role must belong to this organization (or be a system template) — never another clinic's. */
    private function assertRoleBelongsToOrg(Ctx $ctx, int $roleId): void
    {
        $ok = Database::scalar(
            'SELECT id FROM roles WHERE id = :r AND organization_id = :o LIMIT 1',
            ['r' => $roleId, 'o' => $ctx->orgId()]
        );
        if ($ok === null) {
            throw HttpException::validation(['role_id' => 'Selected role does not exist']);
        }
    }

    /** @return int[] */
    private function validateBranches(Ctx $ctx, mixed $branchIds, bool $allBranches): array
    {
        if ($allBranches) {
            return [];
        }
        if (!is_array($branchIds)) {
            return [];
        }
        $ids = array_values(array_unique(array_map('intval', $branchIds)));
        if ($ids === []) {
            return [];
        }
        $names = [];
        $params = ['o' => $ctx->orgId()];
        foreach ($ids as $i => $bid) {
            $names[] = ':b' . $i;
            $params['b' . $i] = $bid;
        }
        $valid = array_map('intval', array_column(Database::select(
            'SELECT id FROM branches WHERE organization_id = :o AND deleted_at IS NULL AND id IN ('
            . implode(',', $names) . ')', $params
        ), 'id'));
        $invalid = array_diff($ids, $valid);
        if ($invalid !== []) {
            throw HttpException::validation(['branch_ids' => 'One or more selected branches do not exist']);
        }
        return $valid;
    }

    private function syncBranches(int $userId, array $branchIds): void
    {
        Database::run('DELETE FROM user_branches WHERE user_id = :u', ['u' => $userId]);
        foreach ($branchIds as $bid) {
            Database::run(
                'INSERT INTO user_branches (user_id, branch_id, created_at) VALUES (:u, :b, :c)',
                ['u' => $userId, 'b' => $bid, 'c' => Str::now()]
            );
        }
    }

    private function present(array $row): array
    {
        unset($row['organization_id'], $row['password_hash'], $row['deleted_at'], $row['is_platform_admin']);
        $row['id'] = (int) $row['id'];
        if (array_key_exists('role_id', $row)) {
            $row['role_id'] = $row['role_id'] === null ? null : (int) $row['role_id'];
        }
        foreach (['is_active', 'all_branches'] as $k) {
            if (array_key_exists($k, $row)) {
                $row[$k] = (int) $row[$k] === 1;
            }
        }
        return $row;
    }
}
