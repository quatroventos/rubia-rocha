<?php

namespace App\Http\Controllers\Admin;

use App\Cms\FormDefinition;
use App\Http\Controllers\Controller;
use App\Models\FormOption;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', FormOption::class);

        $counts = FormOption::query()
            ->selectRaw('form, count(*) as total')
            ->groupBy('form')
            ->pluck('total', 'form');

        $forms = collect(FormDefinition::keys())->map(function (string $key) use ($counts) {
            $form = FormDefinition::resolved($key);

            return [
                'key' => $form['key'],
                'label' => $form['label'],
                'path' => $form['path'],
                'fields' => count(FormDefinition::fields($key)),
                'options' => (int) ($counts[$key] ?? 0),
            ];
        });

        return response()->json(['data' => $forms]);
    }

    public function show(string $form): JsonResponse
    {
        $this->authorize('viewAny', FormOption::class);

        return response()->json(['data' => $this->payload($form)]);
    }

    public function update(Request $request, string $form): JsonResponse
    {
        $this->authorize('create', FormOption::class);
        abort_unless(in_array($form, FormDefinition::keys(), true), 404);

        $data = $request->validate([
            'submit_label' => ['required', 'string', 'max:80'],
            'lgpd_label' => ['required', 'string', 'max:2000'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.label' => ['required', 'string', 'max:120'],
            'sections.*.fields' => ['required', 'array'],
            'sections.*.fields.*.name' => ['nullable', 'string', 'max:40'],
            'sections.*.fields.*.label' => ['required', 'string', 'max:255'],
            'sections.*.fields.*.type' => ['required', 'string', 'in:'.implode(',', FormDefinition::TYPES)],
            'sections.*.fields.*.required' => ['sometimes', 'boolean'],
            'sections.*.fields.*.hint' => ['nullable', 'string', 'max:255'],
            'sections.*.fields.*.options' => ['nullable', 'array'],
            'sections.*.fields.*.options.*.id' => ['nullable', 'integer'],
            'sections.*.fields.*.options.*.label' => ['required_with:sections.*.fields.*.options', 'string', 'max:255'],
            'sections.*.fields.*.options.*.value' => ['nullable', 'string', 'max:255'],
            'sections.*.fields.*.options.*.email' => ['nullable', 'email', 'max:180'],
            'sections.*.fields.*.options.*.sort' => ['nullable', 'integer', 'min:0'],
        ]);

        $sanitized = FormDefinition::sanitize($form, $data);
        $settings = SiteSetting::query()->firstOrFail();
        $schemas = $settings->form_schemas ?? [];
        $schemas[$form] = $sanitized['schema'];
        $settings->update(['form_schemas' => $schemas]);

        $keepFields = array_keys($sanitized['options']);

        foreach ($sanitized['options'] as $field => $rows) {
            $keep = [];

            foreach (array_values($rows) as $index => $row) {
                $payload = [
                    'form' => $form,
                    'field' => $field,
                    'label' => $row['label'],
                    'value' => $row['value'],
                    'email' => $row['email'] ?? null,
                    'sort' => $row['sort'] ?? ($index + 1),
                ];

                if (! empty($row['id'])) {
                    $option = FormOption::query()
                        ->where('form', $form)
                        ->where('field', $field)
                        ->whereKey($row['id'])
                        ->firstOrFail();
                    $option->update($payload);
                    $keep[] = $option->id;
                } else {
                    $keep[] = FormOption::query()->create($payload)->id;
                }
            }

            $query = FormOption::query()->where('form', $form)->where('field', $field);

            if ($keep === []) {
                $query->delete();
            } else {
                $query->whereNotIn('id', $keep)->delete();
            }
        }

        $orphans = FormOption::query()->where('form', $form);

        if ($keepFields !== []) {
            $orphans->whereNotIn('field', $keepFields);
        }

        $orphans->delete();

        return response()->json(['data' => $this->payload($form)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $form): array
    {
        $definition = FormDefinition::withOptions($form);
        abort_if($definition === null, 404);

        return $definition;
    }
}
