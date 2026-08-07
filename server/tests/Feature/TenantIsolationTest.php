<?php
namespace Tests\Feature;

use App\Core\Database;
use Tests\Assert;
use Tests\TestCase;

/**
 * The Phase 1 gate. If anything here fails, no further phase may be built.
 * Org A = Demo General Clinic, Org B = City Care Clinic.
 */
final class TenantIsolationTest extends TestCase
{
    private int $orgAPatientId;
    private int $orgBPatientId;
    private int $orgBBranchId;

    private function ids(): void
    {
        $this->orgAPatientId = (int) $this->scalar(
            "SELECT p.id FROM patients p JOIN organizations o ON o.id = p.organization_id
              WHERE o.code = 'DEMO' ORDER BY p.id LIMIT 1"
        );
        $this->orgBPatientId = (int) $this->scalar(
            "SELECT p.id FROM patients p JOIN organizations o ON o.id = p.organization_id
              WHERE o.code = 'CITYCARE' ORDER BY p.id LIMIT 1"
        );
        $this->orgBBranchId = (int) $this->scalar(
            "SELECT b.id FROM branches b JOIN organizations o ON o.id = b.organization_id
              WHERE o.code = 'CITYCARE' LIMIT 1"
        );
    }

    public function tests(): array
    {
        return [
            'S1 org A cannot read an org B patient by id' => function () {
                $this->ids();
                $this->login('adminA', 'admin@demoklinik.test');
                $result = $this->request('GET', "/api/patients/{$this->orgBPatientId}", as: 'adminA');
                Assert::status(404, $result, 'Cross-tenant read must 404, never 200 or 403');
            },

            'S2 patient list contains only the caller organization rows' => function () {
                $this->ids();
                $this->login('adminA', 'admin@demoklinik.test');
                $this->login('adminB', 'admin@citycare.test');

                $a = $this->request('GET', '/api/patients', query: ['per_page' => 100], as: 'adminA');
                $b = $this->request('GET', '/api/patients', query: ['per_page' => 100], as: 'adminB');
                Assert::status(200, $a);
                Assert::status(200, $b);

                $aIds = array_column($a['body']['data'], 'id');
                $bIds = array_column($b['body']['data'], 'id');
                Assert::true($aIds !== [] && $bIds !== [], 'both organizations should have patients');
                Assert::same([], array_values(array_intersect($aIds, $bIds)), 'patient id overlap between tenants');
                Assert::notContains($this->orgBPatientId, $aIds, 'org B patient leaked into org A list');
                Assert::notContains($this->orgAPatientId, $bIds, 'org A patient leaked into org B list');

                // The totals must match the database, not just the page.
                $aCount = (int) $this->scalar(
                    "SELECT COUNT(*) FROM patients p JOIN organizations o ON o.id = p.organization_id
                      WHERE o.code = 'DEMO' AND p.deleted_at IS NULL"
                );
                Assert::same($aCount, $a['body']['meta']['total']);
            },

            'S3 organization_id in the request body is ignored' => function () {
                $this->ids();
                $this->login('adminA', 'admin@demoklinik.test');
                $orgB = (int) $this->scalar("SELECT id FROM organizations WHERE code = 'CITYCARE'");

                $result = $this->request('POST', '/api/patients', [
                    'full_name'       => 'Injection Attempt',
                    'phone'           => '9000000001',
                    'organization_id' => $orgB,          // hostile field
                    'gender'          => 'Male',
                ], as: 'adminA');
                Assert::status(201, $result);

                $storedOrg = (int) $this->scalar('SELECT organization_id FROM patients WHERE id = :id',
                    ['id' => $result['body']['data']['id']]);
                $orgA = (int) $this->scalar("SELECT id FROM organizations WHERE code = 'DEMO'");
                Assert::same($orgA, $storedOrg, 'record must be stored under the SESSION organization');

                Database::run('DELETE FROM patients WHERE id = :id', ['id' => $result['body']['data']['id']]);
            },

            'S4 org A cannot update an org B record' => function () {
                $this->ids();
                $this->login('adminA', 'admin@demoklinik.test');
                $before = $this->scalar('SELECT full_name FROM patients WHERE id = :id',
                    ['id' => $this->orgBPatientId]);

                $result = $this->request('PUT', "/api/patients/{$this->orgBPatientId}",
                    ['full_name' => 'Hijacked Name'], as: 'adminA');
                Assert::status(404, $result);

                $after = $this->scalar('SELECT full_name FROM patients WHERE id = :id',
                    ['id' => $this->orgBPatientId]);
                Assert::same($before, $after, 'the row must be untouched');
            },

            'S4b org A cannot delete an org B record' => function () {
                $this->ids();
                $this->login('adminA', 'admin@demoklinik.test');
                $result = $this->request('DELETE', "/api/patients/{$this->orgBPatientId}", as: 'adminA');
                Assert::status(404, $result);
                Assert::true(
                    $this->scalar('SELECT deleted_at FROM patients WHERE id = :id',
                        ['id' => $this->orgBPatientId]) === null,
                    'org B patient must not be soft-deleted by org A'
                );
            },

            'S5 receptionist is denied finance data' => function () {
                $this->login('reception', 'reception@demoklinik.test');
                $me = $this->request('GET', '/api/auth/me', as: 'reception');
                Assert::status(200, $me);
                Assert::notContains('finance.view', $me['body']['data']['user']['permissions']);

                Assert::status(403, $this->request('GET', '/api/audit-logs', as: 'reception'));
                Assert::status(403, $this->request('GET', '/api/settings', as: 'reception'));
                Assert::status(403, $this->request('GET', '/api/users', as: 'reception'));
            },

            'S6 sales user is denied clinical and admin data' => function () {
                $this->login('sales', 'sales@demoklinik.test');
                $me = $this->request('GET', '/api/auth/me', as: 'sales');
                Assert::notContains('consultation.view', $me['body']['data']['user']['permissions']);
                Assert::notContains('invoice.view', $me['body']['data']['user']['permissions']);

                Assert::status(403, $this->request('GET', '/api/users', as: 'sales'));
                Assert::status(403, $this->request('GET', '/api/roles', as: 'sales'));
                Assert::status(403, $this->request('POST', '/api/branches',
                    ['name' => 'Rogue', 'code' => 'RG'], as: 'sales'));
            },

            'S7 doctor can read a patient in their own organization' => function () {
                $this->ids();
                $this->login('doctor', 'suresh.doctor@demoklinik.test');
                $result = $this->request('GET', "/api/patients/{$this->orgAPatientId}", as: 'doctor');
                Assert::status(200, $result);
                Assert::same($this->orgAPatientId, $result['body']['data']['id']);

                // …but not one in another organization.
                Assert::status(404,
                    $this->request('GET', "/api/patients/{$this->orgBPatientId}", as: 'doctor'));
            },

            'S8 a user cannot address a branch they are not assigned to' => function () {
                $this->ids();
                // Priya is assigned to Anna Nagar only.
                $this->login('reception', 'reception@demoklinik.test');
                $tnagar = (int) $this->scalar(
                    "SELECT b.id FROM branches b JOIN organizations o ON o.id = b.organization_id
                      WHERE o.code = 'DEMO' AND b.code = 'TNG'"
                );
                $anna = (int) $this->scalar(
                    "SELECT b.id FROM branches b JOIN organizations o ON o.id = b.organization_id
                      WHERE o.code = 'DEMO' AND b.code = 'ANN'"
                );

                Assert::status(403, $this->request('GET', '/api/patients',
                    query: ['branch_id' => $tnagar], as: 'reception'), 'unassigned branch must be refused');
                Assert::status(200, $this->request('GET', '/api/patients',
                    query: ['branch_id' => $anna], as: 'reception'));

                // …and a branch belonging to another organization is also refused.
                Assert::status(403, $this->request('GET', '/api/patients',
                    query: ['branch_id' => $this->orgBBranchId], as: 'reception'));

                // Unfiltered lists are automatically narrowed to the assigned branches.
                $list = $this->request('GET', '/api/patients', query: ['per_page' => 100], as: 'reception');
                Assert::status(200, $list);
                foreach ($list['body']['data'] as $row) {
                    Assert::true(
                        $row['branch_id'] === $anna,
                        'patient from an unassigned branch appeared in the default list'
                    );
                }
            },

            'S9 a mutating request without the CSRF header is rejected' => function () {
                $this->login('adminA', 'admin@demoklinik.test');
                $result = $this->request('POST', '/api/patients',
                    ['full_name' => 'No CSRF', 'phone' => '9000000002'],
                    as: 'adminA', withCsrf: false);
                Assert::status(419, $result);
                Assert::same('CSRF_TOKEN_MISMATCH', $result['body']['error']['code']);

                // Reads are unaffected.
                Assert::status(200, $this->request('GET', '/api/patients', as: 'adminA', withCsrf: false));
            },

            'S10 a revoked session is rejected on the next request' => function () {
                $session = $this->login('revoke', 'admin@demoklinik.test');
                Assert::status(200, $this->request('GET', '/api/auth/me', as: 'revoke'));

                $token = $session['cookies']['clinicflow_session'];
                Database::run('UPDATE sessions SET revoked_at = NOW() WHERE token_hash = :h',
                    ['h' => hash('sha256', $token)]);

                $result = $this->request('GET', '/api/auth/me', as: 'revoke');
                Assert::status(401, $result);
            },

            'S10b an expired session is rejected' => function () {
                $session = $this->login('expired', 'admin@demoklinik.test');
                Database::run(
                    'UPDATE sessions SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)
                      WHERE token_hash = :h',
                    ['h' => hash('sha256', $session['cookies']['clinicflow_session'])]
                );
                Assert::status(401, $this->request('GET', '/api/auth/me', as: 'expired'));
            },

            'S11 a deactivated user loses access immediately' => function () {
                $this->login('victim', 'operations@demoklinik.test');
                Assert::status(200, $this->request('GET', '/api/auth/me', as: 'victim'));

                $userId = (int) $this->scalar('SELECT id FROM users WHERE email = :e',
                    ['e' => 'operations@demoklinik.test']);
                Database::run('UPDATE users SET is_active = 0 WHERE id = :id', ['id' => $userId]);
                try {
                    $result = $this->request('GET', '/api/auth/me', as: 'victim');
                    Assert::status(403, $result, 'a deactivated user must be refused mid-session');
                } finally {
                    Database::run('UPDATE users SET is_active = 1 WHERE id = :id', ['id' => $userId]);
                }
            },

            'S12 a suspended organization blocks its users mid-session' => function () {
                $this->login('suspendee', 'admin@citycare.test');
                Assert::status(200, $this->request('GET', '/api/auth/me', as: 'suspendee'));

                $orgId = (int) $this->scalar("SELECT id FROM organizations WHERE code = 'CITYCARE'");
                Database::run("UPDATE organizations SET status = 'suspended' WHERE id = :id", ['id' => $orgId]);
                try {
                    $result = $this->request('GET', '/api/auth/me', as: 'suspendee');
                    Assert::status(403, $result);
                    // …and login is refused too.
                    try {
                        $this->login('suspendee2', 'admin@citycare.test');
                        Assert::fail('login should be refused while the clinic is suspended');
                    } catch (\RuntimeException $e) {
                        Assert::true(str_contains($e->getMessage(), 'suspended'), $e->getMessage());
                    }
                } finally {
                    Database::run("UPDATE organizations SET status = 'active' WHERE id = :id", ['id' => $orgId]);
                }
            },

            'S13 SQL injection payloads are neutralised' => function () {
                $this->login('adminA', 'admin@demoklinik.test');
                $payloads = [
                    "' OR 1=1 --",
                    "'; DROP TABLE patients; --",
                    "1' UNION SELECT id, email, password_hash FROM users --",
                    "%' AND organization_id <> 1 --",
                ];
                foreach ($payloads as $payload) {
                    $result = $this->request('GET', '/api/patients',
                        query: ['q' => $payload, 'per_page' => 100], as: 'adminA');
                    Assert::status(200, $result, "payload: {$payload}");
                    Assert::same(0, count($result['body']['data']), "payload returned rows: {$payload}");
                }
                // The schema is intact.
                Assert::true((int) $this->scalar('SELECT COUNT(*) FROM patients') > 0,
                    'patients table must still exist and hold rows');
            },

            'S14 an invalid sort column is refused, not interpolated' => function () {
                $this->login('adminA', 'admin@demoklinik.test');
                $result = $this->request('GET', '/api/patients',
                    query: ['sort' => 'id; DROP TABLE patients'], as: 'adminA');
                Assert::status(400, $result);
                Assert::same('INVALID_SORT', $result['body']['error']['code']);
                Assert::true((int) $this->scalar('SELECT COUNT(*) FROM patients') > 0);
            },

            'S15 repeated bad logins lock the account' => function () {
                $email = 'lockme@demoklinik.test';
                $orgId = (int) $this->scalar("SELECT id FROM organizations WHERE code = 'DEMO'");
                $roleId = (int) $this->scalar(
                    "SELECT id FROM roles WHERE organization_id = :o AND slug = 'receptionist'", ['o' => $orgId]
                );
                Database::run('DELETE FROM users WHERE email = :e', ['e' => $email]);
                // Earlier tests deliberately fail logins; clear the window so this test measures
                // the per-account lock-out rather than the shared per-IP throttle.
                Database::run('DELETE FROM login_attempts');
                Database::run(
                    'INSERT INTO users (public_id, organization_id, role_id, name, email, password_hash,
                                        is_active, all_branches, created_at)
                     VALUES (:p, :o, :r, "Lock Test", :e, :h, 1, 1, UTC_TIMESTAMP())',
                    ['p' => \App\Core\Str::ulid(), 'o' => $orgId, 'r' => $roleId, 'e' => $email,
                     'h' => \App\Domain\Services\AuthService::hash('ClinicFlow#2026')]
                );

                for ($i = 0; $i < 5; $i++) {
                    $result = $this->request('POST', '/api/auth/login',
                        ['email' => $email, 'password' => 'wrong-password']);
                    Assert::status(401, $result);
                    // The message must never distinguish "no such user" from "wrong password".
                    Assert::same('Invalid email or password', $result['body']['error']['message']);
                }
                $locked = $this->scalar('SELECT locked_until FROM users WHERE email = :e', ['e' => $email]);
                Assert::true($locked !== null, 'account should be locked after 5 failures');

                // Even the CORRECT password is refused while locked.
                $result = $this->request('POST', '/api/auth/login',
                    ['email' => $email, 'password' => 'ClinicFlow#2026']);
                Assert::status(401, $result);

                Database::run('DELETE FROM users WHERE email = :e', ['e' => $email]);
                Database::run('DELETE FROM login_attempts');
            },

            'S16 platform admin cannot touch clinic endpoints' => function () {
                $this->login('platform', 'platform@clinicflow.test');
                foreach (['/api/patients', '/api/users', '/api/branches', '/api/settings',
                          '/api/dashboard/summary', '/api/audit-logs'] as $path) {
                    Assert::status(403, $this->request('GET', $path, as: 'platform'),
                        "platform admin reached {$path}");
                }
                // Its own area works.
                Assert::status(200, $this->request('GET', '/api/platform/overview', as: 'platform'));
            },

            'S16b clinic users cannot touch platform endpoints' => function () {
                $this->login('adminA', 'admin@demoklinik.test');
                foreach (['/api/platform/overview', '/api/platform/organizations',
                          '/api/platform/plans', '/api/platform/users'] as $path) {
                    Assert::status(403, $this->request('GET', $path, as: 'adminA'),
                        "clinic admin reached {$path}");
                }
            },

            'S17 unauthenticated requests are refused' => function () {
                foreach ([['GET', '/api/patients'], ['GET', '/api/auth/me'],
                          ['GET', '/api/dashboard/summary'], ['POST', '/api/patients']] as [$method, $path]) {
                    $result = $this->request($method, $path);
                    Assert::status(401, $result, "{$method} {$path} should require authentication");
                }
                // Public endpoints still work.
                Assert::status(200, $this->request('GET', '/api/health'));
            },

            'S18 a forged session cookie is rejected' => function () {
                $request = \App\Core\Request::create('GET', '/api/patients', [], [],
                    ['content-type' => 'application/json'],
                    ['clinicflow_session' => str_repeat('a', 64)]);
                $response = self::kernel()->handle($request);
                Assert::same(401, $response->status);
            },

            'S19 every non-public route declares a permission' => function () {
                $undeclared = [];
                foreach (self::kernel()->router()->all() as $route) {
                    if (!$route->public && !$route->authOnly && $route->permission === null) {
                        $undeclared[] = $route->method . ' ' . $route->path;
                    }
                }
                Assert::same([], $undeclared, 'routes without an authorization decision: '
                    . implode(', ', $undeclared));
            },

            'S20 password hashes are never returned by the API' => function () {
                $this->login('adminA', 'admin@demoklinik.test');
                foreach (['/api/users', '/api/auth/me', '/api/patients'] as $path) {
                    $result = $this->request('GET', $path, as: 'adminA');
                    $json = json_encode($result['body']);
                    Assert::true(!str_contains($json, 'password_hash'), "password_hash leaked at {$path}");
                    Assert::true(!str_contains((string) $json, '$2y$') && !str_contains((string) $json, '$argon2'),
                        "a password hash value leaked at {$path}");
                }
            },
        ];
    }
}
