<?php
use App\Core\Env;

return [
    'name'         => Env::get('APP_NAME', 'ClinicFlow'),
    'env'          => Env::get('APP_ENV', 'local'),
    'debug'        => Env::bool('APP_DEBUG', false),
    'url'          => Env::get('APP_URL', 'http://localhost:5173'),
    'api_url'      => Env::get('API_URL', 'http://localhost:8000/api'),
    'key'          => Env::get('APP_KEY', ''),
    'storage_path' => Env::get('STORAGE_PATH', CLINICFLOW_BASE . '/storage'),
];
