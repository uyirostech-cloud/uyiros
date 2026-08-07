<?php
namespace App\Core;

final class Request
{
    public array $routeParams = [];

    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $headers,
        public readonly array $cookies,
        public readonly array $files,
        public readonly string $ip,
        public readonly string $userAgent,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = $_SERVER['REQUEST_URI'] ?? '/';
        $path   = parse_url($uri, PHP_URL_PATH) ?: '/';

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        $body = [];
        $raw = file_get_contents('php://input') ?: '';
        $ct = $headers['content-type'] ?? '';
        if (str_contains($ct, 'application/json') && $raw !== '') {
            $decoded = json_decode($raw, true);
            $body = is_array($decoded) ? $decoded : [];
        } elseif ($_POST !== []) {
            $body = $_POST;
        }

        return new self(
            $method,
            '/' . trim($path, '/'),
            $_GET,
            $body,
            $headers,
            $_COOKIE,
            $_FILES,
            self::clientIp(),
            substr((string) ($headers['user-agent'] ?? ''), 0, 255),
        );
    }

    /** Used by the test runner to dispatch synthetic requests through the real pipeline. */
    public static function create(
        string $method,
        string $path,
        array $body = [],
        array $query = [],
        array $headers = [],
        array $cookies = []
    ): self {
        return new self(
            strtoupper($method),
            '/' . trim(parse_url($path, PHP_URL_PATH) ?: '/', '/'),
            $query,
            $body,
            array_change_key_case($headers),
            $cookies,
            [],
            '127.0.0.1',
            'clinicflow-tests',
        );
    }

    private static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        return $this->cookies[$name] ?? $default;
    }

    public function param(string $name, mixed $default = null): mixed
    {
        return $this->routeParams[$name] ?? $default;
    }

    public function intParam(string $name): int
    {
        return (int) ($this->routeParams[$name] ?? 0);
    }

    public function query(string $name, mixed $default = null): mixed
    {
        return $this->query[$name] ?? $default;
    }

    public function input(string $name, mixed $default = null): mixed
    {
        return $this->body[$name] ?? $default;
    }

    public function isMutating(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * Mass-assignment guard: only the listed keys survive.
     * organization_id, id, created_by and privilege flags can never be smuggled in.
     */
    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->body)) {
                $out[$key] = $this->body[$key];
            }
        }
        unset(
            $out['organization_id'], $out['id'], $out['created_by'], $out['updated_by'],
            $out['is_platform_admin'], $out['password_hash'], $out['created_at'], $out['updated_at']
        );
        return $out;
    }

    /** Pagination with a hard ceiling so no request can pull an unbounded dataset. */
    public function pagination(int $default = 25, int $max = 100): array
    {
        $page = max(1, (int) ($this->query['page'] ?? 1));
        $perPage = (int) ($this->query['per_page'] ?? $default);
        $perPage = max(1, min($max, $perPage ?: $default));
        return [$page, $perPage, ($page - 1) * $perPage];
    }
}
