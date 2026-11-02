<?php

use App\Services\ImageOptimizationService;
use App\Support\MediaUrl;

if (! function_exists('media')) {
    function media(?string $path): string
    {
        if (! $path) {
            return '';
        }

        $optimized = app(ImageOptimizationService::class)->publicUrlFor($path);
        if ($optimized) {
            return $optimized;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $basename = basename($path);
        if ($basename !== '' && is_file(public_path('assets/'.$basename))) {
            return asset('assets/'.$basename);
        }

        return MediaUrl::make($path) ?: '';
    }
}

if (! function_exists('rich')) {
    function rich(?string $value, bool $inline = false, ?string $class = null): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $allowed = $inline
            ? '<strong><b><em><i><u><a><br><span><s><strike><del><mark><code>'
            : '<p><br><strong><b><em><i><u><a><ul><ol><li><span><div><h1><h2><h3><h4><blockquote><s><strike><del><mark><code>';

        $colors = [];
        $tokenized = preg_replace_callback(
            '/<span\b([^>]*)>(.*?)<\/span>/is',
            static function (array $match) use (&$colors): string {
                $attrs = $match[1];
                $inner = $match[2];
                $color = null;
                $class = null;

                if (preg_match('/style\s*=\s*(["\'])(.*?)\1/is', $attrs, $styleMatch)
                    && preg_match('/(?:^|;)\s*color\s*:\s*([^;]+)/i', $styleMatch[2], $colorMatch)) {
                    $candidate = strtolower(trim($colorMatch[1]));
                    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $candidate)) {
                        $color = $candidate;
                    }
                }

                if (preg_match('/class\s*=\s*(["\'])(.*?)\1/is', $attrs, $classMatch)) {
                    $allowedClasses = array_values(array_filter(
                        preg_split('/\s+/', trim($classMatch[2])) ?: [],
                        static fn (string $item): bool => (bool) preg_match('/^(hero-title__nowrap|final-cta__nowrap|intro-card__eyebrow)$/', $item)
                    ));
                    if ($allowedClasses !== []) {
                        $class = implode(' ', $allowedClasses);
                    }
                }

                if ($color === null && $class === null) {
                    return $inner;
                }

                $token = '§RICHCOLOR'.count($colors).'§';
                $colors[$token] = ['color' => $color, 'class' => $class, 'inner' => $inner];

                return $token;
            },
            $value
        ) ?? $value;

        $html = strip_tags($tokenized, $allowed);
        $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace_callback('/<a\s+([^>]*?)>/i', function (array $match): string {
            $attrs = $match[1];
            if (preg_match('/href\s*=\s*([\'"])(.*?)\1/i', $attrs, $href) && preg_match('/^\s*(javascript|data|vbscript):/i', $href[2])) {
                return '<a>';
            }

            return $match[0];
        }, $html) ?? $html;

        if ($inline) {
            $html = preg_replace('#</p>\s*<p[^>]*>#i', '<br>', $html) ?? $html;
            $html = preg_replace('#</?p[^>]*>#i', '', $html) ?? $html;
            $html = preg_replace('#</?div[^>]*>#i', '', $html) ?? $html;
            $html = strip_tags($html, '<strong><b><em><i><u><a><br><span><s><strike><del><mark><code>');
        } else {
            $html = preg_replace('#<div>(.*?)</div>#is', '<p>$1</p>', $html) ?? $html;
        }

        foreach ($colors as $token => $payload) {
            $inner = strip_tags(
                (string) $payload['inner'],
                '<strong><b><em><i><u><a><br><s><strike><del><mark><code>'
            );
            $attrs = [];
            if (! empty($payload['class'])) {
                $attrs[] = 'class="'.$payload['class'].'"';
            }
            if (! empty($payload['color'])) {
                $attrs[] = 'style="color: '.$payload['color'].'"';
            }
            $replacement = $attrs === []
                ? $inner
                : '<span '.implode(' ', $attrs).'>'.$inner.'</span>';
            $html = str_replace($token, $replacement, $html);
        }

        if ($inline) {
            return $html;
        }

        if (! preg_match('/<(p|ul|ol|h[1-4]|blockquote)\b/i', $html)) {
            $attr = $class ? ' class="'.e($class).'"' : '';

            return '<p'.$attr.'>'.$html.'</p>';
        }

        if ($class) {
            if (preg_match('/<p\b/i', $html)) {
                $html = preg_replace('/<p\b([^>]*)>/i', '<p class="'.e($class).'"$1>', $html, 1) ?? $html;
            } else {
                $html = '<div class="'.e($class).'">'.$html.'</div>';
            }
        }

        return $html;
    }
}

if (! function_exists('menu_href_is_external')) {
    function menu_href_is_external(?string $href): bool
    {
        $value = trim((string) $href);
        if ($value === '') {
            return false;
        }

        return (bool) preg_match('#^(https?:)?//#i', $value)
            || (bool) preg_match('#^(mailto|tel):#i', $value);
    }
}

if (! function_exists('menu_url')) {
    /**
     * Resolve a menu href to a navigable URL.
     * Absolute/external schemes stay intact; site paths go through url().
     */
    function menu_url(?string $href): string
    {
        $value = trim((string) $href);
        if ($value === '' || $value === '#') {
            return '#';
        }

        if (menu_href_is_external($value)) {
            return $value;
        }

        if ($value === '/') {
            return url('/');
        }

        return url('/'.ltrim($value, '/'));
    }
}

if (! function_exists('cms_admin_path')) {
    /**
     * Slug do CMS. Lido na requisição, depois do .env, para funcionar no cPanel
     * sem o processo já ter a variável no ambiente.
     */
    function cms_admin_path(): string
    {
        $path = config('cms.admin_path');

        if (! is_string($path)) {
            return 'gestao';
        }

        $path = trim($path, '/');

        return $path !== '' ? $path : 'gestao';
    }
}

if (! function_exists('consent_tags')) {
    /**
     * Tags personalizadas do CMS. Meta e link (verificação de buscadores) saem direto; o resto
     * fica num <template data-consent> que o behaviors.js ativa só depois do aceite de cookies.
     */
    function consent_tags(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        preg_match_all('#<(?:meta|link)\b[^>]*>#i', $html, $matches);
        $direct = implode("\n", $matches[0]);
        $gated = trim((string) preg_replace('#<(?:meta|link)\b[^>]*>#i', '', $html));

        return $direct.($gated !== '' ? "\n<template data-consent=\"analytics\">".$gated.'</template>' : '');
    }
}
