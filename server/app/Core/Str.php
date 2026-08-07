<?php
namespace App\Core;

final class Str
{
    public static function ulid(): string
    {
        $time = (int) (microtime(true) * 1000);
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $out = '';
        for ($i = 9; $i >= 0; $i--) {
            $out = $alphabet[$time % 32] . $out;
            $time = intdiv($time, 32);
        }
        for ($i = 0; $i < 16; $i++) {
            $out .= $alphabet[random_int(0, 31)];
        }
        return $out;
    }

    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    /**
     * Normalize a phone number for duplicate detection.
     * Keeps digits only, drops a leading country code / trunk zero, keeps the last 10 digits.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }
        return $digits;
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function today(): string
    {
        return gmdate('Y-m-d');
    }
}
