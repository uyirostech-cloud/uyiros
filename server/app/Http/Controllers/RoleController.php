<?php
namespace App\Http\Controllers;

use App\Core\Ctx;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Validator;
use App\Domain\Services\AuditService;
use App\Support\Permissions;

final class RoleController
{
    public function index(Request $request, Ctx $ctx): Response
    {
        $roles = Database::select(
            'SELECT r.id, r.name, r.slug, r.description, r.is_system,
                    (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id AND u.deleted_at IS NULL) AS user_count
               FROM roles r
              WHERE r.organization_id = :o
              ORDER BY r.is_system DESC, r.name',
            ['o' => $ctx->orgId()]
        );
        $permissionsByRole = [];
        foreach (Database::select(
            'SELECT rp.role_id, p.slug
               FROM role_permissions rp
               JOIN permissions p ON p.id = rp.permission_id
               JOIN roles r ON r.id = rp.role_id
              WHERE r.organization_id = :o',
            ['o' => $ctx->orgId()]
        ) as $row) {
            $permissionsByRole[(int) $row['role_id']][] = $row['slug'];
        }

        return Response::json(array_map(static fn ($r) => [
            'id'          => (int) $r['id'],
            'name'        => $r['name'],
            'slug'        => $r['slug'],
            'description' => $r['description'],
            'is_system'   => (int) $r['is_system'] === 1,
            'user_count'  => (int) $r['user_count'],
            'permissions' => $permissionsByRole[(int) $r['id']] ?? [],
        ], $roles));
    }

    public function permissions(Request $request, Ctx $ctx): Response
    {
        $grouped = [];
        foreach (Permissions::catalogue() as $slug => [$group, $label]) {
            if ($group === 'platform') {
                continue;                       // clinic users never see platform permissions
            }
            $grouped[$group][] = ['slug' => $slug, 'label' => $label];
        }
        return Response::json($grouped);
    }

    public function store(Request $request, Ctx $ctx): Response
    {
        $data = Validator::make($request->only(['name', 'description', 'permissions']), [
            'name'        => 'required|string|minlen:2|maxlen:80',
            'description' => 'nullable|string|maxlen:255',
            'permissions' => 'required|array',
        ], $ctx);

        $slug = Str::slug((string) $data['name']);
        if (Database::scalar('SELECT id FROM roles WHERE organization_id = :o AND slug = :s',
                ['o' => $ctx->orgId(), 's' => $slug]) !== null) {
            throw HttpException::validation(['name' => 'A role with this name already exists']);
        }
        $permissionIds = $this->resolvePermissions((array) $data['permissions']);

        $id = Database::transaction(function () use ($ctx, $data, $slug, $permissionIds) {
            $roleId = Database::insert(
                'INSERT INTO roles (organization_id, name, slug, description, is_system, created_at)
                 VALUES (:o, :n, :s, :d, 0, :c)',
                ['o' => $ctx->orgId(), 'n' => $data['name'], 's' => $slug,
                 'd' => $data['description'] ?? null, 'c' => Str::now()]
            );
            $this->syncPermissions($roleId, $permissionIds);
            return $roleId;
        });

        AuditService::log($ctx, 'role.created', 'role', $id, null,
            ['name' => $data['name'], 'permissions' => $data['permissions']]);
        return Response::json(['id' => $id, 'name' => $data['name'], 'slug' => $slug], 201);
    }

    public function update(Request $request, Ctx $ctx): Response
    {
        $id = $request->intParam('id');
        $role = $this->findRole($ctx, $id);

        $data = Validator::make($request->only(['name', 'description', 'permissions']), [
            'name'        => 'nullable|string|minlen:2|maxlen:80',
            'description' => 'nullable|string|maxlen:255',
            'permissions' => 'nullable|array',
        ], $ctx);

        $oldPermissions = $this->rolePermissions($id);

        if (array_key_exists('permissions', $data) && $data['permissions'] !== null) {
            $newPermissions = array_values(array_unique((array) $data['permissions']));
            $this->assertOrgKeepsAnAdmin($ctx, $id, $newPermissions);
            $permissionIds = $this->resolvePermissions($newPermissions);
        } else {
            $newPermissions = $oldPermissions;
            $permissionIds = null;
        }

        Database::transaction(function () use ($ctx, $id, $data, $permissionIds, $role) {
            $sets = [];
            $params = ['id' => $id, 'o' => $ctx->orgId()];
            // System role names stay fixed so the seeded matrix remains recognisable.
            if (isset($data['name']) && (int) $role['is_system'] === 0) {
                $sets[] = 'name = :n';
                $params['n'] = $data['name'];
            }
            if (array_key_exists('description', $data)) {
                $sets[] = 'description = :d';
                $params['d'] = $data['description'];
            }
            if ($sets !== []) {
                $sets[] = 'updated_at = :u';
                $params['u'] = Str::now();
                Database::run('UPDATE roles SET ' . implode(', ', $sets)
                    . ' WHERE id = :id AND organization_id = :o', $params);
            }
            if ($permissionIds !== null) {
                $this->syncPermissions($id, $permissionIds);
            }
        });

        if ($permissionIds !== null) {
            sort($oldPermissions);
            $sortedNew = $newPermissions;
            sort($sortedNew);
            if ($oldPermissions !== $sortedNew) {
                AuditService::log($ctx, 'role.permissions_changed', 'role', $id,
                    ['permissions' => $oldPermissions], ['permissions' => $sortedNew]);
            }
        }
        return Response::json(['message' => 'Role updated']);
    }

