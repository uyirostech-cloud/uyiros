<?php
namespace App\Http\Middleware;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Str;

/**
 * DB-backed attempt throttling — no Redis/APCu assumption on shared hosting.
 *
 * Email and IP are counted separately and with different thresholds on purpose: a clinic's
 * staff usually share one NAT address, so a strict per-IP limit would lock the whole practice
 * out because of one person's typo. The per-email limit is what actually protects an account;
 * the per-IP limit only exists to blunt broad credential-stuffing from a single source.
 */
final class RateLimit
{
    private const EMAIL_MAX  = 10;   // failures per email per window
    private const IP_MAX     = 60;   // failures per source address per window
    private const WINDOW_MIN = 15;

    public static function recordLoginAttempt(string $email, string $ip, bool $succeeded): void
    {
        Database::run(
            'INSERT INTO login_attempts (email, ip, succeeded, created_at) VALUES (:e, :i, :s, :c)',
            ['e' => mb_substr($email, 0, 190), 'i' => $ip, 's' => $succeeded ? 1 : 0, 'c' => Str::now()]
        );
    }

    public static function assertLoginAllowed(string $email, string $ip): void
    {
        $since = gmdate('Y-m-d H:i:s', time() - self::WINDOW_MIN * 60);

        $byEmail = (int) Database::scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE succeeded = 0 AND created_at >= :s AND email = :e',
            ['s' => $since, 'e' => mb_substr($email, 0, 190)]
        );
        if ($byEmail >= self::EMAIL_MAX) {
            throw HttpException::tooManyRequests('Too many failed attempts. Please try again later.');
        }

        $byIp = (int) Database::scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE succeeded = 0 AND created_at >= :s AND ip = :i',
            ['s' => $since, 'i' => $ip]
        );
        if ($byIp >= self::IP_MAX) {
            throw HttpException::tooManyRequests('Too many failed attempts from this network. Please try again later.');
        }
    }

    public static function prune(int $days = 7): void
    {
        Database::run('DELETE FROM login_attempts WHERE created_at < :d',
            ['d' => gmdate('Y-m-d H:i:s', time() - $days * 86400)]);
    }
}
