<?php
namespace App\Core;

/** Reads a .env file into an internal map. Never writes to $_ENV/getenv output. */
final class Env
{
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        self::$loaded = true;
        if (!is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            // strip inline comment when unquoted
            if ($val !== '' && $val[0] !== '"' && $val[0] !== "'") {
                $hash = strpos($val, ' #');
                if ($hash !== false) {
                    $val = rtrim(substr($val, 0, $hash));
                }
            }
            if (strlen($val) >= 2
                && (($val[0] === '"' && str_ends_with($val, '"')) || ($val[0] === "'" && str_ends_with($val, "'")))) {
                $val = substr($val, 1, -1);
            }
            self::$vars[$key] = $val;
        }
    }

    public static function set(string $key, string $value): void
    {
        self::$vars[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$vars)) {
            return self::$vars[$key];
        }
        $fromServer = $_SERVER[$key] ?? getenv($key);
        return ($fromServer === false || $fromServer === null) ? $default : $fromServer;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return ($v === null || $v === '') ? $default : (int) $v;
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }
}
