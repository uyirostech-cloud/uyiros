<?php
namespace App\Http\Middleware;

use App\Core\Ctx;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;

/**
 * Builds the Ctx from the authenticated user: organization, role, permissions, branches.
 * This is the ONLY place organization_id enters the request — never from the client.
 */
final class ResolveTenant
{
    /**
     * @param bool $platformRoute Route declared with Router::platform() — platform admins only.
     * @param bool $authOnly Route declared authOnly (no permission, any signed-in account) —
     *                       e.g. /auth/me, /auth/logout. Both account types may use these.
     */
    public static function build(array $auth, Request $request, bool $platformRoute, bool $authOnly = false): Ctx
    {
        $user = $auth['user'];

        if (!$user['is_active']) {
            throw HttpException::forbidden('Your account has been deactivated. Contact your clinic administrator.');
        }

        if ($user['is_platform_admin']) {
            if (!$platformRoute && !$authOnly) {
                throw HttpException::forbidden('Platform administrators cannot access clinic data');
            }
            return new Ctx(
                userId: $user['id'],
                userName: $user['name'],
                userEmail: $user['email'],
                organizationId: null,
                organizationName: null,
                roleId: null,
                roleSlug: 'platform_admin',
                permissions: self::platformPermissions(),
                branchIds: [],
                allBranches: true,
                isPlatformAdmin: true,
                sessionId: $auth['session']['id'],
                ip: $request->ip,
                userAgent: $request->userAgent,
            );
        }

        if ($platformRoute) {
            throw HttpException::forbidden('Platform administration is not available to clinic users');
        }
        if ($user['organization_id'] === null) {
            throw HttpException::forbidden('This account is not associated with a clinic');
        }

        $org = Database::selectOne(
            'SELECT id, name, status FROM organizations WHERE id = :id LIMIT 1',
            ['id' => $user['organization_id']]
        );
        if ($org === null) {
            throw HttpException::forbidden('Clinic not found');
        }
        if (!in_array($org['status'], ['active', 'trial'], true)) {
            throw HttpException::forbidden('This clinic account is currently ' . $org['status'] . '. Contact support.');
        }

        $role = null;
        $permissions = [];
        if ($user['role_id'] !== null) {
            $role = Database::selectOne(
                'SELECT id, name, slug FROM roles WHERE id = :id LIMIT 1',
                ['id' => $user['role_id']]
            );
            $permissions = array_column(Database::select(
                'SELECT p.slug FROM role_permissions rp
                   JOIN permissions p ON p.id = rp.permission_id
                  WHERE rp.role_id = :r',
                ['r' => $user['role_id']]
            ), 'slug');
        }

        if ($user['all_branches']) {
            $branchIds = array_map('intval', array_column(Database::select(
                'SELECT id FROM branches WHERE organization_id = :o AND is_active = 1',
                ['o' => $user['organization_id']]
            ), 'id'));
        } else {
            $branchIds = array_map('intval', array_column(Database::select(
                'SELECT ub.branch_id FROM user_branches ub
                   JOIN branches b ON b.id = ub.branch_id AND b.organization_id = :o
                  WHERE ub.user_id = :u',
                ['u' => $user['id'], 'o' => $user['organization_id']]
            ), 'branch_id'));
        }

        $doctorId = Database::scalar(
            'SELECT id FROM doctors WHERE organization_id = :o AND user_id = :u LIMIT 1',
            ['o' => $user['organization_id'], 'u' => $user['id']]
        );

        return new Ctx(
            userId: $user['id'],
            userName: $user['name'],
            userEmail: $user['email'],
            organizationId: (int) $user['organization_id'],
            organizationName: $org['name'],
            roleId: $role ? (int) $role['id'] : null,
            roleSlug: $role['slug'] ?? null,
            permissions: $permissions,
            branchIds: $branchIds,
            allBranches: (bool) $user['all_branches'],
            isPlatformAdmin: false,
            sessionId: $auth['session']['id'],
            ip: $request->ip,
            userAgent: $request->userAgent,
            doctorId: $doctorId === null ? null : (int) $doctorId,
        );
    }

    private static function platformPermissions(): array
    {
        return array_column(
            Database::select("SELECT slug FROM permissions WHERE slug LIKE 'platform.%'"),
            'slug'
        );
    }
}
