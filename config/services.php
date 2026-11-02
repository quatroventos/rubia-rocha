<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4.1'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 120),
        'queue_connection' => env('OPENAI_QUEUE_CONNECTION'),
        'prompts' => [
            'writer' => null,
            'seo_title' => null,
            'seo_description' => null,
            'keyword' => null,
        ],
    ],

    'openseo' => [
        'key' => env('OPENSEO_API_KEY'),
        'url' => env('OPENSEO_MCP_URL', 'https://app.openseo.so/mcp'),
        'host_header' => env('OPENSEO_HOST_HEADER'),
        'project' => env('OPENSEO_PROJECT_ID'),
        'location_code' => env('OPENSEO_LOCATION_CODE') ? (int) env('OPENSEO_LOCATION_CODE') : null,
        'language_code' => env('OPENSEO_LANGUAGE_CODE'),
        'site_url' => trim((string) env('OPENSEO_SITE_URL', env('APP_URL', '')), '/'),
        'audit_url' => trim((string) env('OPENSEO_AUDIT_URL', ''), '/'),
        'timeout' => (int) env('OPENSEO_TIMEOUT', 90),
        'paid' => (bool) env('OPENSEO_PAID_ENABLED', false),
    ],

    'meta' => [
        'key' => env('META_API_KEY'),
        'base_url' => env('META_BASE_URL', 'https://api.meta.ai/v1'),
        'image_model' => env('META_IMAGE_MODEL', 'muse-image-1.0'),
        'image_timeout' => (int) env('META_IMAGE_TIMEOUT', 180),
    ],

    'mockup' => [
        'chrome_binary' => env('MOCKUP_CHROME_PATH'),
    ],

    'runway' => [
        'key' => env('RUNWAY_API_KEY'),
    ],

];
