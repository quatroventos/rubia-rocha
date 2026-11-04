<?php

namespace App\Cms;

class SectionCatalog
{
    /**
     * @return list<array{type: string, label: string, group: string, group_label: string, preview: string, fields: list<array<string, mixed>>}>
     */
    public static function all(): array
    {
        return array_values(self::map());
    }

    /**
     * @return array<string, array{type: string, label: string, group: string, group_label: string, preview: string, fields: list<array<string, mixed>>}>
     */
    public static function map(): array
    {
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        $map = [];
        foreach (SiteSectionDefinition::all() as $section) {
            $preview = (string) ($section['preview'] ?? 'section');
            $type = 'site.'.$preview;

            $map[$type] = [
                'type' => $type,
                'label' => (string) ($section['label'] ?? 'Seção'),
                'group' => 'site',
                'group_label' => 'Site',
                'preview' => $preview,
                'fields' => $section['fields'] ?? [],
            ];
        }

        return $map;
    }

    /**
     * @return array{type: string, label: string, group: string, group_label: string, preview: string, fields: list<array<string, mixed>>}|null
     */
    public static function find(string $type): ?array
    {
        return self::map()[$type] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_keys(self::map());
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array{id: string, type: string, label: string, preview: string, fields: list<array<string, mixed>>}>
     */
    public static function schemaForSections(array $sections): array
    {
        $schema = [];

        foreach ($sections as $section) {
            $type = is_string($section['type'] ?? null) ? $section['type'] : '';
            $definition = self::find($type);

            if ($definition === null) {
                continue;
            }

            $schema[] = [
                'id' => (string) ($section['id'] ?? ''),
                'type' => $type,
                'label' => $definition['label'],
                'preview' => $definition['preview'],
                'fields' => $definition['fields'],
            ];
        }

        return $schema;
    }

    /**
     * @return list<array{group: string, group_label: string, items: list<array{type: string, label: string, preview: string, origin: string, fields: list<array<string, mixed>>}>}>
     */
    public static function library(): array
    {
        return [[
            'group' => 'all',
            'group_label' => 'Seções',
            'items' => array_map(fn (array $item) => [
                'type' => $item['type'],
                'label' => $item['label'],
                'preview' => $item['preview'],
                'origin' => $item['group_label'],
                'fields' => $item['fields'],
            ], self::all()),
        ]];
    }

    /**
     * Caminhos (notação de ponto, "*" para itens de repetidor) dos campos de um tipo, por tipo de campo.
     *
     * @return list<string>
     */
    public static function fieldPaths(string $type, string $fieldType): array
    {
        $definition = self::find($type);

        return $definition === null ? [] : self::collectPaths($definition['fields'], $fieldType, '');
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<string>
     */
    private static function collectPaths(array $fields, string $fieldType, string $prefix): array
    {
        $paths = [];

        foreach ($fields as $field) {
            $name = $prefix.(string) ($field['name'] ?? '');
            $type = (string) ($field['type'] ?? 'text');

            if ($type === 'repeater') {
                if (($field['simple'] ?? false) && ($field['itemType'] ?? 'text') === $fieldType) {
                    $paths[] = $name.'.*';
                }

                array_push($paths, ...self::collectPaths($field['fields'] ?? [], $fieldType, $name.'.*.'));

                continue;
            }

            if ($type === $fieldType) {
                $paths[] = $name;
            }
        }

        return $paths;
    }
}
