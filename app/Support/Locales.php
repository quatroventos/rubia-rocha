<?php

namespace App\Support;

class Locales
{
    public static function default(): string
    {
        return (string) config('locales.default', 'pt-BR');
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys((array) config('locales.supported', []));
    }

    /**
     * @return list<string>
     */
    public static function translated(): array
    {
        return array_values(array_diff(self::all(), [self::default()]));
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::all(), true);
    }

    public static function isDefault(?string $locale): bool
    {
        return $locale === null || $locale === self::default();
    }

    public static function normalize(?string $locale): string
    {
        return self::isSupported($locale) ? (string) $locale : self::default();
    }

    /**
     * @return array<string, mixed>
     */
    public static function meta(string $locale): array
    {
        return (array) config('locales.supported.'.self::normalize($locale), []);
    }

    public static function prefix(string $locale): string
    {
        return (string) (self::meta($locale)['prefix'] ?? '');
    }

    public static function hreflang(string $locale): string
    {
        return (string) (self::meta($locale)['hreflang'] ?? $locale);
    }

    public static function ogLocale(string $locale): string
    {
        return (string) (self::meta($locale)['og_locale'] ?? Seo::DEFAULT_LOCALE);
    }

    public static function aiName(string $locale): string
    {
        return (string) (self::meta($locale)['ai_name'] ?? $locale);
    }

    public static function routePattern(): string
    {
        return implode('|', array_map(
            fn (string $locale) => preg_quote(self::prefix($locale), '/'),
            self::translated()
        ));
    }

    public static function fromPrefix(?string $prefix): string
    {
        foreach (self::all() as $locale) {
            if (self::prefix($locale) === (string) $prefix) {
                return $locale;
            }
        }

        return self::default();
    }

    public static function path(string $locale, string $path): string
    {
        $path = '/'.ltrim($path, '/');
        $prefix = self::prefix($locale);

        if ($prefix === '') {
            return $path;
        }

        return $path === '/' ? '/'.$prefix : '/'.$prefix.$path;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function splitPath(string $path): array
    {
        $trimmed = trim($path, '/');
        $segments = explode('/', $trimmed, 2);

        foreach (self::translated() as $locale) {
            if ($segments[0] === self::prefix($locale)) {
                return [$locale, '/'.($segments[1] ?? '')];
            }
        }

        return [self::default(), '/'.$trimmed];
    }

    public static function cookie(): string
    {
        return (string) config('locales.cookie', 'rr_locale');
    }

    public static function current(): string
    {
        return app()->bound('site.locale') ? (string) app('site.locale') : self::default();
    }

    public static function setCurrent(string $locale): void
    {
        app()->instance('site.locale', self::normalize($locale));
    }

    /**
     * @return array<string, array{code: string, name: string, short: string, hreflang: string}>
     */
    public static function publicList(): array
    {
        $list = [];
        foreach (self::all() as $locale) {
            $meta = self::meta($locale);
            $list[$locale] = [
                'code' => $locale,
                'name' => (string) ($meta['name'] ?? $locale),
                'short' => (string) ($meta['short'] ?? strtoupper($locale)),
                'hreflang' => self::hreflang($locale),
            ];
        }

        return $list;
    }

    public static function fromAcceptLanguage(?string $header): ?string
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $ranked = [];
        foreach (explode(',', $header) as $index => $part) {
            $pieces = array_map('trim', explode(';', $part));
            $tag = strtolower($pieces[0] ?? '');
            if ($tag === '' || $tag === '*') {
                continue;
            }

            $quality = 1.0;
            foreach (array_slice($pieces, 1) as $param) {
                if (str_starts_with($param, 'q=')) {
                    $quality = (float) substr($param, 2);
                }
            }

            $ranked[] = ['tag' => $tag, 'q' => $quality, 'i' => $index];
        }

        usort($ranked, fn (array $a, array $b) => [$b['q'], $a['i']] <=> [$a['q'], $b['i']]);

        foreach ($ranked as $entry) {
            if ($entry['q'] <= 0) {
                continue;
            }

            $primary = explode('-', $entry['tag'])[0];
            foreach (self::all() as $locale) {
                if (strtolower($locale) === $entry['tag'] || strtolower(explode('-', $locale)[0]) === $primary) {
                    return $locale;
                }
            }
        }

        return null;
    }
}
