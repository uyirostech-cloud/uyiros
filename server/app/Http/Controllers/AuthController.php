<?php
namespace App\Http\Controllers;

use App\Core\Config;
use App\Core\Ctx;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\Validator;
use App\Domain\Services\AuditService;
use App\Domain\Services\AuthService;
use App\Domain\Services\OrganizationProvisioningService;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RateLimit;
use App\Http\Middleware\ResolveTenant;

final class AuthController
{
    /**
     * Self-service SaaS signup: creates a new organization on a 14-day trial, its default
     * roles, a first branch, and a Clinic Admin user — then logs that user straight in.
     * This is the "subscribe" flow the public landing/pricing pages lead to.
     */
    public function signup(Request $request): Response
    {
        RateLimit::assertSignupAllowed($request->ip);
        RateLimit::recordSignupAttempt($request->ip, true);

        $data = Validator::make($request->only([
            'organization_name', 'admin_name', 'admin_email', 'admin_password',
            'phone', 'city', 'state', 'plan_code',
        ]), [
            'organization_name' => 'required|string|minlen:2|maxlen:160',
            'admin_name'        => 'required|string|minlen:2|maxlen:120',
            'admin_email'       => 'required|email|maxlen:190',
            'admin_password'    => 'required|string|maxlen:200',
            'phone'             => 'nullable|phone|maxlen:30',
            'city'              => 'nullable|string|maxlen:80',
            'state'             => 'nullable|string|maxlen:80',
            'plan_code'         => 'nullable|string|exists:plans,code',
        ]);
        AuthService::validatePasswordStrength((string) $data['admin_password']);

        if (Database::scalar('SELECT id FROM users WHERE email = :e', ['e' => $data['admin_email']]) !== null) {
            throw HttpException::validation(['admin_email' => 'This email address is already registered']);
        }

        $planId = null;
        if (!empty($data['plan_code'])) {
            $planId = Database::scalar('SELECT id FROM plans WHERE code = :c AND is_active = 1',
                ['c' => $data['plan_code']]);
        }

        $result = OrganizationProvisioningService::provision([
            'name'   => $data['organization_name'],
            'code'   => OrganizationProvisioningService::generateCode((string) $data['organization_name']),
            'email'  => $data['admin_email'],
            'phone'  => $data['phone'] ?? null,
            'city'   => $data['city'] ?? null,
            'state'  => $data['state'] ?? null,
            'status' => 'trial',
            'plan_id' => $planId === null ? null : (int) $planId,
            'admin_name'          => $data['admin_name'],
            'admin_email'         => $data['admin_email'],
            'admin_password_hash' => AuthService::hash((string) $data['admin_password']),
        ]);

        AuditService::logAnonymous(
            $result['organization_id'], $result['user_id'], 'organization.self_registered',
            ['organization_name' => $data['organization_name'], 'plan_code' => $data['plan_code'] ?? null],
            $request->ip, $request->userAgent
        );

        $session = AuthService::createSession($result['user_id'], $request);
        $auth = [
            'session' => ['id' => $session['session_id'], 'csrf_token' => $session['csrf']],
            'user'    => $this->userRow($result['user_id']),
        ];
        $ctx = ResolveTenant::build($auth, $request, false);

        $response = Response::json([
            'user'                  => $ctx->toArray(),
            'csrf_token'            => $session['csrf'],
            'expires_at'            => $session['expires_at'],
            'must_change_password'  => false,
            'trial_ends_at'         => Database::scalar('SELECT trial_ends_at FROM organizations WHERE id = :o',
                ['o' => $result['organization_id']]),
        ], 201);
        return AuthService::withSessionCookies($response, $session['token'], $session['csrf'], $session['expires_at']);
    }

    public function login(Request $request): Response
    {
        $data = Validator::make($request->only(['email', 'password']), [
            'email'    => 'required|string|maxlen:190',
            'password' => 'required|string|maxlen:200',
        ]);

        $result = AuthService::login($request, (string) $data['email'], (string) $data['password']);

        // Rebuild the full context so the client receives its permissions immediately.
        $auth = [
            'session' => ['id' => $result['session_id'], 'csrf_token' => $result['csrf']],
            'user'    => $this->userRow((int) $result['user']['id']),
        ];
        $ctx = ResolveTenant::build($auth, $request, (bool) $auth['user']['is_platform_admin']);

        $response = Response::json([
            'user'       => $ctx->toArray(),
            'csrf_token' => $result['csrf'],
            'expires_at' => $result['expires_at'],
            'must_change_password' => (bool) ($result['user']['must_change_password'] ?? false),
        ]);
        return AuthService::withSessionCookies($response, $result['token'], $result['csrf'], $result['expires_at']);
    }

    public function me(Request $request, Ctx $ctx): Response
    {
        $csrf = Database::scalar('SELECT csrf_token FROM sessions WHERE id = :id', ['id' => $ctx->sessionId]);
        $branches = $ctx->isPlatformAdmin ? [] : Database::select(
            'SELECT id, name, code FROM branches
              WHERE organization_id = :o AND is_active = 1 AND deleted_at IS NULL
              ORDER BY name',
            ['o' => $ctx->orgId()]
        );
        $allowed = array_values(array_filter(
            $branches,
            static fn ($b) => $ctx->canUseBranch((int) $b['id'])
        ));

        return Response::json([
            'user'       => $ctx->toArray(),
            'branches'   => array_map(static fn ($b) => [
                'id' => (int) $b['id'], 'name' => $b['name'], 'code' => $b['code'],
            ], $allowed),
            'csrf_token' => $csrf,
        ]);
    }

