<?php
namespace App\Domain\Repositories;

final class UserRepository extends BaseRepository
{
    protected function table(): string
    {
        return 'users';
    }

    protected function hasSoftDeletes(): bool
    {
        return true;
    }

    protected function fillable(): array
    {
        // role_id is fillable here ONLY because this repository is reached exclusively from
        // user-management endpoints, which require the user.manage permission.
        return ['name', 'email', 'phone', 'password_hash', 'role_id', 'is_active',
                'all_branches', 'must_change_password', 'public_id'];
    }

    protected function sortable(): array
    {
        return ['id', 'name', 'email', 'created_at', 'last_login_at'];
    }

    public function listWithRole(array $filters, int $page, int $perPage): array
    {
        [$rows, $total] = $this->paginate($filters, $page, $perPage, 'name', 'asc',
            'id,name,email,phone,role_id,is_active,all_branches,last_login_at,created_at');
        if ($rows === []) {
            return [[], $total];
        }
        $roleIds = array_values(array_unique(array_filter(array_column($rows, 'role_id'))));
        $roles = [];
        if ($roleIds !== []) {
            $names = [];
            $params = [];
            foreach ($roleIds as $i => $rid) {
                $names[] = ':r' . $i;
                $params['r' . $i] = (int) $rid;
            }
            foreach (\App\Core\Database::select(
                'SELECT id, name, slug FROM roles WHERE id IN (' . implode(',', $names) . ')', $params
            ) as $r) {
                $roles[(int) $r['id']] = $r;
            }
        }
        $userIds = array_map('intval', array_column($rows, 'id'));
        $branchMap = [];
        if ($userIds !== []) {
            $names = [];
            $params = ['ctx_org' => $this->orgId()];
            foreach ($userIds as $i => $uid) {
                $names[] = ':u' . $i;
                $params['u' . $i] = $uid;
            }
            foreach (\App\Core\Database::select(
                'SELECT ub.user_id, b.id, b.name
                   FROM user_branches ub
                   JOIN branches b ON b.id = ub.branch_id
                  WHERE ub.user_id IN (' . implode(',', $names) . ') AND b.organization_id = :ctx_org',
                $params
            ) as $b) {
                $branchMap[(int) $b['user_id']][] = ['id' => (int) $b['id'], 'name' => $b['name']];
            }
        }
        foreach ($rows as &$row) {
            $rid = $row['role_id'] === null ? null : (int) $row['role_id'];
            $row['role'] = $rid !== null && isset($roles[$rid]) ? $roles[$rid] : null;
            $row['branches'] = $branchMap[(int) $row['id']] ?? [];
        }
        return [$rows, $total];
    }
}
