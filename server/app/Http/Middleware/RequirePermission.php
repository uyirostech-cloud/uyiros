<?php
namespace App\Http\Middleware;

use App\Core\Ctx;
use App\Core\HttpException;

final class RequirePermission
{
    public static function check(Ctx $ctx, ?string $permission): void
    {
        if ($permission === null) {
            return;                       // route is public or authOnly — already validated by the router
        }
        if (!$ctx->can($permission)) {
            throw HttpException::forbidden('You do not have permission to perform this action');
        }
    }
}
