<?php
namespace App\Core;

final class Router
{
    /** @var Route[] */
    private array $routes = [];

    /**
     * @param string|null $permission Required permission slug. Pass null ONLY together with
     *                                $public or $authOnly — the router refuses silent gaps.
     */
    private function add(
        string $method,
        string $path,
        array $handler,
        ?string $permission,
        bool $public = false,
        bool $authOnly = false,
        bool $platform = false
    ): void {
        if ($permission === null && !$public && !$authOnly && !$platform) {
            throw new \LogicException(
                "Route {$method} {$path} declares no permission and is not marked public/authOnly. " .
                'Every protected route must state what it requires.'
            );
        }
        $this->routes[] = new Route($method, '/' . trim($path, '/'), $handler, $permission, $public, $authOnly, $platform);
    }

    public function get(string $p, array $h, ?string $perm = null, bool $public = false, bool $authOnly = false): void
    {
        $this->add('GET', $p, $h, $perm, $public, $authOnly);
    }

    public function post(string $p, array $h, ?string $perm = null, bool $public = false, bool $authOnly = false): void
    {
        $this->add('POST', $p, $h, $perm, $public, $authOnly);
    }

    public function put(string $p, array $h, ?string $perm = null, bool $authOnly = false): void
    {
        $this->add('PUT', $p, $h, $perm, false, $authOnly);
    }

    public function patch(string $p, array $h, ?string $perm = null, bool $authOnly = false): void
    {
        $this->add('PATCH', $p, $h, $perm, false, $authOnly);
    }

    public function delete(string $p, array $h, ?string $perm = null, bool $authOnly = false): void
    {
        $this->add('DELETE', $p, $h, $perm, false, $authOnly);
    }

    /** Platform-admin-only route (Level 1). */
    public function platform(string $method, string $p, array $h, string $perm): void
    {
        $this->add(strtoupper($method), $p, $h, $perm, false, false, true);
    }

    /** @return array{0:Route,1:array<string,string>}|null */
    public function match(string $method, string $path): ?array
    {
        $path = '/' . trim($path, '/');
        $pathMatched = false;
        foreach ($this->routes as $route) {
            if (preg_match($route->regex, $path, $m)) {
                $pathMatched = true;
                if ($route->method === $method) {
                    array_shift($m);
                    return [$route, array_combine($route->paramNames, $m) ?: []];
                }
            }
        }
        if ($pathMatched) {
            throw new HttpException(405, 'METHOD_NOT_ALLOWED', 'Method not allowed for this endpoint');
        }
        return null;
    }

    /** @return Route[] */
    public function all(): array
    {
        return $this->routes;
    }
}
