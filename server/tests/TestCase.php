<?php
namespace Tests;

use App\Core\Database;
use App\Core\Kernel;
use App\Core\Request;
use App\Core\Response;

/**
 * Dispatches synthetic requests through the REAL kernel — the same middleware chain that
 * serves production traffic. Authentication, CSRF, tenancy and permissions are therefore
 * genuinely exercised, not stubbed.
 */
abstract class TestCase
{
    protected static ?Kernel $kernel = null;

    /** @var array<string,array{cookies:array,csrf:string}> logged-in sessions by label */
    protected static array $sessions = [];

    public static function kernel(): Kernel
    {
        return self::$kernel ??= new Kernel();
    }

    /** @return array{status:int,body:array,response:Response} */
    protected function request(
        string $method,
        string $path,
        array $body = [],
        array $query = [],
        ?string $as = null,
        bool $withCsrf = true
    ): array {
        $cookies = [];
        $headers = ['content-type' => 'application/json'];
        if ($as !== null) {
            $session = self::$sessions[$as] ?? throw new \RuntimeException("No session for '{$as}'");
            $cookies = $session['cookies'];
            if ($withCsrf) {
                $headers['x-csrf-token'] = $session['csrf'];
            }
        }
        $request = Request::create($method, $path, $body, $query, $headers, $cookies);
        $response = self::kernel()->handle($request);

        return [
            'status'   => $response->status,
            'body'     => is_array($response->body) ? $response->body : [],
            'response' => $response,
        ];
    }

    /** Log in and remember the session under a label. */
    protected function login(string $label, string $email, string $password = 'ClinicFlow#2026'): array
    {
        $request = Request::create('POST', '/api/auth/login',
            ['email' => $email, 'password' => $password], [], ['content-type' => 'application/json']);
        $response = self::kernel()->handle($request);

        if ($response->status !== 200) {
            throw new \RuntimeException(
                "Login failed for {$email}: " . json_encode($response->body)
            );
        }
        $cookies = [];
        foreach ($response->cookies as $c) {
            $cookies[$c['name']] = $c['value'];
        }
        self::$sessions[$label] = [
            'cookies' => $cookies,
            'csrf'    => $response->body['data']['csrf_token'],
            'user'    => $response->body['data']['user'],
        ];
        return self::$sessions[$label];
    }

    protected function sessionUser(string $label): array
    {
        return self::$sessions[$label]['user'];
    }

    protected function db(): string
    {
        return (string) \App\Core\Config::get('database.database');
    }

    protected function scalar(string $sql, array $params = []): mixed
    {
        return Database::scalar($sql, $params);
    }

    /** @return array<string,callable> test name => test */
    abstract public function tests(): array;

    public function name(): string
    {
        $parts = explode('\\', static::class);
        return end($parts);
    }
}