    public function destroy(Request $request, Ctx $ctx): Response
    {
        $id = $request->intParam('id');
        $role = $this->findRole($ctx, $id);
        if ((int) $role['is_system'] === 1) {
            throw HttpException::badRequest('System roles cannot be deleted', 'SYSTEM_ROLE');
        }
        $inUse = (int) Database::scalar(
            'SELECT COUNT(*) FROM users WHERE role_id = :r AND deleted_at IS NULL', ['r' => $id]
        );
        if ($inUse > 0) {
            throw HttpException::conflict('This role is assigned to ' . $inUse . ' user(s)', 'ROLE_IN_USE');
        }
        Database::run('DELETE FROM roles WHERE id = :id AND organization_id = :o',
            ['id' => $id, 'o' => $ctx->orgId()]);
        AuditService::log($ctx, 'role.deleted', 'role', $id, ['name' => $role['name']], null);
        return Response::json(['message' => 'Role deleted']);
    }

    private function findRole(Ctx $ctx, int $id): array
    {
        $role = Database::selectOne(
            'SELECT id, name, slug, is_system FROM roles WHERE id = :id AND organization_id = :o LIMIT 1',
            ['id' => $id, 'o' => $ctx->orgId()]
        );
        if ($role === null) {
            throw HttpException::notFound('Role not found');
        }
        return $role;
    }

    private function rolePermissions(int $roleId): array
    {
        return array_column(Database::select(
            'SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
              WHERE rp.role_id = :r ORDER BY p.slug',
            ['r' => $roleId]
        ), 'slug');
    }

    /** @return int[] */
    private function resolvePermissions(array $slugs): array
    {
        $catalogue = Permissions::catalogue();
        $ids = [];
        foreach ($slugs as $slug) {
            if (!isset($catalogue[$slug]) || str_starts_with((string) $slug, 'platform.')) {
                throw HttpException::validation(['permissions' => "Unknown permission: {$slug}"]);
            }
            $id = Database::scalar('SELECT id FROM permissions WHERE slug = :s', ['s' => $slug]);
            if ($id !== null) {
                $ids[] = (int) $id;
            }
        }
        return array_values(array_unique($ids));
    }

    private function syncPermissions(int $roleId, array $permissionIds): void
    {
        Database::run('DELETE FROM role_permissions WHERE role_id = :r', ['r' => $roleId]);
        foreach ($permissionIds as $pid) {
            Database::run(
                'INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (:r, :p, :c)',
                ['r' => $roleId, 'p' => $pid, 'c' => Str::now()]
            );
        }
    }

    /** Refuse a change that would leave the organization with nobody able to manage users/settings. */
    private function assertOrgKeepsAnAdmin(Ctx $ctx, int $roleId, array $newPermissions): void
    {
        $keepsAdmin = in_array('user.manage', $newPermissions, true)
            && in_array('settings.manage', $newPermissions, true);
        if ($keepsAdmin) {
            return;
        }
        $otherAdminRoles = (int) Database::scalar(
            "SELECT COUNT(DISTINCT r.id)
               FROM roles r
               JOIN role_permissions rp ON rp.role_id = r.id
               JOIN permissions p ON p.id = rp.permission_id AND p.slug = 'user.manage'
               JOIN users u ON u.role_id = r.id AND u.is_active = 1 AND u.deleted_at IS NULL
              WHERE r.organization_id = :o AND r.id <> :r",
            ['o' => $ctx->orgId(), 'r' => $roleId]
        );
        if ($otherAdminRoles === 0) {
            throw HttpException::conflict(
                'This is the last role that can manage users. Removing user.manage would lock the clinic out.',
                'LAST_ADMIN_ROLE'
            );
        }
    }
}
