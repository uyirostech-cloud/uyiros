<?php
namespace App\Domain\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Http\Middleware\RateLimit;

final class AuthService
{
    private const MAX_FAILED = 5;
    private const LOCK_MINUTES = 15;

    public static function hash(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)
            ? PASSWORD_ARGON2ID
            : PASSWORD_DEFAULT;
        return password_hash($password, $algo);
    }

    /** @return array{user:array,token:string,csrf:string,expires_at:string} */
    public static function login(Request $request, string $email, string $password): array
    {
        $email = strtolower(trim($email));
        RateLimit::assertLoginAllowed($email, $request->ip);

        $user = Database::selectOne(
            'SELECT id, organization_id, name, email, password_hash, is_active, is_platform_admin,
                    failed_attempts, locked_until, must_change_password
               FROM users WHERE email = :e AND deleted_at IS NULL LIMIT 1',
            ['e' => $email]
        );

        // Constant-ish work whether or not the user exists, so timing does not reveal accounts.
        $hash = $user['password_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
        $ok = password_verify($password, $hash);

        if ($user === null || !$ok) {
            RateLimit::recordLoginAttempt($email, $request->ip, false);
            if ($user !== null) {
                self::registerFailure((int) $user['id'], (int) $user['failed_attempts']);
            }
            AuditService::logAnonymous(
                $user['organization_id'] ?? null,
                $user['id'] ?? null,
                'auth.login_failed',
                ['email' => $email],
                $request->ip,
                $request->userAgent
            );
            throw new HttpException(401, 'INVALID_CREDENTIALS', 'Invalid email or password');
        }

        if ($user['locked_until'] !== null && $user['locked_until'] > Str::now()) {
            RateLimit::recordLoginAttempt($email, $request->ip, false);
            throw new HttpException(401, 'INVALID_CREDENTIALS', 'Invalid email or password');
        }

        if ((int) $user['is_active'] !== 1) {
            RateLimit::recordLoginAttempt($email, $request->ip, false);
            throw HttpException::forbidden('Your account has been deactivated. Contact your clinic administrator.');
        }

        if ($user['organization_id'] !== null) {
            $status = Database::scalar('SELECT status FROM organizations WHERE id = :o',
                ['o' => $user['organization_id']]);
            if (!in_array((string) $status, ['active', 'trial'], true)) {
                RateLimit::recordLoginAttempt($email, $request->ip, false);
                throw HttpException::forbidden('This clinic account is currently ' . $status . '. Contact support.');
            }
        }

        // Upgrade the hash transparently if the algorithm/cost has moved on.
        $algo = defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true)
            ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        if (password_needs_rehash($user['password_hash'], $algo)) {
            Database::run('UPDATE users SET password_hash = :h WHERE id = :id',
                ['h' => self::hash($password), 'id' => $user['id']]);
        }

        $session = self::createSession((int) $user['id'], $request);

        Database::run(
            'UPDATE users SET last_login_at = :n, failed_attempts = 0, locked_until = NULL WHERE id = :id',
            ['n' => Str::now(), 'id' => $user['id']]
        );
        RateLimit::recordLoginAttempt($email, $request->ip, true);
        AuditService::logAnonymous(
            $user['organization_id'] === null ? null : (int) $user['organization_id'],
            (int) $user['id'],
            'auth.login',
            [],
            $request->ip,
            $request->userAgent
        );

        return $session + ['user' => $user];
    }

    /** @return array{token:string,csrf:string,expires_at:string,session_id:int} */
    public static function createSession(int $userId, Request $request): array
    {
        $token = Str::token(32);
        $csrf = Str::token(32);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + Config::get('session.absolute_minutes', 720) * 60);

        $id = Database::insert(
            'INSERT INTO sessions (user_id, token_hash, csrf_token, ip, user_agent, created_at, last_seen_at, expires_at)
             VALUES (:u, :t, :c, :ip, :ua, :now, :now2, :exp)',
            [
                'u' => $userId, 't' => hash('sha256', $token), 'c' => $csrf,
                'ip' => $request->ip, 'ua' => $request->userAgent,
                'now' => Str::now(), 'now2' => Str::now(), 'exp' => $expiresAt,
            ]
        );
        return ['token' => $token, 'csrf' => $csrf, 'expires_at' => $expiresAt, 'session_id' => $id];
    }

    public static function revokeSession(int $sessionId): void
    {
        Database::run('UPDATE sessions SET revoked_at = :n WHERE id = :id AND revoked_at IS NULL',
            ['n' => Str::now(), 'id' => $sessionId]);
    }

    public static function revokeAllForUser(int $userId): void
    {
        Database::run('UPDATE sessions SET revoked_at = :n WHERE user_id = :u AND revoked_at IS NULL',
            ['n' => Str::now(), 'u' => $userId]);
    }

    private static function registerFailure(int $userId, int $current): void
    {
        $attempts = $current + 1;
        $lock = $attempts >= self::MAX_FAILED
            ? gmdate('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60)
            : null;
        Database::run('UPDATE users SET failed_attempts = :a, locked_until = :l WHERE id = :id',
            ['a' => $attempts, 'l' => $lock, 'id' => $userId]);
    }

    /** Attach the session + CSRF cookies to a response. */
    public static function withSessionCookies(Response $response, string $token, string $csrf, string $expiresAt): Response
    {
        $secure = (bool) Config::get('session.secure', false)
            || Config::get('app.env') === 'production';
        $sameSite = (string) Config::get('session.samesite', 'Lax');
        $maxAge = max(0, strtotime($expiresAt) - time());

        return $response
            ->withCookie((string) Config::get('session.cookie_name'), $token, [
                'expires'  => time() + $maxAge,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,            // unreachable from JavaScript
                'samesite' => $sameSite,
            ])
            ->withCookie((string) Config::get('session.csrf_cookie_name'), $csrf, [
                'expires'  => time() + $maxAge,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => false,           // the SPA must read this to send X-CSRF-Token
                'samesite' => $sameSite,
            ]);
    }

    public static function clearSessionCookies(Response $response): Response
    {
        $secure = (bool) Config::get('session.secure', false) || Config::get('app.env') === 'production';
        foreach ([Config::get('session.cookie_name'), Config::get('session.csrf_cookie_name')] as $name) {
            $response->withCookie((string) $name, '', [
                'expires' => time() - 3600, 'path' => '/', 'secure' => $secure,
                'httponly' => $name === Config::get('session.cookie_name'),
                'samesite' => (string) Config::get('session.samesite', 'Lax'),
            ]);
        }
        return $response;
    }

    public static function validatePasswordStrength(string $password): void
    {
        $errors = [];
        if (strlen($password) < 10) {
            $errors[] = 'at least 10 characters';
        }
        if (!preg_match('/[A-Za-z]/', $password)) {
            $errors[] = 'a letter';
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'a number';
        }
        if ($errors !== []) {
            throw HttpException::validation(['password' => 'Password must contain ' . implode(', ', $errors)]);
        }
    }
}
