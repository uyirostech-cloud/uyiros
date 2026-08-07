<?php
use App\Core\Env;

return [
    'cookie_name'      => Env::get('SESSION_COOKIE_NAME', 'clinicflow_session'),
    'csrf_cookie_name' => 'clinicflow_csrf',
    'secure'           => Env::bool('SESSION_COOKIE_SECURE', false),
    'samesite'         => Env::get('SESSION_COOKIE_SAMESITE', 'Lax'),
    'absolute_minutes' => Env::int('SESSION_ABSOLUTE_MINUTES', 720),
    'idle_minutes'     => Env::int('SESSION_IDLE_MINUTES', 120),
    'reset_ttl_minutes'=> 60,
];
