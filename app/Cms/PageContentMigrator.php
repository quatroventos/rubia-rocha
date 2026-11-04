<?php

namespace App\Cms;

use Illuminate\Support\Str;

class PageContentMigrator
{
    /**
     * Normaliza o conteúdo para { sections: [...] }, descartando seções de tipos que não existem no catálogo.
     *
     * @param  array<string, mixed>|null  $content
     * @return array{sections: list<array{id: string, type: string, data: array<string, mixed>}>}
     */
    public static function normalize(?array $content, ?string $template = null): array
    {
        $sections = is_array($content) && is_array($content['sections'] ?? null) ? $content['sections'] : [];

        return [
            'sections' => array_values(array_filter(
                array_map(fn ($section) => self::normalizeSection($section), $sections),
                fn ($section) => $section !== null
            )),
        ];
    }

    /**
     * @return array{id: string, type: string, data: array<string, mixed>}|null
     */
    private static function normalizeSection(mixed $section): ?array
    {
        if (! is_array($section)) {
            return null;
        }

        $type = is_string($section['type'] ?? null) ? $section['type'] : '';
        if ($type === '' || SectionCatalog::find($type) === null) {
            return null;
        }

        return [
            'id' => is_string($section['id'] ?? null) && $section['id'] !== ''
                ? $section['id']
                : (string) Str::ulid(),
            'type' => $type,
            'data' => is_array($section['data'] ?? null) ? $section['data'] : [],
        ];
    }
}
