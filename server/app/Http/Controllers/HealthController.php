<?php
namespace App\Http\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class HealthController
{
    public function index(Request $request): Response
    {
        $db = 'ok';
        $migrations = null;
        try {
            Database::scalar('SELECT 1');
            $migrations = (int) (Database::scalar('SELECT COUNT(*) FROM migrations') ?? 0);
        } catch (\Throwable) {
            $db = 'error';
        }
        return Response::json([
            'status'     => $db === 'ok' ? 'ok' : 'degraded',
            'app'        => Config::get('app.name'),
            'env'        => Config::get('app.env'),
            'db'         => $db,
            'migrations' => $migrations,
            'php'        => PHP_VERSION,
            'time'       => gmdate('c'),
        ], $db === 'ok' ? 200 : 503);
    }
}