    public function logout(Request $request, Ctx $ctx): Response
    {
        AuthService::revokeSession($ctx->sessionId);
        AuditService::log($ctx, 'auth.logout');
        return AuthService::clearSessionCookies(Response::json(['message' => 'Signed out']));
    }

    public function changePassword(Request $request, Ctx $ctx): Response
    {
        $data = Validator::make($request->only(['current_password', 'password', 'password_confirmation']), [
            'current_password'      => 'required|string',
            'password'              => 'required|string|maxlen:200',
            'password_confirmation' => 'required|string',
        ]);
        if ($data['password'] !== $data['password_confirmation']) {
            throw HttpException::validation(['password_confirmation' => 'Passwords do not match']);
        }
        AuthService::validatePasswordStrength((string) $data['password']);

        $hash = (string) Database::scalar('SELECT password_hash FROM users WHERE id = :id', ['id' => $ctx->userId]);
        if (!password_verify((string) $data['current_password'], $hash)) {
            throw HttpException::validation(['current_password' => 'Current password is incorrect']);
        }

        Database::run(
            'UPDATE users SET password_hash = :h, must_change_password = 0, updated_at = :u WHERE id = :id',
            ['h' => AuthService::hash((string) $data['password']), 'u' => Str::now(), 'id' => $ctx->userId]
        );
        AuthService::revokeAllForUser($ctx->userId);
        AuditService::log($ctx, 'auth.password_changed', 'user', $ctx->userId);

        return AuthService::clearSessionCookies(
            Response::json(['message' => 'Password updated. Please sign in again.'])
        );
    }

    /** Always 200 — never reveals whether an account exists. */
    public function forgotPassword(Request $request): Response
    {
        $data = Validator::make($request->only(['email']), ['email' => 'required|string|maxlen:190']);
        $email = strtolower(trim((string) $data['email']));

        $user = Database::selectOne(
            'SELECT id, organization_id, is_active FROM users WHERE email = :e AND deleted_at IS NULL LIMIT 1',
            ['e' => $email]
        );

        $payload = ['message' => 'If that email is registered, a reset link has been sent.'];

        if ($user !== null && (int) $user['is_active'] === 1) {
            $token = Str::token(32);
            Database::run(
                'INSERT INTO password_resets (user_id, token_hash, created_at, expires_at, ip)
                 VALUES (:u, :t, :c, :e, :ip)',
                [
                    'u' => $user['id'], 't' => hash('sha256', $token), 'c' => Str::now(),
                    'e' => gmdate('Y-m-d H:i:s', time() + Config::get('session.reset_ttl_minutes', 60) * 60),
                    'ip' => $request->ip,
                ]
            );
            AuditService::logAnonymous(
                $user['organization_id'] === null ? null : (int) $user['organization_id'],
                (int) $user['id'], 'auth.password_reset_requested', [], $request->ip, $request->userAgent
            );
            // Delivery is out of V1 scope; in local/dev the token is returned so the flow is testable.
            if (Config::get('app.env') !== 'production') {
                $payload['dev_reset_token'] = $token;
            }
        }
        return Response::json($payload);
    }

    public function resetPassword(Request $request): Response
    {
        $data = Validator::make($request->only(['token', 'password', 'password_confirmation']), [
            'token'                 => 'required|string|maxlen:200',
            'password'              => 'required|string|maxlen:200',
            'password_confirmation' => 'required|string',
        ]);
        if ($data['password'] !== $data['password_confirmation']) {
            throw HttpException::validation(['password_confirmation' => 'Passwords do not match']);
        }
        AuthService::validatePasswordStrength((string) $data['password']);

        $row = Database::selectOne(
            'SELECT id, user_id, expires_at, used_at FROM password_resets WHERE token_hash = :t LIMIT 1',
            ['t' => hash('sha256', (string) $data['token'])]
        );
        if ($row === null || $row['used_at'] !== null || $row['expires_at'] < Str::now()) {
            throw HttpException::badRequest('This reset link is invalid or has expired', 'INVALID_RESET_TOKEN');
        }

        Database::transaction(function () use ($row, $data) {
            Database::run(
                'UPDATE users SET password_hash = :h, must_change_password = 0, failed_attempts = 0,
                        locked_until = NULL, updated_at = :u WHERE id = :id',
                ['h' => AuthService::hash((string) $data['password']), 'u' => Str::now(), 'id' => $row['user_id']]
            );
            Database::run('UPDATE password_resets SET used_at = :n WHERE id = :id',
                ['n' => Str::now(), 'id' => $row['id']]);
            AuthService::revokeAllForUser((int) $row['user_id']);
        });

        AuditService::logAnonymous(null, (int) $row['user_id'], 'auth.password_reset', [], $request->ip, $request->userAgent);
        return Response::json(['message' => 'Password reset. You can now sign in.']);
    }

    private function userRow(int $id): array
    {
        $u = Database::selectOne(
            'SELECT id, name, email, organization_id, role_id, is_platform_admin, is_active, all_branches
               FROM users WHERE id = :id',
            ['id' => $id]
        );
        return [
            'id'                => (int) $u['id'],
            'name'              => $u['name'],
            'email'             => $u['email'],
            'organization_id'   => $u['organization_id'] === null ? null : (int) $u['organization_id'],
            'role_id'           => $u['role_id'] === null ? null : (int) $u['role_id'],
            'is_platform_admin' => (int) $u['is_platform_admin'] === 1,
            'is_active'         => (int) $u['is_active'] === 1,
            'all_branches'      => (int) $u['all_branches'] === 1,
        ];
    }
}
