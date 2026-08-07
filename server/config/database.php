<?php
use App\Core\Env;

return [
    'host'     => Env::get('DB_HOST', '127.0.0.1'),
    'port'     => Env::int('DB_PORT', 3306),
    'database' => Env::get('DB_NAME', 'clinicflow'),
    'username' => Env::get('DB_USER', 'root'),
    'password' => Env::get('DB_PASSWORD', ''),
    'charset'  => 'utf8mb4',
    'collation'=> 'utf8mb4_unicode_ci',
];
