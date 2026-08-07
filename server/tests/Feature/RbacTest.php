<?php
namespace Tests\Feature;

use App\Core\Database;
use Tests\Assert;
use Tests\TestCase;

/** Roles, permissions, branch assignment and user management. */
final class RbacTest extends TestCase
{
    public function tests(): array
    {
        return [
            'R1 each seeded role carries exactly its documented permission set' => function () {
                $expected = \App\Support\Permissions::defaultRoles();
                $orgId = (int) $this->scalar("SELECT id FROM organizations WHERE code = 'DEMO'");
                foreach ($expected as $slug => $definition) {
                    $actual = array_column(Database::select(
                        'SELECT p.slug FROM roles r
                           JOIN role_permissions rp ON rp.role_id = r.id
                           JOIN permissions p ON p.id = rp.permission_id
                          WHERE r.organization_id = :o AND r.slug = :s ORDER BY p.slug',
                        ['o' => $orgId, 's' => $slug]
                    ), 'slug');
                    $want = $definition['permissions'];
                    sort($want);
                    Assert::same($want, $actual, "role {$slug} permission set mismatch");
                }
            },

            'R2 permission checks come from the database, not the role name' => function () {
                $this->login('doctor', 'suresh.doctor@demoklinik.test');
                $me = $this->request('GET', '/api/auth/me', as: 'doctor');
                $permissions = $me['body']['data']['user']['permissions'];

                Assert::contains('consultation.finalize', $permissions);
                Assert::notContains('invoice.create', $permissions);
                Assert::notContains('user.manage', $permissions);
                Assert::notContains('finance.view', $permissions);

                Assert::status(403, $this->request('GET', '/api/users', as: 'doctor'));
                Assert::status(403, $this->request('POST', '/api/branches',
                    ['name' => 'X', 'code' => 'XX'], as: 'doctor'));
            },

            'R3 revoking a permission takes effect on the next request' => function () {
                $this->login('adminA', 'admin@demoklinik.test');
                $orgId = (int) $this->scalar("SELECT id FROM organizations WHERE code = 'DEMO'");
                $roleId = (int) $this->scalar(
                    "SELECT id FROM roles WHERE organization_id = :o AND slug = 'receptionist'", ['o' => $orgId]
                );
                $permId = (int) $this->scalar("SELECT id FROM permissions WHERE slug = 'patient.view'");

                $this->login('reception', 'reception@demoklinik.test');
                Assert::status(200, $this->request('GET', '/api/patients', as: 'reception'));

                Database::run('DELETE FROM role_permissions WHERE role_id = :r AND permission_id = :p',
                    ['r' => $roleId, 'p' => $permId]);
                try {
                    Assert::status(403, $this->request('GET', '/api/patients', as: 'reception'),
                        'a revoked permission must apply immediately, without re-login');
                } finally {
                    Database::run(
                        'INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
                         VALUES (:r, :p, UTC_TIMESTAMP())',
                        ['r' => $roleId, 'p' => $permId]
                    );
                }
                Assert::status(200, $this->request('GET', '/api/patients', as: 'reception'));
            },

            'R4 clinic admin can create a user with scoped branch access' => function () {
                $this->login('adminA', 'admin@demoklinik.test');
                $orgId = (int) $this->scalar("SELECT id FROM organizations WHERE code = 'DEMO'");
                $anna = (int) $this->scalar(
                    "SELECT id FROM branches WHERE organization_id = :o AND code = 'ANN'", ['o' => $orgId]);
                $roleId = (int) $this->scalar(
                    "SELECT id FROM roles WHERE organization_id = :o AND slug = 'receptionist'", ['o' => $orgId]);

                Database::run('DELETE FROM users WHERE email = :e', ['e' => 'newuser@demoklinik.test']);
                $result = $this->request('POST', '/api/users', [
                    'name' => 'New Receptionist', 'email' => 'newuser@demoklinik.test',
                    'password' => 'Onboard#2026', 'role_id' => $roleId,
                    'branch_ids' => [$anna], 'all_branches' => false,
                ], as: 'adminA');
                Assert::status(201, $result);

                $userId = $result['body']['data']['id'];
                Assert::same(1, (int) $this->scalar(
                    'SELECT COUNT(*) FROM user_branches WHERE user_id = :u', ['u' => $userId]));
                Assert::same(1, (int) $this->scalar(
                    'SELECT must_change_password FROM users WHERE id = :u', ['u' => $userId]));

                Database::run('DELETE FROM users WHERE id = :u', ['u' => $userId]);
            },

            'R5 a user cannot be assigned a role from another organization' => function () {
                $this->login('adminA', 'admin@demoklinik.test');
                $foreignRole = (int) $this->scalar(
                    "SELECT r.id FROM roles r JOIN organizations o ON o.id = r.organization_id
                      WHERE o.code = 'CITYCARE' AND r.slug = 'clinic_admin'"
                );
                $result = $this->request('POST', '/api/users', [
                    'name' => 'Cross Tenant', 'email' => 'crosstenant@demoklinik.test',
                    'password' => 'Onboard#2026', 'role_id' => $foreignRole,
                ], as: 'adminA');
                Assert::status(422, $result);
                Assert::same(0, (int) $this->scalar('SELECT COUNT(*) FROM users WHERE email = :e',
                    ['e' => 'crosstenant@demoklinik.test']));
            },

            'R6 a user cannot be assigned a branch from another organization' => function () {
                $this->login('adminA', 'admin@demoklinik.test');
                $orgId = (int) $this->scalar("SELECT id FROM organizations WHERE code = 'DEMO'");
                $roleId = (int) $this->scalar(
                    "SELECT id FROM roles WHERE organization_id = :o AND slug = 'receptionist'", ['o' => $orgId]);
                $foreignBranch = (int) $this->scalar(
                    "SELECT b.id FROM branches b JOIN organizations o ON o.id = b.organization_id
                      WHERE o.code = 'CITYCARE' LIMIT 1"
                );
                $result = $this->request('POST', '/api/users', [
                    'name' => 'Branch Hop', 'email' => 'branchhop@demoklinik.test',
                    'password' => 'Onboard#2026', 'role_id' => $roleId,
                    'branch_ids' => [$foreignBranch],
                ], as: 'adminA');
                Assert::status(422, $result);
            },

            'R7 an admin cannot deactivate their own account' => function () {
                $this->login('adminA', 'admin@demoklinik.test');
                $me = $this->sessionUser('adminA');
                $result = $this->request('POST', "/api/users/{$me['id']}/deactivate", as: 'adminA');
                Assert::status(400, $result);
                Assert::same('SELF_DEACTIVATION', $result['body']['error']['code']);
            },

            'R8 the last user-managing role cannot lose user.manage' => function () {
                $this->login('adminA', 'admin@demoklinik.test');
                $orgId = (int) $this->scalar("SELECT id FROM organizations WHERE code = 'DEMO'");
                $roleId = (int) $this->scalar(
                    "SELECT id FROM roles WHERE organization_id = :o AND slug = 'clinic_admin'", ['o' => $orgId]);

                $result = $this->request('PUT', "/api/roles/{$roleId}",
                    ['permissions' => ['dashboard.view', 'patient.view']], as: 'adminA');
                Assert::status(409, $result);
                Assert::same('LAST_ADMIN_ROLE', $result['body']['error']['code']);
            },

            'R9 platform permissions cannot be granted to a clinic role' => function () {
                $this->login('adminA', 'admin@demoklinik.test');
                $result = $this->request('POST', '/api/roles', [
                    'name' => 'Escalation Attempt',
                    'permissions' => ['dashboard.view', 'platform.organization.manage'],
                ], as: 'adminA');
                Assert::status(422, $result);
            },

            'R10 deactivating a user immediately kills their sessions' => function () {
                $this->login('adminA', 'admin@demoklinik.test');
                $this->login('target', 'accounts@demoklinik.test');
                Assert::status(200, $this->request('GET', '/api/auth/me', as: 'target'));

                $targetId = (int) $this->scalar('SELECT id FROM users WHERE email = :e',
                    ['e' => 'accounts@demoklinik.test']);
                try {
                    Assert::status(200,
                        $this->request('POST', "/api/users/{$targetId}/deactivate", as: 'adminA'));
                    Assert::status(401, $this->request('GET', '/api/auth/me', as: 'target'),
                        'sessions must be revoked when a user is deactivated');
                } finally {
                    Database::run('UPDATE users SET is_active = 1 WHERE id = :id', ['id' => $targetId]);
                }
            },

            'R11 branch list is limited to the branches a user may use' => function () {
                $this->login('reception', 'reception@demoklinik.test');
                $result = $this->request('GET', '/api/branches', as: 'reception');
                Assert::status(200, $result);
                Assert::count(1, $result['body']['data'], 'receptionist is assigned to one branch only');
                Assert::same('ANN', $result['body']['data'][0]['code']);

                $this->login('adminA', 'admin@demoklinik.test');
                $admin = $this->request('GET', '/api/branches', as: 'adminA');
                Assert::count(2, $admin['body']['data'], 'admin has all branches');
            },

            'R12 role listing never exposes another organization roles' => function () {
                $this->login('adminA', 'admin@demoklinik.test');
                $result = $this->request('GET', '/api/roles', as: 'adminA');
                Assert::status(200, $result);
                $orgId = (int) $this->scalar("SELECT id FROM organizations WHERE code = 'DEMO'");
                foreach ($result['body']['data'] as $role) {
                    $roleOrg = (int) $this->scalar('SELECT organization_id FROM roles WHERE id = :id',
                        ['id' => $role['id']]);
                    Assert::same($orgId, $roleOrg, 'a foreign role leaked into the role list');
                }
            },
        ];
    }
}
