<?php
use App\Core\Env;

// Development only. In production the SPA and API share an origin, so this stays empty.
$raw = trim((string) Env::get('CORS_ALLOWED_ORIGINS', ''));

return [
    'allowed_origins' => $raw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $raw)))),
];
