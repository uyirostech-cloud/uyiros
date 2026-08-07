<?php
namespace App\Core;

final class Response
{
    public function __construct(
        public int $status = 200,
        public mixed $body = null,
        public array $headers = [],
        /** @var array<int,array{name:string,value:string,options:array}> */
        public array $cookies = [],
    ) {
    }

    public static function json(mixed $data, int $status = 200, array $meta = []): self
    {
        $payload = ['data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }
        return new self($status, $payload, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function paginated(array $items, int $total, int $page, int $perPage, array $extra = []): self
    {
        return self::json($items, 200, array_merge([
            'page'      => $page,
            'per_page'  => $perPage,
            'total'     => $total,
            'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
        ], $extra));
    }

    public static function error(int $status, string $code, string $message, array $fields = []): self
    {
        $err = ['code' => $code, 'message' => $message];
        if ($fields !== []) {
            $err['fields'] = $fields;
        }
        return new self($status, ['error' => $err], ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function noContent(): self
    {
        return new self(204, null);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withCookie(string $name, string $value, array $options): self
    {
        $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options];
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
            foreach ($this->cookies as $c) {
                setcookie($c['name'], $c['value'], $c['options']);
            }
        }
        if ($this->status !== 204 && $this->body !== null) {
            echo is_string($this->body)
                ? $this->body
                : json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
