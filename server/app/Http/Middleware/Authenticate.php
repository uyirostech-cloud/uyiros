<?php
namespace App\Http\Middleware;

use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Str;

/**
 * Resolves the opaque session cookie to a session row.
 * Enforces revocation, absolute lifetime and idle timeout on EVERY request.
 */
final class Authenticate
{
    /** @return array{session:array,user:array} */
    public static function resolve(Request $request): array
    {
        $cookieName = Config::get('session.cookie_name');
        $token = $request->cookie($cookieName);
        if (!is_string($token) || $token === '') {
            throw HttpException::unauthenticated();
        }

        $hash = hash('sha256', $token);
        $row = Database::selectOne(
            'SELECT s.id AS session_id, s.user_id, s.csrf_token, s.expires_at, s.revoked_at, s.last_seen_at,
                    u.id, u.name, u.email, u.organization_id, u.role_id, u.is_platform_admin,
                    u.is_active, u.all_branches
               FROM sessions s
               JOIN users u ON u.id = s.user_id
              WHERE s.token_hash = :h
              LIMIT 1',
            ['h' => $hash]
        );

        if ($row === null || $row['revoked_at'] !== null) {
            throw HttpException::unauthenticated('Session is no longer valid');
        }
        $now = Str::now();
        if ($row['expires_at'] < $now) {
            throw HttpException::unauthenticated('Session has expired');
        }
        $idleMinutes = (int) Config::get('session.idle_minutes', 120);
        if ($row['last_seen_at'] !== null
            && strtotime($row['last_seen_at']) < strtotime($now) - $idleMinutes * 60) {
            Database::run('UPDATE sessions SET revoked_at = :n WHERE id = :id',
                ['n' => $now, 'id' => $row['session_id']]);
            throw HttpException::unauthenticated('Session expired due to inactivity');
        }

        Database::run('UPDATE sessions SET last_seen_at = :n WHERE id = :id',
            ['n' => $now, 'id' => $row['session_id']]);

        return [
            'session' => [
                'id'         => (int) $row['session_id'],
                'csrf_token' => $row['csrf_token'],
            ],
            'user' => [
                'id'                => (int) $row['id'],
                'name'              => $row['name'],
                'email'             => $row['email'],
                'organization_id'   => $row['organization_id'] === null ? null : (int) $row['organization_id'],
                'role_id'           => $row['role_id'] === null ? null : (int) $row['role_id'],
                'is_platform_admin' => (int) $row['is_platform_admin'] === 1,
                'is_active'         => (int) $row['is_active'] === 1,
                'all_branches'      => (int) $row['all_branches'] === 1,
            ],
        ];
    }
}
