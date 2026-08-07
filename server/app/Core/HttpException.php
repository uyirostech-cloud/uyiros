<?php
namespace App\Core;

class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $fields = []
    ) {
        parent::__construct($message);
    }

    public static function badRequest(string $m = 'Bad request', string $code = 'BAD_REQUEST'): self
    {
        return new self(400, $code, $m);
    }

    public static function unauthenticated(string $m = 'Authentication required'): self
    {
        return new self(401, 'UNAUTHENTICATED', $m);
    }

    public static function forbidden(string $m = 'You are not allowed to perform this action'): self
    {
        return new self(403, 'FORBIDDEN', $m);
    }

    public static function notFound(string $m = 'Resource not found'): self
    {
        return new self(404, 'NOT_FOUND', $m);
    }

    public static function conflict(string $m, string $code = 'CONFLICT', array $fields = []): self
    {
        return new self(409, $code, $m, $fields);
    }

    public static function csrf(string $m = 'Invalid or expired security token'): self
    {
        return new self(419, 'CSRF_TOKEN_MISMATCH', $m);
    }

    public static function validation(array $fields, string $m = 'The submitted data is invalid'): self
    {
        return new self(422, 'VALIDATION_FAILED', $m, $fields);
    }

    public static function tooManyRequests(string $m = 'Too many requests'): self
    {
        return new self(429, 'TOO_MANY_REQUESTS', $m);
    }
}
