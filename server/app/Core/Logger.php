<?php
namespace App\Core;

final class Logger
{
    public static function write(string $level, string $message, array $context = []): void
    {
        $dir = rtrim((string) Config::get('app.storage_path', CLINICFLOW_BASE . '/storage'), '/') . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        $line = sprintf(
            "[%s] %s: %s %s\n",
            gmdate('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : json_encode(self::redact($context), JSON_UNESCAPED_SLASHES)
        );
        @file_put_contents($dir . '/app-' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function exception(\Throwable $e): void
    {
        self::write('error', get_class($e) . ': ' . $e->getMessage(), [
            'file'  => $e->getFile() . ':' . $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10),
        ]);
    }

    /** Never let a secret reach the log. */
    public static function redact(array $data): array
    {
        $secret = ['password', 'password_hash', 'password_confirmation', 'token', 'token_hash',
                   'csrf_token', 'secret', 'api_key', 'authorization'];
        foreach ($data as $k => $v) {
            if (is_string($k) && in_array(strtolower($k), $secret, true)) {
                $data[$k] = '[redacted]';
            } elseif (is_array($v)) {
                $data[$k] = self::redact($v);
            }
        }
        return $data;
    }
}
