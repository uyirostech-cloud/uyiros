<?php
namespace App\Http\Middleware;

use App\Core\HttpException;
use App\Core\Request;

final class CsrfGuard
{
    public static function check(Request $request, string $sessionCsrfToken): void
    {
        if (!$request->isMutating()) {
            return;
        }
        $supplied = $request->header('x-csrf-token');
        if (!is_string($supplied) || $supplied === '' || !hash_equals($sessionCsrfToken, $supplied)) {
            throw HttpException::csrf();
        }
    }
}
