<?php

namespace App\Http\Controllers\Admin;

use App\Cms\PageContentMigrator;
use App\Cms\SectionCatalog;
use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\SiteSetting;
use App\Services\PageSlugRedirects;
use App\Support\Locales;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    private const INSTITUTIONAL = 'Institucional';

    private const OUTSIDE_MENU = 'Fora do menu';

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Page::class);

        $pages = Page::query()->originals()->with('translations')->orderBy('slug')->get();
        $sections = $this->menuSections($pages->pluck('slug')->all());

        $pages = $pages
            ->map(function (Page $page) use ($sections) {
                $place = $sections[$page->slug] ?? ['section' => self::OUTSIDE_MENU, 'order' => [PHP_INT_MAX, 0]];

                return [...$this->summary($page), 'section' => $place['section'], 'translations' => $this->translationStatus($page), '_order' => $place['order']];
            })
            ->sortBy(fn (array $page) => $page['_order'])
            ->map(fn (array $page) => array_diff_key($page, ['_order' => true]))
            ->values();

        return response()->json(['data' => $pages]);
    }

    /**
     * Agrupa as paginas pela navegacao: filhas de um item do cabecalho ficam na secao dele,
     * assim como as paginas aninhadas no slug dele (ensaios/gestante fica em Ensaios);
     * o restante do cabecalho, a home e o rodape ficam em Institucional.
     *
     * @param  list<string>  $slugs
     * @return array<string, array{section: string, order: array{int, int}}>
     */
    private function menuSections(array $slugs): array
    {
        $items = MenuItem::query()
            ->where('locale', Locales::default())
            ->where('is_active', true)
            ->orderBy('sort')
            ->get();

        $slugOf = function (string $href): ?string {
            if (preg_match('#^https?://#i', $href) === 1) {
                return null;
            }

            $path = trim((string) (parse_url($href, PHP_URL_PATH) ?: $href), '/');

            return $path === '' ? 'home' : $path;
        };

        $header = $items->where('group', 'header');
        $parents = $header->whereNull('parent_id')->values();
        $sections = ['home' => ['section' => self::INSTITUTIONAL, 'order' => [0, 0]]];

        foreach ($parents as $index => $parent) {
            $children = $header->where('parent_id', $parent->id)->values();
            $slug = $slugOf($parent->href);
            $nested = $slug === null || $slug === 'home'
                ? []
                : array_values(array_filter($slugs, fn (string $candidate) => str_starts_with($candidate, $slug.'/')));

            if ($nested !== []) {
                $sections[$slug] = ['section' => $parent->label, 'order' => [$index + 1, 0]];

                foreach ($nested as $nestedIndex => $nestedSlug) {
                    $sections[$nestedSlug] ??= ['section' => $parent->label, 'order' => [$index + 1, 1000 + $nestedIndex]];
                }
            }

            if ($children->isEmpty()) {
                if ($slug !== null && $nested === []) {
                    $sections[$slug] ??= ['section' => self::INSTITUTIONAL, 'order' => [0, $index + 1]];
                }

                continue;
            }

            if ($slug !== null) {
                $sections[$slug] = ['section' => $parent->label, 'order' => [$index + 1, 0]];
            }

            foreach ($children as $childIndex => $child) {
                $childSlug = $slugOf($child->href);
                if ($childSlug !== null) {
                    $sections[$childSlug] ??= ['section' => $parent->label, 'order' => [$index + 1, $childIndex + 1]];
                }
            }
        }

        foreach ($items->where('group', '!=', 'header')->values() as $index => $item) {
            $slug = $slugOf($item->href);
            if ($slug !== null) {
                $sections[$slug] ??= ['section' => self::INSTITUTIONAL, 'order' => [0, 100 + $index]];
            }
        }

        return $sections;
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Page::class);

        $data = $request->validate([
            'seo_title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:#^'.Page::SLUG_PATTERN.'$#',
                Rule::unique('pages', 'slug')->where('locale', Locales::default()),
                Rule::notIn(Page::reservedSlugs()),
            ],
        ], $this->slugMessages());

        $page = Page::query()->create([
            'slug' => $data['slug'],
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => $data['seo_title'],
            'seo_description' => null,
            'content' => ['sections' => []],
        ]);

        return response()->json(['data' => $this->detail($page)], 201);
    }

    public function show(Page $page): JsonResponse
    {
        $this->authorize('view', $page);

        $site = SiteSetting::query()->first();
        $normalized = PageContentMigrator::normalize($page->content, $page->template);
        $sections = $normalized['sections'];

        return response()->json([
            'data' => $this->detail($page, $normalized),
            'schema' => SectionCatalog::schemaForSections($sections),
            'library' => SectionCatalog::library(),
            'seo' => Seo::defaults($site),
        ]);
    }

    public function update(Request $request, Page $page): JsonResponse
    {
        $this->authorize('update', $page);

        $data = $request->validate([
            'slug' => [
                'sometimes',
                'string',
                'max:120',
                'regex:#^'.Page::SLUG_PATTERN.'$#',
                Rule::unique('pages', 'slug')->where('locale', $page->localeCode())->ignore($page->id),
                Rule::notIn(array_values(array_diff(Page::reservedSlugs(), [$page->slug]))),
            ],
            'seo_title' => ['required', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'seo_keyword' => ['nullable', 'string', 'max:120'],
            'canonical' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string', 'max:500'],
            'og_image_alt' => ['nullable', 'string', 'max:255'],
            'robots' => ['nullable', 'string', 'max:255'],
            'twitter_card' => ['nullable', 'string', 'max:64'],
            'status' => ['sometimes', Rule::in(['draft', 'published'])],
            'content' => ['required', 'array'],
            'content.sections' => ['required', 'array'],
            'content.sections.*.id' => ['required', 'string', 'max:40'],
            'content.sections.*.type' => ['required', 'string', Rule::in(SectionCatalog::types())],
            'content.sections.*.data' => ['nullable', 'array'],
        ], $this->slugMessages());

        if ($page->isHome()) {
            unset($data['slug']);
        }

        if ($page->isOriginal()) {
            $data['status'] = 'published';
        } else {
            $data['status'] ??= $page->status;
        }

        if ($data['status'] === 'published' && $page->published_at === null) {
            $data['published_at'] = now();
        }

        $data['content'] = PageContentMigrator::normalize($data['content'], $page->template);

        if ($this->pageChanged($page, $data)) {
            $page->revisions()->create([
                'user_id' => $request->user()?->id,
                'snapshot' => $page->revisionSnapshot(),
                'created_at' => now(),
            ]);
        }

        $previousPath = $page->publicPath();

        $page->update($data);

        $redirect = null;

        if ($page->wasChanged('slug')) {
            $this->syncMenuLinks($page, $previousPath);
            $redirect = app(PageSlugRedirects::class)->record($previousPath, $page->publicPath());
        }

        $fresh = $page->fresh();
        $normalized = PageContentMigrator::normalize($fresh?->content, $fresh?->template);

        return response()->json([
            'data' => $this->detail($fresh, $normalized),
            'schema' => SectionCatalog::schemaForSections($normalized['sections']),
            'redirect' => $redirect,
        ]);
    }

    /**
     * Itens de menu apontam para a página pelo href; ao trocar o slug, quem está vinculado
     * pelo page_id ou ainda usa o endereço antigo passa para o novo, mantendo query e âncora.
     */
    private function syncMenuLinks(Page $page, string $previousPath): void
    {
        $newPath = $page->publicPath();
        $pathOf = fn (string $href): string => '/'.trim((string) (parse_url($href, PHP_URL_PATH) ?? ''), '/');

        MenuItem::query()
            ->where('locale', $page->localeCode())
            ->where(fn ($query) => $query->where('page_id', $page->id)->orWhereNull('page_id'))
            ->get()
            ->filter(fn (MenuItem $item) => preg_match('#^https?://#i', $item->href) !== 1 && $pathOf($item->href) === $previousPath)
            ->each(function (MenuItem $item) use ($newPath, $page): void {
                $suffix = (string) preg_replace('#^[^?\#]*#', '', $item->href);
                $item->update(['href' => $newPath.$suffix, 'page_id' => $page->id]);
            });
    }

    public function destroy(Page $page): JsonResponse
    {
        $this->authorize('delete', $page);

        abort_if($page->isHome(), 422, 'A home não pode ser excluída.');

        MenuItem::query()->where('page_id', $page->id)->delete();
        $page->delete();

        return response()->json(['ok' => true]);
    }

    public function revisions(Page $page): JsonResponse
    {
        $this->authorize('view', $page);

        $items = $page->revisions()
            ->with('user:id,name')
            ->latest('id')
            ->get()
            ->map(fn (PageRevision $revision) => $this->revisionSummary($revision));

        return response()->json(['data' => $items]);
    }

    public function restoreRevision(Request $request, Page $page, PageRevision $revision): JsonResponse
    {
        $this->authorize('update', $page);

        abort_unless($revision->page_id === $page->id, 404);

        $snapshot = $revision->snapshot ?? [];

        $page->revisions()->create([
            'user_id' => $request->user()?->id,
            'snapshot' => $page->revisionSnapshot(),
            'created_at' => now(),
        ]);

        $content = is_array($snapshot['content'] ?? null) ? $snapshot['content'] : [];
        $content = PageContentMigrator::normalize($content, $page->template);

        $payload = [
            'status' => $page->isOriginal() ? 'published' : $page->status,
            'seo_title' => $snapshot['seo_title'] ?? $page->seo_title,
            'seo_description' => $snapshot['seo_description'] ?? null,
            'seo_keyword' => $snapshot['seo_keyword'] ?? $page->seo_keyword,
            'canonical' => $snapshot['canonical'] ?? null,
            'og_image' => $snapshot['og_image'] ?? null,
            'og_image_alt' => $snapshot['og_image_alt'] ?? null,
            'robots' => $snapshot['robots'] ?? null,
            'twitter_card' => $snapshot['twitter_card'] ?? null,
            'content' => $content,
        ];

        if (! $page->isHome() && is_string($snapshot['slug'] ?? null) && $snapshot['slug'] !== '') {
            $payload['slug'] = $snapshot['slug'];
        }

        $payload['published_at'] = $page->published_at ?? now();

        $previousPath = $page->publicPath();
        $page->update($payload);
        $redirect = null;

        if ($page->wasChanged('slug')) {
            $this->syncMenuLinks($page, $previousPath);
            $redirect = app(PageSlugRedirects::class)->record($previousPath, $page->publicPath());
        }

        return response()->json(['data' => $this->detail($page->fresh()), 'redirect' => $redirect]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pageChanged(Page $page, array $data): bool
    {
        foreach (['status', 'slug', 'seo_title', 'seo_description', 'seo_keyword', 'canonical', 'og_image', 'og_image_alt', 'robots', 'twitter_card', 'content'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($field === 'content') {
                if (json_encode($page->content ?? []) !== json_encode($data['content'])) {
                    return true;
                }

                continue;
            }

            if ((string) ($page->{$field} ?? '') !== (string) ($data[$field] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function revisionSummary(PageRevision $revision): array
    {
        $snapshot = $revision->snapshot ?? [];

        return [
            'id' => $revision->id,
            'created_at' => $revision->created_at?->toIso8601String(),
            'user_name' => $revision->user?->name,
            'status' => $snapshot['status'] ?? null,
            'seo_title' => $snapshot['seo_title'] ?? null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function slugMessages(): array
    {
        return [
            'seo_title.required' => 'Informe o título.',
            'slug.required' => 'Informe o endereço da página.',
            'slug.regex' => 'Use letras minúsculas, números e hífens, com no máximo uma barra (ex.: ensaios/gestante).',
            'slug.unique' => 'Já existe uma página com este endereço.',
            'slug.not_in' => 'Este endereço está reservado pelo sistema.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Page $page): array
    {
        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'locale' => $page->localeCode(),
            'translation_of' => $page->translation_of,
            'path' => $page->publicPath(),
            'template' => $page->template,
            'status' => $page->status,
            'seo_title' => $page->seo_title,
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function translationStatus(Page $page): array
    {
        $rows = $page->relationLoaded('translations') ? $page->translations->keyBy('locale') : $page->translations()->get()->keyBy('locale');
        $hash = $page->sourceHash();
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
     * @param  array{sections: list<array<string, mixed>>}|null  $content
     * @return array<string, mixed>
     */
    private function detail(Page $page, ?array $content = null): array
    {
        $normalized = $content ?? PageContentMigrator::normalize($page->content, $page->template);

        return [
            ...$this->summary($page),
            'seo_description' => $page->seo_description,
            'seo_keyword' => $page->seo_keyword,
            'canonical' => $page->canonical,
            'og_image' => $page->og_image,
            'og_image_alt' => $page->og_image_alt,
            'robots' => $page->robots,
            'twitter_card' => $page->twitter_card,
            'published_at' => $page->published_at?->toIso8601String(),
            'content' => $normalized,
            'source_id' => $page->sourceRow()->id,
            'source_slug' => $page->sourceRow()->slug,
            'stale' => $page->isTranslationStale(),
            'translated_at' => $page->translated_at?->toIso8601String(),
            'translations' => $this->translationStatus($page->sourceRow()),
        ];
    }
}
