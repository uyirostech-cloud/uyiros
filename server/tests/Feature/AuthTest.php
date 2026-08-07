<?php
namespace Tests\Feature;

use App\Core\Database;
use Tests\Assert;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    public function tests(): array
    {
        return [
            'A1 valid credentials return the user with permissions and branches' => function () {
                $result = $this->request('POST', '/api/auth/login', [
                    'email' => 'admin@demoklinik.test', 'password' => 'ClinicFlow#2026',
                ]);
                Assert::status(200, $result);
                $user = $result['body']['data']['user'];
                Assert::same('admin@demoklinik.test', $user['email']);
                Assert::same('clinic_admin', $user['role']);
                Assert::contains('patient.view', $user['permissions']);
                Assert::contains('settings.manage', $user['permissions']);
                Assert::true($user['organization_id'] !== null);
                Assert::true(count($user['branch_ids']) === 2, 'admin has all branches');
                Assert::true(!empty($result['body']['data']['csrf_token']));
            },

            'A2 the session cookie is HttpOnly and the CSRF cookie is readable' => function () {
                $result = $this->request('POST', '/api/auth/login', [
                    'email' => 'admin@demoklinik.test', 'password' => 'ClinicFlow#2026',
                ]);
                $cookies = [];
                foreach ($result['response']->cookies as $c) {
                    $cookies[$c['name']] = $c['options'];
                }
                Assert::true(isset($cookies['clinicflow_session']), 'session cookie missing');
                Assert::same(true, $cookies['clinicflow_session']['httponly'],
                    'the session token must be unreachable from JavaScript');
                Assert::same('Lax', $cookies['clinicflow_session']['samesite']);
                Assert::same(false, $cookies['clinicflow_csrf']['httponly'],
                    'the SPA must be able to read the CSRF token');
            },

            'A3 the raw session token is never stored in the database' => function () {
                $session = $this->login('probe', 'admin@demoklinik.test');
                $token = $session['cookies']['clinicflow_session'];
                Assert::same(64, strlen($token));
                $stored = $this->scalar('SELECT COUNT(*) FROM sessions WHERE token_hash = :raw',
                    ['raw' => $token]);
                Assert::same(0, (int) $stored, 'the raw token must never appear in sessions.token_hash');
                Assert::same(1, (int) $this->scalar(
                    'SELECT COUNT(*) FROM sessions WHERE token_hash = :h AND revoked_at IS NULL',
                    ['h' => hash('sha256', $token)]
                ));
            },

            'A4 wrong password is refused with a generic message' => function () {
                $result = $this->request('POST', '/api/auth/login', [
                    'email' => 'admin@demoklinik.test', 'password' => 'definitely-wrong',
                ]);
                Assert::status(401, $result);
                Assert::same('Invalid email or password', $result['body']['error']['message']);
            },

            'A5 an unknown email gives the same response as a wrong password' => function () {
                $result = $this->request('POST', '/api/auth/login', [
                    'email' => 'nobody@nowhere.test', 'password' => 'whatever',
                ]);
                Assert::status(401, $result);
                Assert::same('Invalid email or password', $result['body']['error']['message']);
            },

            'A6 logout revokes the session' => function () {
                $session = $this->login('bye', 'reception@demoklinik.test');
                Assert::status(200, $this->request('POST', '/api/auth/logout', as: 'bye'));
                Assert::status(401, $this->request('GET', '/api/auth/me', as: 'bye'));
                Assert::true(
                    $this->scalar('SELECT revoked_at FROM sessions WHERE token_hash = :h',
                        ['h' => hash('sha256', $session['cookies']['clinicflow_session'])]) !== null
                );
            },

            'A7 forgot-password never reveals whether an account exists' => function () {
                $known = $this->request('POST', '/api/auth/forgot-password',
                    ['email' => 'admin@demoklinik.test']);
                $unknown = $this->request('POST', '/api/auth/forgot-password',
                    ['email' => 'nobody@nowhere.test']);
                Assert::status(200, $known);
                Assert::status(200, $unknown);
                Assert::same($known['body']['data']['message'], $unknown['body']['data']['message']);
            },

            'A8 the reset flow changes the password and revokes existing sessions' => function () {
                $email = 'resettest@demoklinik.test';
                $orgId = (int) $this->scalar("SELECT id FROM organizations WHERE code = 'DEMO'");
                $roleId = (int) $this->scalar(
                    "SELECT id FROM roles WHERE organization_id = :o AND slug = 'receptionist'", ['o' => $orgId]
                );
                Database::run('DELETE FROM users WHERE email = :e', ['e' => $email]);
                Database::run(
                    'INSERT INTO users (public_id, organization_id, role_id, name, email, password_hash,
                                        is_active, all_branches, created_at)
                     VALUES (:p, :o, :r, "Reset Test", :e, :h, 1, 1, UTC_TIMESTAMP())',
                    ['p' => \App\Core\Str::ulid(), 'o' => $orgId, 'r' => $roleId, 'e' => $email,
                     'h' => \App\Domain\Services\AuthService::hash('ClinicFlow#2026')]
                );

                $this->login('resetter', $email);
                $forgot = $this->request('POST', '/api/auth/forgot-password', ['email' => $email]);
                $token = $forgot['body']['data']['dev_reset_token'] ?? null;
                Assert::true($token !== null, 'dev reset token should be exposed outside production');

                $reset = $this->request('POST', '/api/auth/reset-password', [
                    'token' => $token, 'password' => 'BrandNew#2026', 'password_confirmation' => 'BrandNew#2026',
                ]);
                Assert::status(200, $reset);

                // The old session is gone; the old password no longer works; the new one does.
                Assert::status(401, $this->request('GET', '/api/auth/me', as: 'resetter'));
                Assert::status(401, $this->request('POST', '/api/auth/login',
                    ['email' => $email, 'password' => 'ClinicFlow#2026']));
                Assert::status(200, $this->request('POST', '/api/auth/login',
                    ['email' => $email, 'password' => 'BrandNew#2026']));

                // A reset token is single-use.
                Assert::status(400, $this->request('POST', '/api/auth/reset-password', [
                    'token' => $token, 'password' => 'Another#2026', 'password_confirmation' => 'Another#2026',
                ]));

                Database::run('DELETE FROM users WHERE email = :e', ['e' => $email]);
            },

            'A9 weak passwords are refused' => function () {
                foreach (['short', 'alllettersonly', '1234567890'] as $weak) {
                    $result = $this->request('POST', '/api/auth/reset-password', [
                        'token' => str_repeat('f', 64), 'password' => $weak, 'password_confirmation' => $weak,
                    ]);
                    Assert::status(422, $result, "weak password accepted: {$weak}");
                }
            },

            'A10 login input is validated' => function () {
                Assert::status(422, $this->request('POST', '/api/auth/login', []));
                Assert::status(422, $this->request('POST', '/api/auth/login', ['email' => 'a@b.test']));
            },

            'A11 the health endpoint reports the database and migrations' => function () {
                $result = $this->request('GET', '/api/health');
                Assert::status(200, $result);
                Assert::same('ok', $result['body']['data']['status']);
                Assert::same('ok', $result['body']['data']['db']);
                Assert::true($result['body']['data']['migrations'] >= 3);
            },

            'A12 login is audited' => function () {
                $before = (int) $this->scalar("SELECT COUNT(*) FROM audit_logs WHERE action = 'auth.login'");
                $this->login('audited', 'accounts@demoklinik.test');
                $after = (int) $this->scalar("SELECT COUNT(*) FROM audit_logs WHERE action = 'auth.login'");
                Assert::true($after > $before, 'a successful login must be audited');

                $this->request('POST', '/api/auth/login',
                    ['email' => 'accounts@demoklinik.test', 'password' => 'nope']);
                Assert::true(
                    (int) $this->scalar("SELECT COUNT(*) FROM audit_logs WHERE action = 'auth.login_failed'") > 0,
                    'a failed login must be audited'
                );
            },
        ];
    }
}
