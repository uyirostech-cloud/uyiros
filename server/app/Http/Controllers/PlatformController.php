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
use App\Domain\Services\AuthService;
use App\Domain\Services\NumberingService;
use App\Support\Permissions;

/**
 * Level 1 — Platform administration. Reachable only by users with is_platform_admin = 1,
 * enforced in ResolveTenant. These endpoints deal with organizations and billing, never
 * with patient or clinical rows.
 */
final class PlatformController
{
    public function overview(Request $request, Ctx $ctx): Response
    {
        $orgs = Database::selectOne(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'active') AS active,
                    SUM(status = 'trial') AS trial,
                    SUM(status = 'suspended') AS suspended
               FROM organizations"
        );
        $users = (int) Database::scalar(
            'SELECT COUNT(*) FROM users WHERE is_platform_admin = 0 AND deleted_at IS NULL'
        );
        return Response::json([
            'organizations' => [
                'total'     => (int) $orgs['total'],
                'active'    => (int) $orgs['active'],
                'trial'     => (int) $orgs['trial'],
                'suspended' => (int) $orgs['suspended'],
            ],
            'clinic_users' => $users,
            'plans'        => (int) Database::scalar('SELECT COUNT(*) FROM plans WHERE is_active = 1'),
            'branches'     => (int) Database::scalar('SELECT COUNT(*) FROM branches WHERE deleted_at IS NULL'),
        ]);
    }

    public function organizations(Request $request, Ctx $ctx): Response
    {
        [$page, $perPage] = $request->pagination(25);
        $where = '1 = 1';
        $params = [];
        if (($q = trim((string) $request->query('q', ''))) !== '') {
            $where .= ' AND (o.name LIKE :q1 OR o.code LIKE :q2 OR o.email LIKE :q3)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        if (($status = $request->query('status')) !== null && $status !== '') {
            $where .= ' AND o.status = :status';
            $params['status'] = (string) $status;
        }

        $total = (int) Database::scalar("SELECT COUNT(*) FROM organizations o WHERE {$where}", $params);
        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            "SELECT o.id, o.code, o.name, o.slug, o.status, o.email, o.phone, o.city,
                    o.trial_ends_at, o.created_at, p.name AS plan_name, p.code AS plan_code,
                    (SELECT COUNT(*) FROM branches b WHERE b.organization_id = o.id AND b.deleted_at IS NULL) AS branch_count,
                    (SELECT COUNT(*) FROM users u WHERE u.organization_id = o.id AND u.deleted_at IS NULL) AS user_count,
                    (SELECT COUNT(*) FROM patients pa WHERE pa.organization_id = o.id AND pa.deleted_at IS NULL) AS patient_count
               FROM organizations o
               LEFT JOIN plans p ON p.id = o.plan_id
              WHERE {$where}
              ORDER BY o.name LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return Response::paginated(array_map(static fn ($r) => [
            'id'            => (int) $r['id'],
            'code'          => $r['code'],
            'name'          => $r['name'],
            'slug'          => $r['slug'],
            'status'        => $r['status'],
            'email'         => $r['email'],
            'phone'         => $r['phone'],
            'city'          => $r['city'],
            'plan'          => $r['plan_name'],
            'plan_code'     => $r['plan_code'],
            'trial_ends_at' => $r['trial_ends_at'],
            'branch_count'  => (int) $r['branch_count'],
            'user_count'    => (int) $r['user_count'],
            'patient_count' => (int) $r['patient_count'],
            'created_at'    => $r['created_at'],
        ], $rows), $total, $page, $perPage);
    }

    public function createOrganization(Request $request, Ctx $ctx): Response
    {
        $data = Validator::make($request->only([
            'name', 'code', 'email', 'phone', 'city', 'state', 'plan_id', 'status',
            'admin_name', 'admin_email', 'admin_password', 'branch_name',
        ]), [
            'name'           => 'required|string|minlen:2|maxlen:160',
            'code'           => 'required|string|minlen:2|maxlen:30',
            'email'          => 'nullable|email|maxlen:190',
            'phone'          => 'nullable|phone|maxlen:30',
            'city'           => 'nullable|string|maxlen:80',
            'state'          => 'nullable|string|maxlen:80',
            'plan_id'        => 'nullable|int|exists:plans',
            'status'         => 'nullable|in:active,trial,suspended,cancelled',
            'admin_name'     => 'required|string|minlen:2|maxlen:120',
            'admin_email'    => 'required|email|maxlen:190',
            'admin_password' => 'required|string|maxlen:200',
            'branch_name'    => 'nullable|string|maxlen:120',
        ]);
        AuthService::validatePasswordStrength((string) $data['admin_password']);

        $code = strtoupper((string) $data['code']);
        if (Database::scalar('SELECT id FROM organizations WHERE code = :c', ['c' => $code]) !== null) {
            throw HttpException::validation(['code' => 'This organization code is already in use']);
        }
        if (Database::scalar('SELECT id FROM users WHERE email = :e', ['e' => $data['admin_email']]) !== null) {
            throw HttpException::validation(['admin_email' => 'This email address is already registered']);
        }

        $orgId = Database::transaction(function () use ($data, $code, $ctx) {
            $slug = Str::slug((string) $data['name']);
            $suffix = 1;
            while (Database::scalar('SELECT id FROM organizations WHERE slug = :s', ['s' => $slug]) !== null) {
                $slug = Str::slug((string) $data['name']) . '-' . (++$suffix);
            }
            $orgId = Database::insert(
                'INSERT INTO organizations
                   (public_id, code, name, slug, status, plan_id, trial_ends_at, email, phone, city, state, created_at)
                 VALUES (:pub, :code, :name, :slug, :status, :plan, :trial, :email, :phone, :city, :state, :created)',
                [
                    'pub' => Str::ulid(), 'code' => $code, 'name' => $data['name'], 'slug' => $slug,
                    'status' => $data['status'] ?? 'trial', 'plan' => $data['plan_id'] ?? null,
                    'trial' => ($data['status'] ?? 'trial') === 'trial' ? gmdate('Y-m-d', time() + 14 * 86400) : null,
                    'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
                    'city' => $data['city'] ?? null, 'state' => $data['state'] ?? null,
                    'created' => Str::now(),
                ]
            );

            NumberingService::seedDefaults($orgId);
            $roleIds = self::seedRoles($orgId);
            self::seedDefaultData($orgId);

            $branchId = Database::insert(
                'INSERT INTO branches (organization_id, name, code, city, state, is_active, created_at)
                 VALUES (:o, :n, :c, :city, :state, 1, :created)',
                [
                    'o' => $orgId, 'n' => $data['branch_name'] ?? 'Main Branch', 'c' => 'MAIN',
                    'city' => $data['city'] ?? null, 'state' => $data['state'] ?? null, 'created' => Str::now(),
                ]
            );

            $userId = Database::insert(
                'INSERT INTO users
                   (public_id, organization_id, role_id, name, email, password_hash, is_active,
                    all_branches, must_change_password, created_at, created_by)
                 VALUES (:pub, :o, :r, :n, :e, :h, 1, 1, 1, :created, :by)',
                [
                    'pub' => Str::ulid(), 'o' => $orgId, 'r' => $roleIds['clinic_admin'],
                    'n' => $data['admin_name'], 'e' => $data['admin_email'],
                    'h' => AuthService::hash((string) $data['admin_password']),
                    'created' => Str::now(), 'by' => $ctx->userId,
                ]
            );
            Database::run('INSERT INTO user_branches (user_id, branch_id, created_at) VALUES (:u, :b, :c)',
                ['u' => $userId, 'b' => $branchId, 'c' => Str::now()]);

            return $orgId;
        });

        AuditService::log($ctx, 'platform.organization_created', 'organization', $orgId, null,
            ['name' => $data['name'], 'code' => $code]);

        return Response::json(['id' => $orgId, 'code' => $code, 'name' => $data['name']], 201);
    }

    public function updateOrganizationStatus(Request $request, Ctx $ctx): Response
    {
        $id = $request->intParam('id');
        $org = Database::selectOne('SELECT id, name, status FROM organizations WHERE id = :id', ['id' => $id]);
        if ($org === null) {
            throw HttpException::notFound('Organization not found');
        }
        $data = Validator::make($request->only(['status', 'reason']), [
            'status' => 'required|in:active,trial,suspended,cancelled',
            'reason' => 'nullable|string|maxlen:255',
        ]);

        Database::run('UPDATE organizations SET status = :s, updated_at = :u WHERE id = :id',
            ['s' => $data['status'], 'u' => Str::now(), 'id' => $id]);

        // Suspending a clinic must end its users' sessions immediately.
        if (in_array($data['status'], ['suspended', 'cancelled'], true)) {
            Database::run(
                'UPDATE sessions s JOIN users u ON u.id = s.user_id
                    SET s.revoked_at = :n
                  WHERE u.organization_id = :o AND s.revoked_at IS NULL',
                ['n' => Str::now(), 'o' => $id]
            );
        }

        AuditService::log($ctx, 'platform.organization_status_changed', 'organization', $id,
            ['status' => $org['status']], ['status' => $data['status'], 'reason' => $data['reason'] ?? null]);

        return Response::json(['message' => 'Organization status updated', 'status' => $data['status']]);
    }

    public function plans(Request $request, Ctx $ctx): Response
    {
        $rows = Database::select('SELECT * FROM plans ORDER BY price_monthly');
        return Response::json(array_map(static fn ($p) => [
            'id'            => (int) $p['id'],
            'code'          => $p['code'],
            'name'          => $p['name'],
            'description'   => $p['description'],
            'price_monthly' => $p['price_monthly'],
            'price_yearly'  => $p['price_yearly'],
            'max_branches'  => (int) $p['max_branches'],
            'max_users'     => (int) $p['max_users'],
            'is_active'     => (int) $p['is_active'] === 1,
        ], $rows));
    }

    public function createPlan(Request $request, Ctx $ctx): Response
    {
        $data = Validator::make($request->only([
            'code', 'name', 'description', 'price_monthly', 'price_yearly',
            'max_branches', 'max_users', 'is_active',
        ]), [
            'code'          => 'required|string|minlen:2|maxlen:50',
            'name'          => 'required|string|minlen:2|maxlen:120',
            'description'   => 'nullable|string|maxlen:255',
            'price_monthly' => 'required|money',
            'price_yearly'  => 'nullable|money',
            'max_branches'  => 'required|int|min:1',
            'max_users'     => 'required|int|min:1',
            'is_active'     => 'nullable|boolean',
        ]);
        if (Database::scalar('SELECT id FROM plans WHERE code = :c', ['c' => $data['code']]) !== null) {
            throw HttpException::validation(['code' => 'This plan code is already in use']);
        }
        $id = Database::insert(
            'INSERT INTO plans (code, name, description, price_monthly, price_yearly,
                                max_branches, max_users, is_active, created_at)
             VALUES (:c, :n, :d, :pm, :py, :mb, :mu, :a, :created)',
            [
                'c' => $data['code'], 'n' => $data['name'], 'd' => $data['description'] ?? null,
                'pm' => $data['price_monthly'], 'py' => $data['price_yearly'] ?? '0.00',
                'mb' => $data['max_branches'], 'mu' => $data['max_users'],
                'a' => $data['is_active'] ?? 1, 'created' => Str::now(),
            ]
        );
        AuditService::log($ctx, 'platform.plan_created', 'plan', $id, null, $data);
        return Response::json(['id' => $id] + $data, 201);
    }

    public function subscriptions(Request $request, Ctx $ctx): Response
    {
        [$page, $perPage] = $request->pagination(25);
        $total = (int) Database::scalar('SELECT COUNT(*) FROM subscriptions');
        $offset = ($page - 1) * $perPage;
        $rows = Database::select(
            "SELECT s.id, s.status, s.billing_cycle, s.amount, s.starts_at, s.ends_at,
                    o.name AS organization, o.code AS organization_code, p.name AS plan
               FROM subscriptions s
               JOIN organizations o ON o.id = s.organization_id
               JOIN plans p ON p.id = s.plan_id
              ORDER BY s.id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        return Response::paginated(array_map(static fn ($r) => [
            'id'                => (int) $r['id'],
            'organization'      => $r['organization'],
            'organization_code' => $r['organization_code'],
            'plan'              => $r['plan'],
            'status'            => $r['status'],
            'billing_cycle'     => $r['billing_cycle'],
            'amount'            => $r['amount'],
            'starts_at'         => $r['starts_at'],
            'ends_at'           => $r['ends_at'],
        ], $rows), $total, $page, $perPage);
    }

    public function users(Request $request, Ctx $ctx): Response
    {
        [$page, $perPage] = $request->pagination(25);
        $where = 'u.is_platform_admin = 0 AND u.deleted_at IS NULL';
        $params = [];
        if (($orgId = $request->query('organization_id')) !== null && $orgId !== '') {
            $where .= ' AND u.organization_id = :o';
            $params['o'] = (int) $orgId;
        }
        $total = (int) Database::scalar("SELECT COUNT(*) FROM users u WHERE {$where}", $params);
        $offset = ($page - 1) * $perPage;
        // Administrative overview only — no clinical or patient data crosses this boundary.
        $rows = Database::select(
            "SELECT u.id, u.name, u.email, u.is_active, u.last_login_at,
                    o.name AS organization, r.name AS role
               FROM users u
               LEFT JOIN organizations o ON o.id = u.organization_id
               LEFT JOIN roles r ON r.id = u.role_id
              WHERE {$where}
              ORDER BY o.name, u.name LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return Response::paginated(array_map(static fn ($r) => [
            'id'            => (int) $r['id'],
            'name'          => $r['name'],
            'email'         => $r['email'],
            'organization'  => $r['organization'],
            'role'          => $r['role'],
            'is_active'     => (int) $r['is_active'] === 1,
            'last_login_at' => $r['last_login_at'],
        ], $rows), $total, $page, $perPage);
    }

    /** @return array<string,int> role slug => id */
    public static function seedRoles(int $organizationId): array
    {
        $ids = [];
        foreach (Permissions::defaultRoles() as $slug => $definition) {
            $roleId = Database::insert(
                'INSERT INTO roles (organization_id, name, slug, description, is_system, created_at)
                 VALUES (:o, :n, :s, :d, 1, :c)',
                ['o' => $organizationId, 'n' => $definition['name'], 's' => $slug,
                 'd' => $definition['description'], 'c' => Str::now()]
            );
            foreach ($definition['permissions'] as $permissionSlug) {
                $pid = Database::scalar('SELECT id FROM permissions WHERE slug = :s', ['s' => $permissionSlug]);
                if ($pid !== null) {
                    Database::run(
                        'INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
                         VALUES (:r, :p, :c)',
                        ['r' => $roleId, 'p' => (int) $pid, 'c' => Str::now()]
                    );
                }
            }
            $ids[$slug] = $roleId;
        }
        return $ids;
    }

    /** Expense categories, financial accounts and inventory categories every clinic starts with. */
    public static function seedDefaultData(int $organizationId): void
    {
        $now = Str::now();
        foreach (['Rent', 'Salary', 'Electricity', 'Internet', 'Medical Supplies', 'Maintenance',
                  'Marketing', 'Travel', 'Vendor', 'Office', 'Other'] as $name) {
            Database::run(
                'INSERT IGNORE INTO expense_categories (organization_id, name, is_active, created_at)
                 VALUES (:o, :n, 1, :c)',
                ['o' => $organizationId, 'n' => $name, 'c' => $now]
            );
        }
        foreach ([['Cash in Hand', 'cash'], ['Bank Account', 'bank'], ['UPI', 'upi'],
                  ['Card Clearing', 'card_clearing'], ['Other', 'other']] as [$name, $type]) {
            Database::run(
                'INSERT INTO financial_accounts (organization_id, name, type, is_active, created_at)
                 VALUES (:o, :n, :t, 1, :c)',
                ['o' => $organizationId, 'n' => $name, 't' => $type, 'c' => $now]
            );
        }
        foreach (['Consumables', 'Medicines', 'Equipment', 'Stationery', 'Other'] as $name) {
            Database::run(
                'INSERT IGNORE INTO inventory_categories (organization_id, name, is_active, created_at)
                 VALUES (:o, :n, 1, :c)',
                ['o' => $organizationId, 'n' => $name, 'c' => $now]
            );
        }
    }
}
