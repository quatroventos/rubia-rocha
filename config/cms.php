<?php

return [
    'admin_path' => trim((string) env('ADMIN_PATH', 'gestao'), '/'),
    'lighthouse_url' => trim((string) env('LIGHTHOUSE_URL', env('APP_URL', '')), '/'),
    'pagespeed_key' => (string) env('PAGESPEED_API_KEY', ''),
    'capture_url' => trim((string) env('OG_CAPTURE_URL', 'http://127.0.0.1:8000'), '/'),
    'capture_vite_url' => trim((string) env('OG_CAPTURE_VITE_URL', 'http://host.docker.internal:5173'), '/'),
    'og_domain' => trim((string) env('OG_DOMAIN', ''), '/'),
    'og_on_save' => (bool) env('CMS_OG_ON_SAVE', true),
    'options_ttl' => (int) env('CMS_OPTIONS_TTL', 3600),
    'prerender_on_save' => (bool) env('CMS_PRERENDER_ON_SAVE', true),
    'bootstrap_admin' => [
        'name' => env('ADMIN_NAME', 'Administrador'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
    'backup' => [
        'hour' => (int) env('BACKUP_HOUR', 3),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 7),
    ],
];
