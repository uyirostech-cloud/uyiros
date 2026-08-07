<?php
/**
 * ClinicFlow bootstrap.
 * CLINICFLOW_BASE must be defined by the entry point (public/index.php, CLI script or test runner).
 */
declare(strict_types=1);

if (!defined('CLINICFLOW_BASE')) {
    define('CLINICFLOW_BASE', __DIR__);
}

require_once CLINICFLOW_BASE . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Env;
use App\Core\Logger;

Autoloader::register(CLINICFLOW_BASE . '/app');

if (!Env::isLoaded()) {
    Env::load(CLINICFLOW_BASE . '/.env');
}
Config::loadDir(CLINICFLOW_BASE . '/config');

mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');

$debug = (bool) Config::get('app.debug', false);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        Logger::write('critical', $err['message'], ['file' => $err['file'] . ':' . $err['line']]);
    }
});
