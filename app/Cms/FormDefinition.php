<?php

namespace App\Cms;

use App\Models\FormOption;
use App\Services\SystemOptions;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationException;

class FormDefinition
{
    public const TYPES = ['text', 'email', 'tel', 'url', 'textarea', 'select', 'checkbox', 'file'];

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            self::contact(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $key): ?array
    {
        foreach (self::all() as $form) {
            if ($form['key'] === $key) {
                return $form;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }

    /**
     * @return list<string>
     */
    public static function systemNames(string $key): array
    {
        return array_keys(self::systemFields($key));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function systemFields(string $key): array
    {
        $form = self::get($key);

        if ($form === null) {
            return [];
        }

        $fields = [];

        foreach ($form['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                $fields[$field['name']] = $field;
            }
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    public static function columnMap(string $key): array
    {
        return match ($key) {
            'contact' => [
                'nome' => 'name',
                'email' => 'email',
                'telefone' => 'phone',
                'destino' => 'destination',
                'mensagem' => 'message',
            ],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function optionFields(string $key): array
    {
        $names = [];

        foreach (self::fields($key) as $field) {
            if (($field['type'] ?? '') === 'select') {
                $names[] = $field['name'];
            }
        }

        return $names;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function resolved(string $key): ?array
    {
        $defaults = self::get($key);

        if ($defaults === null) {
            return null;
        }

        $stored = app(SystemOptions::class)->site()?->form_schemas[$key] ?? null;
        $form = $defaults;

        if (is_array($stored)) {
            $form['submit_label'] = $stored['submit_label'] ?? $defaults['submit_label'];
            $form['lgpd_label'] = $stored['lgpd_label'] ?? $defaults['lgpd_label'];

            if (! empty($stored['sections']) && is_array($stored['sections'])) {
                $form['sections'] = $stored['sections'];
            }
        }

        $system = self::systemNames($key);
        $alwaysRequired = self::alwaysRequired($key);

        foreach ($form['sections'] as $sectionIndex => $section) {
            foreach ($section['fields'] ?? [] as $fieldIndex => $field) {
                $name = $field['name'] ?? '';
                $form['sections'][$sectionIndex]['fields'][$fieldIndex]['system'] = in_array($name, $system, true);

                if (in_array($name, $system, true) && in_array($name, $alwaysRequired, true)) {
                    $form['sections'][$sectionIndex]['fields'][$fieldIndex]['required'] = true;
                }

                $form['sections'][$sectionIndex]['fields'][$fieldIndex]['options_field'] = ($field['type'] ?? '') === 'select';
            }
        }

        return $form;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function withOptions(string $key): ?array
    {
        $form = self::resolved($key);

        if ($form === null) {
            return null;
        }

        $options = FormOption::query()
            ->where('form', $key)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->groupBy('field');

        foreach ($form['sections'] as $sectionIndex => $section) {
            foreach ($section['fields'] ?? [] as $fieldIndex => $field) {
                if (($field['type'] ?? '') !== 'select') {
                    continue;
                }

                $form['sections'][$sectionIndex]['fields'][$fieldIndex]['options'] = ($options[$field['name']] ?? collect())
                    ->map(fn (FormOption $option) => $option->only(['id', 'label', 'value', 'email', 'sort']))
                    ->values()
                    ->all();
                $form['sections'][$sectionIndex]['fields'][$fieldIndex]['options_field'] = true;
            }
        }

        return $form;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fields(string $key): array
    {
        $form = self::resolved($key);

        if ($form === null) {
            return [];
        }

        $fields = [];

        foreach ($form['sections'] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function validationRules(string $key): array
    {
        $rules = [];

        foreach (self::fields($key) as $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $required = (bool) ($field['required'] ?? false);
            $type = $field['type'] ?? 'text';

            $rules[$name] = match ($type) {
                'checkbox' => $required ? ['accepted'] : ['nullable'],
                'file' => array_values(array_filter([
                    $required ? 'required' : 'nullable',
                    'file',
                    'mimes:pdf,doc,docx',
                    'max:10240',
                ])),
                'email' => [$required ? 'required' : 'nullable', 'email', 'max:180'],
                'url' => [$required ? 'required' : 'nullable', 'url', 'max:255'],
                'textarea' => [$required ? 'required' : 'nullable', 'string', 'max:4000'],
                'select' => array_values(array_filter([
                    $required ? 'required' : 'nullable',
                    'string',
                    'max:80',
                    self::selectRule($key, $name),
                ])),
                'tel' => [$required ? 'required' : 'nullable', 'string', 'max:40'],
                default => [$required ? 'required' : 'nullable', 'string', 'max:180'],
            };
        }

        return $rules;
    }

    private static function selectRule(string $form, string $field): ?In
    {
        $values = FormOption::query()
            ->where('form', $form)
            ->where('field', $field)
            ->pluck('value')
            ->filter()
            ->values()
            ->all();

        if ($values === []) {
            return null;
        }

        return Rule::in($values);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function submissionAttributes(string $key, array $data): array
    {
        $map = self::columnMap($key);
        $nullable = ['linkedin'];
        $row = [];
        $extra = [];

        foreach (self::fields($key) as $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name) || $name === 'lgpd' || ($field['type'] ?? '') === 'file') {
                continue;
            }

            $value = $data[$name] ?? null;

            if (array_key_exists($name, $map)) {
                $row[$map[$name]] = in_array($name, $nullable, true)
                    ? ($value ?: null)
                    : ($value ?? '');

                continue;
            }

            $extra[$name] = [
                'label' => $field['label'] ?? $name,
                'value' => $value,
            ];
        }

        foreach ($map as $column) {
            $row[$column] ??= '';
        }

        $row['extra'] = $extra === [] ? null : $extra;

        return $row;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{schema: array<string, mixed>, options: array<string, list<array<string, mixed>>>}
     */
    public static function sanitize(string $key, array $data): array
    {
        $defaults = self::get($key);

        if ($defaults === null) {
            throw ValidationException::withMessages(['form' => 'Formulário inválido.']);
        }

        $system = self::systemFields($key);
        $seen = [];
        $sections = [];
        $options = [];

        foreach ($data['sections'] ?? [] as $section) {
            $fields = [];

            foreach ($section['fields'] ?? [] as $field) {
                $incomingName = (string) ($field['name'] ?? '');
                $label = trim((string) ($field['label'] ?? ''));

                if ($label === '') {
                    continue;
                }

                if (isset($system[$incomingName])) {
                    if (isset($seen[$incomingName])) {
                        continue;
                    }

                    $locked = $system[$incomingName];
                    $name = $locked['name'];
                    $type = $locked['type'];
                    $required = in_array($name, self::alwaysRequired($key), true)
                        ? true
                        : (bool) ($field['required'] ?? $locked['required'] ?? false);
                    $hint = $type === 'file'
                        ? (trim((string) ($field['hint'] ?? '')) ?: ($locked['hint'] ?? null))
                        : (trim((string) ($field['hint'] ?? '')) ?: null);
                    $seen[$name] = true;
                } else {
                    $type = (string) ($field['type'] ?? 'text');

                    if (! in_array($type, self::TYPES, true) || $type === 'file') {
                        $type = 'text';
                    }

                    $name = self::uniqueName($incomingName !== '' ? $incomingName : $label, array_keys($seen));
                    $required = (bool) ($field['required'] ?? false);
                    $hint = trim((string) ($field['hint'] ?? '')) ?: null;
                    $seen[$name] = true;
                }

                $clean = [
                    'name' => $name,
                    'label' => $label,
                    'type' => $type,
                    'required' => $required,
                ];

                if ($hint) {
                    $clean['hint'] = $hint;
                }

                $fields[] = $clean;

                if ($type === 'select') {
                    $options[$name] = [];

                    foreach (array_values($field['options'] ?? []) as $index => $option) {
                        $optionLabel = trim((string) ($option['label'] ?? ''));

                        if ($optionLabel === '') {
                            continue;
                        }

                        $optionEmail = trim((string) ($option['email'] ?? ''));

                        $options[$name][] = [
                            'id' => $option['id'] ?? null,
                            'label' => $optionLabel,
                            'value' => trim((string) ($option['value'] ?? '')) ?: $optionLabel,
                            'email' => $optionEmail !== '' ? $optionEmail : null,
                            'sort' => $option['sort'] ?? ($index + 1),
                        ];
                    }
                }
            }

            $sectionLabel = trim((string) ($section['label'] ?? ''));

            if ($sectionLabel === '' && $fields === []) {
                continue;
            }

            $sections[] = [
                'label' => $sectionLabel !== '' ? $sectionLabel : 'Campos',
                'fields' => $fields,
            ];
        }

        if ($sections === []) {
            $sections = $defaults['sections'];
        }

        $missing = array_diff(array_keys($system), array_keys($seen));

        if ($missing !== []) {
            $sections[] = [
                'label' => 'Campos obrigatórios',
                'fields' => array_values(array_map(fn (string $name) => $system[$name], $missing)),
            ];
        }

        return [
            'schema' => [
                'submit_label' => trim((string) ($data['submit_label'] ?? $defaults['submit_label'])),
                'lgpd_label' => trim((string) ($data['lgpd_label'] ?? $defaults['lgpd_label'])),
                'sections' => $sections,
            ],
            'options' => $options,
        ];
    }

    /**
     * Campos de sistema que o editor não pode tornar opcionais.
     *
     * @return list<string>
     */
    public static function alwaysRequired(string $key): array
    {
        return ['lgpd', 'curriculo', 'email', 'nome'];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(string $key): array
    {
        return match ($key) {
            'contact' => [
                'nome.required' => 'Me conta o seu nome.',
                'email.required' => 'Preciso de um e-mail para te responder.',
                'email.email' => 'Esse e-mail parece incompleto.',
                'mensagem.required' => 'Escreve um pouco sobre o momento que você quer registrar.',
            ],
            default => [],
        };
    }

    public static function isWide(array $field): bool
    {
        return in_array($field['type'] ?? '', ['textarea', 'checkbox', 'file'], true)
            || in_array($field['name'] ?? '', ['linkedin'], true);
    }

    /**
     * @param  list<string>  $taken
     */
    private static function uniqueName(string $source, array $taken): string
    {
        $base = Str::slug($source, '_');
        $base = preg_replace('/[^a-z0-9_]/', '', (string) $base) ?: 'campo';

        if (preg_match('/^[0-9]/', $base) === 1) {
            $base = 'campo_'.$base;
        }

        $reserved = array_merge($taken, ['empresa_site', '_token']);
        $name = substr($base, 0, 40);
        $i = 2;

        while (in_array($name, $reserved, true)) {
            $name = substr($base, 0, 36).'_'.$i;
            $i++;
        }

        return $name;
    }

    /**
     * @return array<string, mixed>
     */
    private static function contact(): array
    {
        return [
            'key' => 'contact',
            'label' => 'Contato',
            'path' => '/contato',
            'submit_label' => 'Enviar mensagem',
            'lgpd_label' => 'Autorizo o armazenamento dos dados informados para fins de contato e retorno, conforme a legislação aplicável de proteção de dados.',
            'sections' => [
                [
                    'label' => 'Contato',
                    'fields' => [
                        ['name' => 'nome', 'label' => 'Nome', 'type' => 'text', 'required' => true],
                        ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => true],
                        ['name' => 'mensagem', 'label' => 'Mensagem', 'type' => 'textarea', 'required' => true],
                    ],
                ],
            ],
        ];
    }
}
