<?php

// Idiomas do site público. O padrão fica na raiz, os demais em /{prefixo}.

return [
    'default' => 'pt-BR',

    'cookie' => 'qv_locale',

    'supported' => [
        'pt-BR' => [
            'prefix' => '',
            'hreflang' => 'pt-BR',
            'og_locale' => 'pt_BR',
            'name' => 'Português',
            'short' => 'PT',
            'ai_name' => 'Brazilian Portuguese',
        ],
        'en' => [
            'prefix' => 'en',
            'hreflang' => 'en',
            'og_locale' => 'en_US',
            'name' => 'English',
            'short' => 'EN',
            'ai_name' => 'English (international, neutral)',
        ],
        'es' => [
            'prefix' => 'es',
            'hreflang' => 'es',
            'og_locale' => 'es_LA',
            'name' => 'Español',
            'short' => 'ES',
            'ai_name' => 'neutral Latin American Spanish',
        ],
    ],
];
