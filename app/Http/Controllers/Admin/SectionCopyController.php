<?php

namespace App\Http\Controllers\Admin;

use App\Cms\SectionCatalog;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Content\SectionCopyWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class SectionCopyController extends Controller
{
    public function store(Request $request, SectionCopyWriter $writer): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['page'])],
            'target' => ['nullable', Rule::in(['section', 'seo'])],
            'page_id' => ['required_if:kind,page', 'nullable', 'integer'],
            'index' => ['required_unless:target,seo', 'nullable', 'integer', 'min:0'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'seo_keyword' => ['nullable', 'string', 'max:120'],
            'mode' => ['required', Rule::in(SectionCopyWriter::MODES)],
            'instruction' => ['nullable', 'string', 'max:1000'],
            'field' => ['nullable', 'string', 'max:120'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'path' => ['nullable', 'string', 'max:255'],
            'sections' => ['present', 'array', 'max:60'],
            'sections.*.type' => ['required', 'string', Rule::in(SectionCatalog::types())],
            'sections.*.data' => ['present', 'array'],
        ]);

        $this->authorize('update', Page::query()->findOrFail($data['page_id']));

        if (! $writer->configured()) {
            return response()->json(['message' => 'Defina a chave da OpenAI em SEO > Integrações para usar a IA.'], 422);
        }

        $sections = array_map(fn (array $section) => ['type' => $section['type'], 'data' => $section['data']], array_values($data['sections']));
        $context = [
            'kind' => $data['kind'],
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'path' => $data['path'] ?? null,
        ];
        $instruction = trim((string) ($data['instruction'] ?? ''));

        if (($data['target'] ?? 'section') === 'seo') {
            try {
                $value = $writer->seo($sections, $context, [
                    'seo_title' => $data['seo_title'] ?? null,
                    'seo_description' => $data['seo_description'] ?? null,
                    'seo_keyword' => $data['seo_keyword'] ?? null,
                ], (string) ($data['field'] ?? ''), $data['mode'], $instruction);
            } catch (RuntimeException $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return response()->json(['data' => null, 'value' => $value]);
        }

        if (! isset($sections[$data['index']])) {
            return response()->json(['message' => 'Seção não encontrada.'], 422);
        }

        try {
            $result = $writer->rewrite($sections, $data['index'], $context, $data['mode'], $instruction, $data['field'] ?? null);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'data' => $result,
            'value' => filled($data['field'] ?? null) ? data_get($result, $data['field']) : null,
        ]);
    }

    public function proofread(Request $request, SectionCopyWriter $writer): JsonResponse
    {
        $this->authorize('viewAny', Page::class);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:20000'],
        ]);

        if (! $writer->configured()) {
            return response()->json(['message' => 'Defina a chave da OpenAI em SEO > Integrações para usar a IA.'], 422);
        }

        try {
            $value = $writer->proofread($data['text']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['value' => $value, 'changed' => $value !== $data['text']]);
    }
}
