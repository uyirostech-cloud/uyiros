<?php
namespace Tests;

final class AssertionFailed extends \RuntimeException
{
}

final class Assert
{
    public static int $count = 0;

    public static function true(bool $condition, string $message = 'Expected true'): void
    {
        self::$count++;
        if (!$condition) {
            throw new AssertionFailed($message);
        }
    }

    public static function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::$count++;
        if ($expected !== $actual) {
            throw new AssertionFailed(
                ($message !== '' ? $message . ' — ' : '')
                . 'expected ' . self::stringify($expected) . ', got ' . self::stringify($actual)
            );
        }
    }

    public static function equals(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::$count++;
        if ((string) $expected !== (string) $actual) {
            throw new AssertionFailed(
                ($message !== '' ? $message . ' — ' : '')
                . 'expected ' . self::stringify($expected) . ', got ' . self::stringify($actual)
            );
        }
    }

    public static function status(int $expected, array $result, string $message = ''): void
    {
        self::$count++;
        if ($result['status'] !== $expected) {
            $err = $result['body']['error'] ?? $result['body']['data'] ?? null;
            throw new AssertionFailed(
                ($message !== '' ? $message . ' — ' : '')
                . "expected HTTP {$expected}, got {$result['status']}"
                . ($err ? ' (' . json_encode($err) . ')' : '')
            );
        }
    }

    public static function count(int $expected, array $items, string $message = ''): void
    {
        self::$count++;
        if (count($items) !== $expected) {
            throw new AssertionFailed(
                ($message !== '' ? $message . ' — ' : '')
                . 'expected ' . $expected . ' items, got ' . count($items)
            );
        }
    }

    public static function contains(mixed $needle, array $haystack, string $message = ''): void
    {
        self::$count++;
        if (!in_array($needle, $haystack, true)) {
            throw new AssertionFailed(
                ($message !== '' ? $message . ' — ' : '') . self::stringify($needle) . ' not found'
            );
        }
    }

    public static function notContains(mixed $needle, array $haystack, string $message = ''): void
    {
        self::$count++;
        if (in_array($needle, $haystack, true)) {
            throw new AssertionFailed(
                ($message !== '' ? $message . ' — ' : '') . self::stringify($needle) . ' should not be present'
            );
        }
    }

    public static function fail(string $message): void
    {
        self::$count++;
        throw new AssertionFailed($message);
    }

    private static function stringify(mixed $v): string
    {
        return match (true) {
            is_bool($v)  => $v ? 'true' : 'false',
            is_null($v)  => 'null',
            is_array($v) => json_encode($v),
            default      => var_export($v, true),
        };
    }
}
