<?php
namespace App\Core;

final class Config
{
    private static array $items = [];

    public static function loadDir(string $dir): void
    {
        foreach (glob(rtrim($dir, '/') . '/*.php') ?: [] as $file) {
            self::$items[basename($file, '.php')] = require $file;
        }
    }

    /** Dot notation: Config::get('database.host') */
    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $node = self::$items;
        foreach ($parts as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) {
                return $default;
            }
            $node = $node[$part];
        }
        return $node;
    }

    public static function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $node = &self::$items;
        foreach ($parts as $part) {
            if (!isset($node[$part]) || !is_array($node[$part])) {
                $node[$part] = [];
            }
            $node = &$node[$part];
        }
        $node = $value;
    }
}
