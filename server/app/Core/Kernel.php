<?php
namespace App\Core;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CsrfGuard;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;

/**
 * The single request pipeline. Every protected endpoint goes through it — there is no
 * alternative entry point, so authorization cannot be forgotten or implemented differently
 * per endpoint.
 *
 *   Request → SecurityHeaders → (preflight) → Route → Authenticate → ResolveTenant
 *           → CsrfGuard → RequirePermission → Controller → Response
 */
final class Kernel
{
    private Router $router;

    public function __construct()
    {
        $this->router = require CLINICFLOW_BASE . '/routes/api.php';
    }

    public function handle(?Request $request = null): Response
    {
        $request = $request ?? Request::fromGlobals();
        $response = $this->dispatch($request);
        return SecurityHeaders::apply($request, $response);
    }

    private function dispatch(Request $request): Response
    {
        try {
            if ($request->method === 'OPTIONS') {
                return new Response(204);
            }

            $path = $request->path;
            // Strip the /api prefix so routes read cleanly whether mounted at / or /api.
            if (str_starts_with($path, '/api/') || $path === '/api') {
                $path = substr($path, 4) ?: '/';
            }

            $matched = $this->router->match($request->method, $path);
            if ($matched === null) {
                return Response::error(404, 'NOT_FOUND', 'Endpoint not found');
            }
            [$route, $params] = $matched;
            $request->routeParams = $params;

            if ($route->public) {
                return $this->invoke($route, $request, null);
            }

            $auth = Authenticate::resolve($request);
            CsrfGuard::check($request, (string) $auth['session']['csrf_token']);
            $ctx = ResolveTenant::build($auth, $request, $route->platform, $route->authOnly);
            RequirePermission::check($ctx, $route->permission);

            return $this->invoke($route, $request, $ctx);
        } catch (HttpException $e) {
            return Response::error($e->status, $e->errorCode, $e->getMessage(), $e->fields);
        } catch (\Throwable $e) {
            Logger::exception($e);
            if (Config::get('app.debug')) {
                return Response::error(500, 'SERVER_ERROR', $e->getMessage(), [
                    'exception' => get_class($e),
                    'file'      => $e->getFile() . ':' . $e->getLine(),
                    'trace'     => array_slice(explode("\n", $e->getTraceAsString()), 0, 12),
                ]);
            }
            return Response::error(500, 'SERVER_ERROR', 'An unexpected error occurred');
        }
    }

    private function invoke(Route $route, Request $request, ?Ctx $ctx): Response
    {
        [$class, $method] = $route->handler;
        $controller = new $class();
        $result = $ctx === null ? $controller->$method($request) : $controller->$method($request, $ctx);
        return $result instanceof Response ? $result : Response::json($result);
    }

    public function router(): Router
    {
        return $this->router;
    }
}
