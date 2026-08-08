<?php
namespace App\Domain\Services;

use App\Core\Database;
use App\Core\Str;
use App\Http\Controllers\PlatformController;

/**
 * Everything needed to stand up a brand-new tenant: organization row, default roles +
 * permissions, starter settings data, a first branch, and the clinic's first admin user.
 *
 * Used by both the platform-admin "create clinic" endpoint and the public self-service
 * signup flow, so a new organization is provisioned identically either way.
 */
final class OrganizationProvisioningService
{
    /**
     * @param array{
     *   name:string, code:string, email?:?string, phone?:?string, city?:?string, state?:?string,
     *   status?:string, plan_id?:?int, admin_name:string, admin_email:string,
     *   admin_password_hash:string, branch_name?:string
     * } $data
     * @return array{organization_id:int, branch_id:int, user_id:int, role_id:int}
     */
    public static function provision(array $data): array
    {
        return Database::transaction(function () use ($data) {
            $now = Str::now();
            $status = $data['status'] ?? 'trial';

            $slug = Str::slug((string) $data['name']);
            $suffix = 1;
            $baseSlug = $slug;
            while (Database::scalar('SELECT id FROM organizations WHERE slug = :s', ['s' => $slug]) !== null) {
                $slug = $baseSlug . '-' . (++$suffix);
            }

            $orgId = Database::insert(
                'INSERT INTO organizations
                   (public_id, code, name, slug, status, plan_id, trial_ends_at, email, phone, city, state, created_at)
                 VALUES (:pub, :code, :name, :slug, :status, :plan, :trial, :email, :phone, :city, :state, :created)',
                [
                    'pub' => Str::ulid(), 'code' => $data['code'], 'name' => $data['name'], 'slug' => $slug,
                    'status' => $status, 'plan' => $data['plan_id'] ?? null,
                    'trial' => $status === 'trial' ? gmdate('Y-m-d', time() + 14 * 86400) : null,
                    'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
                    'city' => $data['city'] ?? null, 'state' => $data['state'] ?? null,
                    'created' => $now,
                ]
            );

            NumberingService::seedDefaults($orgId);
            $roles = PlatformController::seedRoles($orgId);
            PlatformController::seedDefaultData($orgId);

            $branchId = Database::insert(
                'INSERT INTO branches (organization_id, name, code, city, state, is_active, created_at)
                 VALUES (:o, :n, "MAIN", :city, :state, 1, :created)',
                [
                    'o' => $orgId, 'n' => $data['branch_name'] ?? 'Main Branch',
                    'city' => $data['city'] ?? null, 'state' => $data['state'] ?? null, 'created' => $now,
                ]
            );

            $userId = Database::insert(
                'INSERT INTO users (public_id, organization_id, role_id, name, email, password_hash,
                                    is_active, all_branches, must_change_password, created_at)
                 VALUES (:pub, :o, :r, :n, :e, :h, 1, 1, 0, :created)',
                [
                    'pub' => Str::ulid(), 'o' => $orgId, 'r' => $roles['clinic_admin'],
                    'n' => $data['admin_name'], 'e' => $data['admin_email'],
                    'h' => $data['admin_password_hash'], 'created' => $now,
                ]
            );
            Database::run('INSERT INTO user_branches (user_id, branch_id, created_at) VALUES (:u, :b, :c)',
                ['u' => $userId, 'b' => $branchId, 'c' => $now]);

            if (!empty($data['plan_id'])) {
                Database::run(
                    'INSERT INTO subscriptions (organization_id, plan_id, status, billing_cycle, amount, starts_at, created_at)
                     VALUES (:o, :p, :s, "monthly", 0.00, :starts, :created)',
                    [
                        'o' => $orgId, 'p' => $data['plan_id'],
                        's' => $status === 'trial' ? 'trialing' : 'active',
                        'starts' => Str::today(), 'created' => $now,
                    ]
                );
            }

            return [
                'organization_id' => $orgId,
                'branch_id'       => $branchId,
                'user_id'         => $userId,
                'role_id'         => $roles['clinic_admin'],
            ];
        });
    }

    /** Generates a short, unique organization code from its name, e.g. "Sunrise Clinic" -> "SUNRISE-4821". */
    public static function generateCode(string $name): string
    {
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $name) ?? '');
        $base = substr($base !== '' ? $base : 'CLINIC', 0, 16);
        do {
            $code = $base . '-' . random_int(1000, 9999);
        } while (Database::scalar('SELECT id FROM organizations WHERE code = :c', ['c' => $code]) !== null);
        return $code;
    }
}
