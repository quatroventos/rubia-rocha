<?php

namespace App\Cms;

class SectionCatalog
{
    /**
     * @return list<array{type: string, label: string, group: string, group_label: string, preview: string, css: string, js: string|null, dependencies: list<string>, fields: list<array<string, mixed>>, keys: list<string>, wrap_root: bool}>
     */
    public static function all(): array
    {
        return array_values(self::map());
    }

    /**
     * @return array<string, array{type: string, label: string, group: string, group_label: string, preview: string, css: string, js: string|null, dependencies: list<string>, fields: list<array<string, mixed>>, keys: list<string>, wrap_root: bool}>
     */
    public static function map(): array
    {
        static $map = null;

        if ($map !== null) {
            return $map;
        }

        $map = [];
        foreach (self::definitions() as $item) {
            $map[$item['type']] = $item;
        }

        return $map;
    }

    /**
     * @return array{type: string, label: string, group: string, group_label: string, preview: string, css: string, js: string|null, dependencies: list<string>, fields: list<array<string, mixed>>, keys: list<string>, wrap_root: bool}|null
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
        $seen = [];
        $items = [];

        foreach (self::all() as $item) {
            if ($item['group'] !== 'site') {
                continue;
            }

            $preview = $item['preview'];
            if ($preview === '' || isset($seen[$preview])) {
                continue;
            }

            $seen[$preview] = true;
            $items[] = [
                'type' => $item['type'],
                'label' => $item['label'],
                'preview' => $preview,
                'origin' => $item['group_label'],
                'fields' => $item['fields'],
            ];
        }

        return [[
            'group' => 'all',
            'group_label' => 'Seções',
            'items' => $items,
        ]];
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<string>
     */
    public static function cssBundles(array $sections): array
    {
        $bundles = [];

        foreach ($sections as $section) {
            $definition = self::find((string) ($section['type'] ?? ''));
            if ($definition === null) {
                continue;
            }
            $bundles[$definition['css']] = true;
        }

        return array_keys($bundles);
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<string>
     */
    public static function jsBundles(array $sections): array
    {
        $bundles = [];

        foreach ($sections as $section) {
            $definition = self::find((string) ($section['type'] ?? ''));
            if ($definition === null || $definition['js'] === null) {
                continue;
            }
            $bundles[$definition['js']] = true;
        }

        return array_keys($bundles);
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<string>
     */
    public static function dependencies(array $sections): array
    {
        $deps = [];

        foreach ($sections as $section) {
            $definition = self::find((string) ($section['type'] ?? ''));
            if ($definition === null) {
                continue;
            }
            foreach ($definition['dependencies'] as $dependency) {
                $deps[$dependency] = true;
            }
        }

        return array_keys($deps);
    }

    /**
     * @return list<array{type: string, label: string, group: string, group_label: string, preview: string, css: string, js: string|null, dependencies: list<string>, fields: list<array<string, mixed>>, keys: list<string>, wrap_root: bool}>
     */
    private static function definitions(): array
    {
        $used = [];
        $out = [];

        foreach (self::origins() as $group => $meta) {
            $sections = $group === 'site' ? SiteSectionDefinition::all() : PageContentDefinition::for($group);

            foreach ($sections as $section) {
                $keys = self::keysFromFields($section['fields'] ?? []);
                $preview = (string) ($section['preview'] ?? 'section');
                $base = $group.'.'.$preview;
                $type = $base;
                $n = 2;
                while (isset($used[$type])) {
                    $type = $base.'-'.$n;
                    $n++;
                }
                $used[$type] = true;

                $wrapRoot = self::isWrapRoot($section['fields'] ?? [], $keys);

                $out[] = [
                    'type' => $type,
                    'label' => (string) ($section['label'] ?? 'Seção'),
                    'group' => $group,
                    'group_label' => $meta['label'],
                    'preview' => $preview,
                    'css' => $meta['css'],
                    'js' => $meta['js'],
                    'dependencies' => self::dependenciesFor($group, $keys, $preview),
                    'fields' => self::relativeFields($section['fields'] ?? [], $keys, $wrapRoot),
                    'keys' => $keys,
                    'wrap_root' => $wrapRoot,
                ];
            }
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, css: string, js: string|null}>
     */
    private static function origins(): array
    {
        return [
            'site' => ['label' => 'Site', 'css' => 'site', 'js' => null],
            'home' => ['label' => 'Home', 'css' => 'home', 'js' => 'home'],
            'about' => ['label' => 'Quem somos', 'css' => 'quem-somos', 'js' => 'quem-somos'],
            'expertise' => ['label' => 'Expertise', 'css' => 'expertise', 'js' => 'expertise'],
            'structure' => ['label' => 'Estrutura', 'css' => 'estrutura', 'js' => 'estrutura'],
            'ai' => ['label' => 'Inovação', 'css' => 'ia', 'js' => 'ia'],
            'esg' => ['label' => 'ESG', 'css' => 'esg', 'js' => 'esg'],
            'careers' => ['label' => 'Carreiras', 'css' => 'carreiras', 'js' => 'carreiras'],
            'contact' => ['label' => 'Contato', 'css' => 'contato', 'js' => 'contato'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<string>
     */
    private static function keysFromFields(array $fields): array
    {
        $keys = [];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $root = explode('.', $name, 2)[0];
            $keys[$root] = true;
        }

        return array_keys($keys);
    }

    /**
     * True when the only content key is itself a root field (e.g. expertise `cards`).
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  list<string>  $keys
     */
    private static function isWrapRoot(array $fields, array $keys): bool
    {
        if (count($keys) !== 1) {
            return false;
        }

        $key = $keys[0];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private static function dependenciesFor(string $group, array $keys, string $preview): array
    {
        $deps = [];

        if ($group === 'contact' && ($preview === 'form' || in_array('form', $keys, true))) {
            $deps[] = 'form';
        }

        return $deps;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private static function relativeFields(array $fields, array $keys, bool $wrapRoot): array
    {
        if (count($keys) !== 1 || $wrapRoot) {
            return $fields;
        }

        $prefix = $keys[0].'.';

        return array_map(function (array $field) use ($prefix) {
            $name = (string) ($field['name'] ?? '');
            if (str_starts_with($name, $prefix)) {
                $field['name'] = substr($name, strlen($prefix));
            }

            return $field;
        }, $fields);
    }
}
