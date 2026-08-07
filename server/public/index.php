<?php
/**
 * ClinicFlow API front controller — the only web-reachable PHP entry point.
 * It boots the kernel and nothing else. No API logic lives here.
 *
 * On Hostinger this file is copied to public_html/api/index.php with
 * CLINICFLOW_BASE pointing at the non-public app directory.
 */
declare(strict_types=1);

define('CLINICFLOW_BASE', dirname(__DIR__));
require CLINICFLOW_BASE . '/bootstrap.php';

(new App\Core\Kernel())->handle()->send();
