<?php
namespace App\Core;

final class Route
{
    public string $regex;
    public array $paramNames = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $handler,
        public readonly ?string $permission,
        public readonly bool $public = false,
        public readonly bool $authOnly = false,
        public readonly bool $platform = false,
    ) {
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($m) {
            $this->paramNames[] = $m[1];
            return '([^/]+)';
        }, $path);
        $this->regex = '#^' . $regex . '$#';
    }
}
