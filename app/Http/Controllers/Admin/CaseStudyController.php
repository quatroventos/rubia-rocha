<?php

namespace App\Http\Controllers\Admin;

use App\Cms\SectionCatalog;
use App\Http\Controllers\Admin\Concerns\RestoresSoftDeletedModels;
use App\Http\Controllers\Controller;
use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Support\Locales;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CaseStudyController extends Controller
{
    use RestoresSoftDeletedModels;

    private const EXCLUDED_SECTIONS = ['site.hero', 'site.cases-browser', 'site.cases-list', 'site.contact', 'site.pricing'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CaseStudy::class);

        return response()->json([
            'data' => $this->scopedQuery($request, CaseStudy::class)->originals()->with('translations')->ordered()->get()->map(fn (CaseStudy $case) => $this->summary($case)),
            'library' => $this->library(),
            'sectors' => self::sectorOptions(),
            'services' => self::serviceOptions(),
            'seo' => Seo::defaults(SiteSetting::query()->first()),
        ]);
    }

    public function show(CaseStudy $caseStudy): JsonResponse
    {
        $this->authorize('view', $caseStudy);

        return response()->json([
            'data' => [
                ...$caseStudy->toArray(),
                'source_id' => $caseStudy->sourceRow()->id,
                'path' => $caseStudy->publicPath(),
                'stale' => $caseStudy->isTranslationStale(),
                'translations' => $this->translationStatus($caseStudy->sourceRow()),
                'content' => ['sections' => $caseStudy->sections()],
            ],
            'library' => $this->library(),
            'sectors' => self::sectorOptions(),
            'services' => self::serviceOptions(),
            'seo' => Seo::defaults(SiteSetting::query()->first()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CaseStudy::class);

        $case = CaseStudy::query()->create($this->payload($request));

        return response()->json(['data' => $case], 201);
    }

    public function update(Request $request, CaseStudy $caseStudy): JsonResponse
    {
        $this->authorize('update', $caseStudy);

        $caseStudy->update($this->payload($request, $caseStudy));

        return response()->json(['data' => $caseStudy->fresh()]);
    }

    public function destroy(CaseStudy $caseStudy): JsonResponse
    {
        $this->authorize('delete', $caseStudy);
        $caseStudy->delete();

        return response()->json([], 204);
    }

    public function restore(Request $request, int $caseStudy): JsonResponse
    {
        return $this->restoreSoftDeleted($request, CaseStudy::class, $caseStudy);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, ?CaseStudy $case = null): array
    {
        $data = $request->validate([
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('case_studies', 'slug')->where('locale', $case?->localeCode() ?? Locales::default())->ignore($case?->id),
            ],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'client' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:300'],
            'sector' => ['nullable', Rule::in(array_keys(CaseStudy::SECTORS))],
            'services' => ['nullable', 'array'],
            'services.*' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'array', 'max:4'],
            'tags.*' => ['nullable', 'string', 'max:60'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:2100'],
            'cover_path' => ['nullable', 'string', 'max:500'],
            'cover_alt' => ['nullable', 'string', 'max:255'],
            'cover_ratio' => ['nullable', Rule::in(CaseStudy::COVER_RATIOS)],
            'content' => ['nullable', 'array'],
            'content.sections' => ['nullable', 'array'],
            'content.sections.*.id' => ['required', 'string', 'max:40'],
            'content.sections.*.type' => ['required', 'string', Rule::in(self::sectionTypes())],
            'content.sections.*.data' => ['nullable', 'array'],
            'url' => ['nullable', 'url', 'max:500'],
            'featured' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'seo_keyword' => ['nullable', 'string', 'max:120'],
            'og_image' => ['nullable', 'string', 'max:500'],
        ], [
            'slug.required' => 'Informe o endereço do case.',
            'slug.regex' => 'Use apenas letras minúsculas, números e hífens.',
            'slug.unique' => 'Já existe um case com este endereço.',
            'client.required' => 'Informe o cliente.',
            'title.required' => 'Informe o título.',
            'summary.max' => 'O resumo aceita até 300 caracteres.',
            'content.sections.*.type.in' => 'Tipo de seção inválido para case.',
            'url.url' => 'Informe uma URL completa, com https://.',
        ]);

        $data['services'] = array_values(array_unique(array_filter($data['services'] ?? [])));
        $data['tags'] = $this->strings($data['tags'] ?? []);
        if (array_key_exists('content', $data) || $case === null) {
            $data['content'] = ['sections' => array_values(array_map(fn (array $section) => [
                'id' => $section['id'],
                'type' => $section['type'],
                'data' => $section['data'] ?? [],
            ], $data['content']['sections'] ?? []))];
        }
        $data['featured'] = (bool) ($data['featured'] ?? false);
        $data['sort'] = (int) ($data['sort'] ?? 0);

        if ($data['status'] === 'published' && ($case?->published_at === null)) {
            $data['published_at'] = now();
        }

        return $data;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    private function strings(array $values): array
    {
        return array_values(array_filter(array_map(fn ($value) => trim((string) $value), $values), fn (string $value) => $value !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(CaseStudy $case): array
    {
        return [
            'id' => $case->id,
            'slug' => $case->slug,
            'status' => $case->status,
            'client' => $case->client,
            'title' => $case->title,
            'sector' => $case->sector,
            'services' => $case->services ?? [],
            'cover_path' => $case->cover_path,
            'featured' => $case->featured,
            'sort' => $case->sort,
            'updated_at' => $case->updated_at?->toIso8601String(),
            'locale' => $case->localeCode(),
            'translations' => $case->isOriginal() ? $this->translationStatus($case) : [],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function translationStatus(CaseStudy $case): array
    {
        $rows = $case->relationLoaded('translations') ? $case->translations->keyBy('locale') : $case->translations()->get()->keyBy('locale');
        $hash = $case->sourceHash();
        $status = [];

        foreach (Locales::translated() as $locale) {
            $row = $rows->get($locale);
            $status[$locale] = [
                'id' => $row?->id,
                'status' => $row === null ? 'missing' : (string) $row->status,
                'stale' => $row !== null && $row->source_hash !== null && $row->source_hash !== $hash,
                'slug' => $row?->slug,
                'translated_at' => $row?->translated_at?->toIso8601String(),
            ];
        }

        return $status;
    }

    /**
     * Seções do site disponíveis no case. O hero vem dos dados do case; listagens de cases, formulário e planos ficam nas páginas.
     *
     * @return list<string>
     */
    private static function sectionTypes(): array
    {
        return array_values(array_filter(
            SectionCatalog::types(),
            fn (string $type) => str_starts_with($type, 'site.') && ! in_array($type, self::EXCLUDED_SECTIONS, true)
        ));
    }

    /**
     * @return list<array{group: string, group_label: string, items: list<array<string, mixed>>}>
     */
    private function library(): array
    {
        return array_map(fn (array $group) => [
            ...$group,
            'items' => array_values(array_filter($group['items'], fn (array $item) => in_array($item['type'], self::sectionTypes(), true))),
        ], SectionCatalog::library());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function sectorOptions(): array
    {
        return collect(CaseStudy::SECTORS)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function serviceOptions(): array
    {
        return Page::query()
            ->originals()
            ->whereNotIn('template', ['home', 'contact'])
            ->whereNotIn('slug', ['home', 'obrigado', 'cases', 'sobre', 'contato', 'blog'])
            ->orderBy('slug')
            ->get(['slug', 'seo_title'])
            ->map(fn (Page $page) => [
                'value' => $page->slug,
                'label' => trim((string) preg_replace('/\s*\|.*$/', '', (string) $page->seo_title)) ?: $page->slug,
            ])
            ->values()
            ->all();
    }
}
