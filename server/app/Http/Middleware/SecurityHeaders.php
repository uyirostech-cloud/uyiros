<?php
namespace App\Http\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

final class SecurityHeaders
{
    public static function apply(Request $request, Response $response): Response
    {
        $response->withHeader('X-Content-Type-Options', 'nosniff')
                 ->withHeader('X-Frame-Options', 'DENY')
                 ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                 ->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()')
                 ->withHeader('Cache-Control', 'no-store, private');

        if (Config::get('app.env') === 'production') {
            $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // CORS: development only. Production is same-origin, so the allow-list is empty.
        $origin = $request->header('origin');
        $allowed = Config::get('cors.allowed_origins', []);
        if ($origin !== null && in_array($origin, $allowed, true)) {
            $response->withHeader('Access-Control-Allow-Origin', $origin)
                     ->withHeader('Access-Control-Allow-Credentials', 'true')
                     ->withHeader('Access-Control-Allow-Headers', 'Content-Type, X-CSRF-Token, X-Requested-With')
                     ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                     ->withHeader('Vary', 'Origin');
        }
        return $response;
    }
}
