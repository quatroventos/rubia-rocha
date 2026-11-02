<?php

namespace App\Cms;

use Illuminate\Support\Str;

class PageContentMigrator
{
    /**
     * Normalize page content to { sections: [...] }. Legacy flat content is converted.
     *
     * @param  array<string, mixed>|null  $content
     * @return array{sections: list<array{id: string, type: string, data: array<string, mixed>}>}
     */
    public static function normalize(?array $content, ?string $template): array
    {
        $content = is_array($content) ? $content : [];

        if (isset($content['sections']) && is_array($content['sections'])) {
            return [
                'sections' => array_values(array_filter(
                    array_map(fn ($section) => self::normalizeSection($section), $content['sections']),
                    fn ($section) => $section !== null
                )),
            ];
        }

        return [
            'sections' => self::fromLegacy($content, $template),
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    public static function fromLegacy(array $content, ?string $template): array
    {
        $sections = [];

        foreach (SectionCatalog::all() as $definition) {
            if ($template !== null && $template !== '' && $definition['group'] !== $template) {
                continue;
            }

            $data = self::extractData($content, $definition);
            $sections[] = [
                'id' => (string) Str::ulid(),
                'type' => $definition['type'],
                'data' => $data,
            ];
        }

        return $sections;
    }

    /**
     * @param  array{type: string, keys: list<string>, wrap_root: bool}  $definition
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function extractData(array $content, array $definition): array
    {
        $keys = $definition['keys'];

        if ($keys === []) {
            return [];
        }

        if (count($keys) === 1 && ! ($definition['wrap_root'] ?? false)) {
            $value = $content[$keys[0]] ?? [];

            return is_array($value) ? $value : [];
        }

        $data = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $content)) {
                $data[$key] = $content[$key];
            }
        }

        return $data;
    }

    /**
     * Expand section data back to the legacy flat $c shape expected by Blade partials.
     *
     * @param  array{type?: string, data?: array<string, mixed>}  $section
     * @return array<string, mixed>
     */
    public static function toViewContent(array $section): array
    {
        $type = (string) ($section['type'] ?? '');
        $data = is_array($section['data'] ?? null) ? $section['data'] : [];
        $definition = SectionCatalog::find($type);

        if ($definition === null) {
            return $data;
        }

        $keys = $definition['keys'];

        if ($keys === []) {
            return $data;
        }

        if (count($keys) === 1 && ! ($definition['wrap_root'] ?? false)) {
            return [$keys[0] => $data];
        }

        return $data;
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
