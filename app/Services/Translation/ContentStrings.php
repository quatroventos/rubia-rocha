<?php

namespace App\Services\Translation;

class ContentStrings
{
    /**
     * Chaves cujo valor nunca é texto para leitura (links, mídia, enums, ids).
     *
     * @var list<string>
     */
    private const SKIP_KEYS = [
        'id', 'type', 'layout', 'image', 'src', 'icon', 'variant', 'theme', 'tone', 'filter',
        'href', 'url', 'path', 'slug', 'anchor', 'kind', 'value', 'cover_path', 'services', 'stack',
        'whatsapp', 'email', 'phone', 'year', 'sector', 'featured', 'sort', 'glyph', 'visual', 'logo_place',
        'panel_id', 'monthly', 'annual',
    ];

    /**
     * Tipos de seção cujos itens são referências (slugs), não texto.
     *
     * @var list<string>
     */
    private const REFERENCE_SECTIONS = ['site.cases', 'site.showcase', 'site.case-grid'];

    /**
     * @param  array<int|string, mixed>  $data
     * @return array<string, string> caminho => texto
     */
    public static function extract(array $data, string $prefix = ''): array
    {
        $found = [];
        self::walk($data, $prefix, $found, null);

        return $found;
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @param  array<string, string>  $translated  caminho => texto
     * @return array<int|string, mixed>
     */
    public static function apply(array $data, array $translated, string $prefix = ''): array
    {
        foreach ($translated as $path => $text) {
            if ($prefix !== '') {
                if (! str_starts_with($path, $prefix.'.')) {
                    continue;
                }
                $path = substr($path, strlen($prefix) + 1);
            }

            $keys = explode('.', $path);
            $ref = &$data;
            foreach ($keys as $index => $key) {
                if (! is_array($ref) || ! array_key_exists($key, $ref)) {
                    unset($ref);

                    continue 2;
                }

                if ($index === count($keys) - 1) {
                    if (is_string($ref[$key])) {
                        $ref[$key] = $text;
                    }
                } else {
                    $ref = &$ref[$key];
                }
            }
            unset($ref);
        }

        return $data;
    }

    public static function isText(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/\p{L}/u', $value)) {
            return false;
        }

        if (preg_match('#^(/|\#|https?://|mailto:|tel:|www\.)#i', $value)) {
            return false;
        }

        if (preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)+$/', $value)) {
            return false;
        }

        return ! filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @param  array<string, string>  $found
     */
    private static function walk(array $data, string $prefix, array &$found, ?string $sectionType): void
    {
        if (is_string($data['type'] ?? null) && array_key_exists('data', $data)) {
            $sectionType = $data['type'];
        }

        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_string($key) && self::skipKey($key)) {
                continue;
            }

            if ($key === 'items' && in_array($sectionType, self::REFERENCE_SECTIONS, true)) {
                continue;
            }

            if (is_array($value)) {
                self::walk($value, $path, $found, $sectionType);

                continue;
            }

            if (is_string($value) && self::isText($value)) {
                $found[$path] = $value;
            }
        }
    }

    private static function skipKey(string $key): bool
    {
        return in_array($key, self::SKIP_KEYS, true)
            || str_ends_with($key, '_href')
            || str_ends_with($key, '_url')
            || str_ends_with($key, '_path');
    }
}
