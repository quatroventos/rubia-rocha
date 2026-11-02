<?php

namespace App\Services;

use App\Cms\FormDefinition;
use App\Models\CaseStudy;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\Translation\LinkLocalizer;
use App\Services\Translation\SiteTranslator;
use App\Support\Locales;
use App\Support\Seo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicContent
{
    /**
     * Seções cujos cases são resolvidos no payload público.
     *
     * @var list<string>
     */
    private const CASE_SECTIONS = ['site.cases', 'site.cases-list', 'site.showcase', 'site.case-grid', 'site.cases-browser'];

    private const HOME_LABEL = ['pt-BR' => 'Início', 'en' => 'Home', 'es' => 'Inicio'];

    public const LEGAL_PAGES = ['privacy' => 'politica-de-privacidade', 'cookies' => 'politica-de-cookies'];

    /** @var array<string, array<string, array{label: string, path: string}>> */
    private array $serviceTitles = [];

    public function __construct(private SystemOptions $options, private LinkLocalizer $links) {}

    /**
     * @return array<string, mixed>
     */
    public function site(?string $locale = null): array
    {
        $locale = Locales::normalize($locale ?? Locales::current());
        $site = $this->options->site();
        $menus = $this->options->menus($locale);
        $logo = media($site?->logo_path);

        return [
            'name' => filled($site?->name) ? (string) $site->name : Seo::FALLBACK_SITE_NAME,
            'locale' => $locale,
            'default_locale' => Locales::default(),
            'locales' => array_values(array_map(fn (array $item) => [
                ...$item,
                'prefix' => Locales::prefix($item['code']),
            ], Locales::publicList())),
            'cookie' => Locales::cookie(),
            'home_path' => Locales::path($locale, '/'),
            'tagline' => $site?->translated('tagline', $locale),
            'phone' => $site?->phone,
            'phone_href' => $site?->phone_href,
            'email' => $site?->email,
            'whatsapp_url' => $site?->whatsapp_url,
            'logo' => $logo !== '' ? $logo : asset('assets/logo-quatroventos.svg'),
            'address' => $site?->translated('address', $locale),
            'instagram_url' => $site?->instagram_url,
            'linkedin_url' => $site?->linkedin_url,
            'facebook_url' => $site?->facebook_url,
            'admin_path' => '/'.trim((string) config('cms.admin_path'), '/'),
            'menus' => [
                'header' => $this->menuNodes($menus->get('header'), $locale),
                'footer' => array_merge(
                    $this->menuNodes($menus->get('footer'), $locale),
                    $this->menuNodes($menus->get('footer_1'), $locale),
                    $this->menuNodes($menus->get('footer_2'), $locale),
                ),
            ],
            'seo' => [...Seo::defaults($site), 'og_locale' => Locales::ogLocale($locale)],
            'form' => $this->form($site, $locale),
            'plan_links' => (object) collect(is_array($site?->plan_links) ? $site->plan_links : [])
                ->filter(fn ($url) => is_string($url) && preg_match('#^https?://#i', $url) === 1)
                ->all(),
            'cases' => (object) $this->casesBySourceSlug($locale)
                ->map(fn (CaseStudy $case) => $this->caseCard($case, 0, $locale))
                ->all(),
            'legal' => (object) $this->legalLinks($locale),
        ];
    }

    /**
     * Links das políticas no idioma, só das páginas publicadas.
     *
     * @return array<string, string>
     */
    private function legalLinks(string $locale): array
    {
        $published = Page::query()->originals()->published()
            ->whereIn('slug', array_values(self::LEGAL_PAGES))
            ->pluck('slug')
            ->all();

        $links = [];
        foreach (self::LEGAL_PAGES as $key => $slug) {
            if (in_array($slug, $published, true)) {
                $links[$key] = $this->links->href('/'.$slug, $locale);
            }
        }

        return $links;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function form(?SiteSetting $site, string $locale): ?array
    {
        $form = FormDefinition::withOptions('contact');
        if ($form === null || Locales::isDefault($locale)) {
            return $form;
        }

        $translations = is_array($site?->translations) ? $site->translations : [];
        $dictionary = $translations[$locale]['form_strings'] ?? [];

        return SiteTranslator::localizeForm($form, is_array($dictionary) ? $dictionary : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function page(Page $page): array
    {
        $site = $this->options->site();
        $locale = $page->localeCode();
        $alternates = $page->alternates();
        $seo = Seo::resolve($page, $site, url($page->publicPath()), $locale, $alternates);

        if (filled($seo['og_image'])) {
            $seo['og_image'] = media($seo['og_image']);
        }

        return [
            'slug' => $page->isHome() ? 'home' : $page->slug,
            'path' => $page->publicPath(),
            'locale' => $locale,
            'alternates' => $alternates,
            'template' => $page->template,
            'sections' => $this->sections($page),
            'seo' => $seo,
            'jsonld' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $seo['title'],
                'description' => $seo['description'],
                'url' => $seo['canonical'],
                'inLanguage' => Locales::hreflang($locale),
                'isPartOf' => ['@type' => 'WebSite', 'name' => $seo['og_site_name'], 'url' => url(Locales::path($locale, '/'))],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function caseStudy(CaseStudy $case): array
    {
        $site = $this->options->site();
        $defaults = Seo::defaults($site);
        $cover = media($case->cover_path);
        $title = filled($case->seo_title) ? (string) $case->seo_title : $case->title.' | Case '.$case->client;

        $locale = $case->localeCode();
        $alternates = $case->alternates();

        $next = CaseStudy::query()->published()->forLocale($locale)->ordered()->whereKeyNot($case->id)->get()
            ->sortByDesc(fn (CaseStudy $other) => count(array_intersect($other->services ?? [], $case->services ?? [])))
            ->first();

        return [
            'slug' => 'cases/'.$case->slug,
            'path' => $case->publicPath(),
            'locale' => $locale,
            'alternates' => $alternates,
            'template' => 'case',
            'sections' => $this->resolveSections($case->sections(), $locale, null),
            'case' => [
                ...$this->caseCard($case, 0, $locale),
                'year' => $case->year,
                'url' => $case->url,
                'next' => $next ? $this->caseCard($next, 0, $locale) : null,
            ],
            'seo' => [
                'title' => Seo::composeTitle($title, $site),
                'description' => filled($case->seo_description) ? (string) $case->seo_description : ($case->summary ?: $defaults['seo_description']),
                'robots' => $defaults['seo_robots'],
                'canonical' => url($case->publicPath()),
                'og_image' => filled($case->og_image) ? media($case->og_image) : ($cover !== '' ? $cover : ($defaults['seo_og_image'] ? media($defaults['seo_og_image']) : null)),
                'og_image_alt' => $case->cover_alt ?: $case->title,
                'twitter_card' => $defaults['twitter_card'],
                'og_site_name' => $defaults['og_site_name'],
                'og_locale' => Locales::isDefault($locale) ? $defaults['og_locale'] : Locales::ogLocale($locale),
                'og_locale_alternates' => Seo::alternateOgLocales($locale, $alternates),
                'hreflang' => Seo::hreflangLinks($alternates),
            ],
            'jsonld' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    array_filter([
                        '@type' => 'CreativeWork',
                        'name' => $case->title,
                        'description' => $case->summary ?: null,
                        'image' => $cover !== '' ? $cover : null,
                        'url' => url($case->publicPath()),
                        'inLanguage' => Locales::hreflang($locale),
                        'dateCreated' => $case->year ? (string) $case->year : null,
                        'creator' => ['@type' => 'Organization', 'name' => $defaults['og_site_name'], 'url' => url(Locales::path($locale, '/'))],
                        'about' => ['@type' => 'Organization', 'name' => $case->client],
                    ]),
                    [
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => [
                            ['@type' => 'ListItem', 'position' => 1, 'name' => self::HOME_LABEL[$locale] ?? 'Início', 'item' => url(Locales::path($locale, '/'))],
                            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Cases', 'item' => url(Locales::path($locale, '/cases'))],
                            ['@type' => 'ListItem', 'position' => 3, 'name' => $case->client, 'item' => url($case->publicPath())],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cases(?string $service = null, ?string $locale = null): array
    {
        $locale = Locales::normalize($locale ?? Locales::current());

        return CaseStudy::query()->published()->forLocale($locale)->ordered()->get()
            ->filter(fn (CaseStudy $case) => $service === null || $case->hasService($service))
            ->map(fn (CaseStudy $case) => $this->caseCard($case, 3, $locale))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function caseCard(CaseStudy $case, ?int $prints, ?string $locale = null): array
    {
        $locale ??= $case->localeCode();
        $gallery = collect($case->prints())
            ->map(fn (array $item) => [
                'src' => media($item['path']),
                'caption' => $item['caption'],
                'alt' => $item['alt'] !== '' ? $item['alt'] : $item['caption'],
            ])
            ->values();

        return [
            'slug' => $case->slug,
            'path' => $case->publicPath(),
            'url' => filled($case->url) && preg_match('#^https?://#i', (string) $case->url) === 1 ? (string) $case->url : null,
            'client' => $case->client,
            'title' => $case->title,
            'summary' => (string) $case->summary,
            'sector' => $case->sectorLabel(),
            'services' => $this->serviceLinks($case->services ?? [], $locale),
            'tags' => array_values($case->tags ?? []),
            'cover' => $case->cover_path ? ['src' => media($case->cover_path), 'alt' => $case->cover_alt ?: $case->title] : null,
            'cover_ratio' => $case->cover_ratio,
            'gallery' => ($prints === null ? $gallery : $gallery->take($prints))->all(),
            'featured' => $case->featured,
        ];
    }

    /**
     * @param  list<string>  $slugs
     * @return list<array{slug: string, label: string, path: string}>
     */
    private function serviceLinks(array $slugs, string $locale): array
    {
        if ($slugs === []) {
            return [];
        }

        $titles = $this->serviceTitles($locale);

        return collect($slugs)
            ->filter(fn (string $slug) => isset($titles[$slug]))
            ->map(fn (string $slug) => ['slug' => $slug, 'label' => $titles[$slug]['label'], 'path' => $titles[$slug]['path']])
            ->values()
            ->all();
    }

    /**
     * Indexado pelo slug da página original (pt-BR), que é o que fica gravado nos cases.
     *
     * @return array<string, array{label: string, path: string}>
     */
    private function serviceTitles(string $locale): array
    {
        return $this->serviceTitles[$locale] ??= Page::query()->originals()->published()
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)->where('status', 'published')])
            ->get()
            ->mapWithKeys(function (Page $page) use ($locale) {
                $row = Locales::isDefault($locale) ? $page : $page->translations->first();
                if ($row === null) {
                    return [];
                }

                return [$page->slug => [
                    'label' => trim((string) preg_replace('/\s*\|.*$/', '', (string) $row->seo_title)) ?: $row->slug,
                    'path' => $row->publicPath(),
                ]];
            })
            ->all();
    }

    /**
     * @return Collection<string, CaseStudy>
     */
    private function casesBySourceSlug(string $locale): Collection
    {
        if (Locales::isDefault($locale)) {
            return CaseStudy::query()->originals()->published()->ordered()->get()->keyBy('slug');
        }

        return CaseStudy::query()->forLocale($locale)->published()->ordered()->with('original')->get()
            ->filter(fn (CaseStudy $case) => $case->original !== null)
            ->keyBy(fn (CaseStudy $case) => (string) $case->original?->slug);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveCases(string $type, array $data, string $locale, ?string $serviceSlug): array
    {
        if ($type === 'site.cases-list') {
            $data['cases'] = $this->cases(null, $locale);

            return $data;
        }

        if ($type === 'site.cases-browser') {
            return $this->resolveBrowser($data, $locale);
        }

        if ($type === 'site.showcase' || $type === 'site.case-grid') {
            $published = $this->casesBySourceSlug($locale);
            $data['cases'] = $this->pickCases($data['items'] ?? null, $published, $locale);

            if ($type === 'site.case-grid') {
                $data['featured_cases'] = $this->pickCases($data['featured'] ?? null, $published, $locale);
            }

            return $data;
        }

        $slugs = array_values(array_filter(is_array($data['items'] ?? null) ? $data['items'] : [], 'is_string'));
        $published = $this->casesBySourceSlug($locale);

        $selected = $slugs !== []
            ? collect($slugs)->map(fn (string $slug) => $published->get($slug))->filter()
            : $published->filter(fn (CaseStudy $case) => $serviceSlug !== null && $case->hasService($serviceSlug))->values();

        $layout = ($data['layout'] ?? '') === 'destaque' ? 'destaque' : 'par';
        $limit = $layout === 'destaque' ? 1 : 2;

        $data['layout'] = $layout;
        $data['cases'] = $selected->take($limit)->map(fn (CaseStudy $case) => $this->caseCard($case, 3, $locale))->values()->all();

        return $data;
    }

    /**
     * Cases publicados na ordem escolhida no painel; slugs de rascunho ou removidos ficam de fora.
     *
     * @param  Collection<string, CaseStudy>  $published
     * @return list<array<string, mixed>>
     */
    private function pickCases(mixed $slugs, Collection $published, string $locale): array
    {
        return collect(is_array($slugs) ? $slugs : [])
            ->filter(fn ($slug) => is_string($slug))
            ->map(fn (string $slug) => $published->get($slug))
            ->filter()
            ->map(fn (CaseStudy $case) => $this->caseCard($case, 0, $locale))
            ->values()
            ->all();
    }

    /**
     * Todos os cases publicados, cada um na primeira categoria cujos serviços ele atende.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveBrowser(array $data, string $locale): array
    {
        $categories = collect(is_array($data['categories'] ?? null) ? $data['categories'] : [])
            ->filter(fn ($category) => is_array($category) && filled($category['id'] ?? null))
            ->values();

        $data['cases'] = $this->casesBySourceSlug($locale)
            ->map(function (CaseStudy $case) use ($categories, $locale) {
                $original = $case->original ?? $case;
                $category = $categories->first(fn (array $category) => collect(is_array($category['services'] ?? null) ? $category['services'] : [])
                    ->contains(fn ($service) => is_string($service) && $original->hasService($service)));

                return [...$this->caseCard($case, 0, $locale), 'category' => (string) ($category['id'] ?? '')];
            })
            ->values()
            ->all();

        return $data;
    }

    /**
     * Link de compra definido em Configurações substitui o href do plano com a mesma chave.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolvePlanLinks(array $data): array
    {
        $links = collect(is_array($this->options->site()?->plan_links) ? $this->options->site()->plan_links : [])
            ->filter(fn ($url) => is_string($url) && preg_match('#^https?://#i', $url) === 1);

        if ($links->isEmpty() || ! is_array($data['categories'] ?? null)) {
            return $data;
        }

        foreach ($data['categories'] as $index => $category) {
            if (! is_array($category) || ! is_array($category['plans'] ?? null)) {
                continue;
            }

            foreach ($category['plans'] as $planIndex => $plan) {
                $key = is_array($plan) && is_string($plan['link_key'] ?? null) ? $plan['link_key'] : '';
                if ($key !== '' && $links->has($key)) {
                    $data['categories'][$index]['plans'][$planIndex]['href'] = $links->get($key);
                }
            }
        }

        return $data;
    }

    /**
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    private function sections(Page $page): array
    {
        $content = is_array($page->content) ? $page->content : [];
        $raw = $content['sections'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        return $this->resolveSections($raw, $page->localeCode(), $page->sourceRow()->slug);
    }

    /**
     * @param  array<int, mixed>  $raw
     * @param  string|null  $serviceSlug  Página de serviço que filtra o bloco "Cases selecionados" sem seleção manual.
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    private function resolveSections(array $raw, string $locale, ?string $serviceSlug): array
    {
        $sections = [];

        foreach ($raw as $section) {
            if (! is_array($section)) {
                continue;
            }

            $type = is_string($section['type'] ?? null) ? $section['type'] : '';
            if ($type === '') {
                continue;
            }

            $data = is_array($section['data'] ?? null) ? $section['data'] : [];

            if (in_array($type, self::CASE_SECTIONS, true)) {
                $data = $this->resolveCases($type, $data, $locale, $serviceSlug);
            }

            if ($type === 'site.pricing') {
                $data = $this->resolvePlanLinks($data);
            }

            if ($type === 'site.logos' && empty($data['own_logos'])) {
                $global = $this->options->site()?->client_logos;
                $data['items'] = is_array($global) ? array_values($global) : [];
            }

            if ($type === 'site.logos' && is_array($data['items'] ?? null)) {
                $data['items'] = array_map(
                    fn ($item) => is_array($item) && is_string($item['image'] ?? null)
                        ? [...$item, 'ratio' => $this->imageRatio($item['image'])]
                        : $item,
                    $data['items'],
                );
            }

            $data = $this->links->data($data, $locale);

            $sections[] = [
                'id' => is_string($section['id'] ?? null) && $section['id'] !== ''
                    ? $section['id']
                    : (string) Str::ulid(),
                'type' => $type,
                'data' => $data,
            ];
        }

        return $sections;
    }

    /**
     * Largura dividida pela altura do arquivo local (raster pelo cabeçalho, SVG pelo viewBox
     * ou width/height). O front usa para dar a cada logo a mesma área visual.
     */
    private function imageRatio(string $src): ?float
    {
        $path = (string) parse_url($src, PHP_URL_PATH);

        $absolute = match (true) {
            str_starts_with($path, '/storage/') => storage_path('app/public/'.substr($path, strlen('/storage/'))),
            str_starts_with($path, '/') => public_path(ltrim($path, '/')),
            default => null,
        };

        if ($absolute === null || str_contains($path, '..') || ! is_file($absolute)) {
            return null;
        }

        if (str_ends_with(strtolower($path), '.svg')) {
            $head = (string) file_get_contents($absolute, length: 4096);

            if (preg_match('/viewBox\s*=\s*["\']\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $head, $box) && (float) $box[2] > 0) {
                return round((float) $box[1] / (float) $box[2], 3);
            }

            if (preg_match('/<svg[^>]*\bwidth\s*=\s*["\']([\d.]+)/i', $head, $width) && preg_match('/<svg[^>]*\bheight\s*=\s*["\']([\d.]+)/i', $head, $height) && (float) $height[1] > 0) {
                return round((float) $width[1] / (float) $height[1], 3);
            }

            return null;
        }

        $size = @getimagesize($absolute);

        return $size && $size[1] > 0 ? round($size[0] / $size[1], 3) : null;
    }

    /**
     * @param  Collection<int, MenuItem>|null  $items
     * @return list<array{label: string, href: string, children: list<array<string, mixed>>}>
     */
    private function menuNodes(?Collection $items, string $locale): array
    {
        if ($items === null) {
            return [];
        }

        return $items->map(function (MenuItem $item) use ($locale) {
            $children = $item->relationLoaded('children') ? $item->children : collect();

            return [
                'label' => (string) $item->label,
                'href' => $this->links->href((string) $item->href, $locale),
                'children' => $this->menuNodes($children, $locale),
            ];
        })->values()->all();
    }
}
