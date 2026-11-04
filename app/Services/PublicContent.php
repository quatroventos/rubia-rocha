<?php

namespace App\Services;

use App\Cms\FormDefinition;
use App\Cms\SectionCatalog;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Support\Locales;
use App\Support\ResponsiveImage;
use App\Support\Seo;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicContent
{
    public const DEFAULT_LOGO = '/rubia/img/logo-rubia-foto.svg';

    private const HOME_LABEL = 'Início';

    public function __construct(private SystemOptions $options) {}

    /**
     * @return array<string, mixed>
     */
    public function site(?string $locale = null): array
    {
        $locale = Locales::normalize($locale ?? Locales::current());
        $site = $this->options->site();
        $menus = $this->options->menus($locale);
        $gallery = $this->footerGallery($site);

        return [
            'name' => filled($site?->name) ? (string) $site->name : Seo::FALLBACK_SITE_NAME,
            'locale' => $locale,
            'home_path' => '/',
            'tagline' => $site?->tagline,
            'phone' => $site?->phone,
            'phone_href' => $site?->phone_href,
            'email' => $site?->email,
            'whatsapp_url' => $site?->whatsapp_url,
            'logo' => filled($site?->logo_path) ? (string) $site->logo_path : self::DEFAULT_LOGO,
            'address' => $site?->address,
            'instagram_url' => $site?->instagram_url,
            'facebook_url' => $site?->facebook_url,
            'menus' => [
                'header' => $this->menuNodes($menus->get('header')),
                'footer' => $this->menuNodes($menus->get('footer')),
            ],
            'footer_gallery_title' => filled($site?->footer_gallery_title) ? (string) $site->footer_gallery_title : null,
            'footer_gallery' => $gallery,
            'form' => FormDefinition::withOptions('contact'),
            'images' => (object) $this->imageMap(array_column($gallery, 'image')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function page(Page $page): array
    {
        $site = $this->options->site();
        $locale = $page->localeCode();
        $seo = Seo::resolve($page, $site, url($page->publicPath()), $locale);

        if (filled($seo['og_image'])) {
            $seo['og_image'] = media($seo['og_image']);
        }

        $sections = $this->sections($page);

        return [
            'slug' => $page->isHome() ? 'home' : $page->slug,
            'path' => $page->publicPath(),
            'locale' => $locale,
            'alternates' => [],
            'template' => $page->template,
            'sections' => $sections,
            'images' => (object) $this->imageMap($this->sectionImages($sections)),
            'seo' => $seo,
            'jsonld' => $this->jsonLd($page, $site, $seo, $sections),
        ];
    }

    /**
     * @param  list<array{id: string, type: string, data: array<string, mixed>}>  $sections
     * @param  array<string, mixed>  $seo
     * @return array<string, mixed>
     */
    private function jsonLd(Page $page, ?SiteSetting $site, array $seo, array $sections): array
    {
        $home = url('/');
        $graph = [[
            '@type' => 'WebPage',
            'name' => $seo['title'],
            'description' => $seo['description'],
            'url' => $seo['canonical'],
            'inLanguage' => Locales::hreflang($page->localeCode()),
            'isPartOf' => ['@type' => 'WebSite', 'name' => $seo['og_site_name'], 'url' => $home],
        ]];

        if ($page->isHome()) {
            $graph[] = array_filter([
                '@type' => 'ProfessionalService',
                '@id' => $home.'#negocio',
                'name' => $seo['og_site_name'],
                'description' => $seo['description'],
                'url' => $home,
                'image' => media(filled($site?->logo_path) ? (string) $site->logo_path : self::DEFAULT_LOGO),
                'telephone' => $site?->phone ?: null,
                'email' => $site?->email ?: null,
                'areaServed' => 'Curitiba e região',
                'address' => filled($site?->address) ? ['@type' => 'PostalAddress', 'addressLocality' => (string) $site->address, 'addressCountry' => 'BR'] : null,
                'sameAs' => array_values(array_filter([$site?->instagram_url, $site?->facebook_url])) ?: null,
            ]);
        }

        $crumbs = $this->breadcrumbs($page);
        if (count($crumbs) > 1) {
            $graph[] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(fn (array $crumb, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ], $crumbs, array_keys($crumbs)),
            ];
        }

        $questions = collect($sections)
            ->where('type', 'site.faq')
            ->flatMap(fn (array $section) => is_array($section['data']['items'] ?? null) ? $section['data']['items'] : [])
            ->filter(fn ($item) => is_array($item) && filled($item['question'] ?? null) && filled($item['answer'] ?? null))
            ->map(fn (array $item) => [
                '@type' => 'Question',
                'name' => trim(strip_tags((string) $item['question'])),
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags((string) $item['answer']))],
            ])
            ->values()
            ->all();

        if ($questions !== []) {
            $graph[] = ['@type' => 'FAQPage', 'mainEntity' => $questions];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }

    /**
     * Início, página-mãe (ensaios em ensaios/gestante) e a própria página.
     *
     * @return list<array{name: string, url: string}>
     */
    private function breadcrumbs(Page $page): array
    {
        if ($page->isHome()) {
            return [];
        }

        $crumbs = [['name' => self::HOME_LABEL, 'url' => url('/')]];

        if (str_contains((string) $page->slug, '/')) {
            $parent = Page::query()->published()->forLocale($page->localeCode())
                ->where('slug', Str::before((string) $page->slug, '/'))
                ->first();

            if ($parent !== null) {
                $crumbs[] = ['name' => $this->pageLabel($parent), 'url' => url($parent->publicPath())];
            }
        }

        $crumbs[] = ['name' => $this->pageLabel($page), 'url' => url($page->publicPath())];

        return $crumbs;
    }

    private function pageLabel(Page $page): string
    {
        return trim((string) preg_replace('/\s*\|.*$/', '', (string) $page->seo_title)) ?: (string) $page->slug;
    }

    /**
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    private function sections(Page $page): array
    {
        $sections = [];

        foreach ($page->sections() as $section) {
            $sections[] = [
                'id' => $section['id'],
                'type' => $section['type'],
                'data' => $this->sanitizeRich($section['type'], $section['data']),
            ];
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeRich(string $type, array $data): array
    {
        foreach (SectionCatalog::fieldPaths($type, 'rich') as $path) {
            $data = $this->mapPath($data, explode('.', $path), fn ($value) => is_string($value) ? rich($value) : $value);
        }

        return $data;
    }

    /**
     * @param  list<array{id: string, type: string, data: array<string, mixed>}>  $sections
     * @return list<string>
     */
    private function sectionImages(array $sections): array
    {
        $images = [];

        foreach ($sections as $section) {
            foreach (SectionCatalog::fieldPaths($section['type'], 'media') as $path) {
                array_push($images, ...$this->valuesAt($section['data'], explode('.', $path)));
            }
        }

        return $images;
    }

    /**
     * @param  list<mixed>  $sources
     * @return array<string, array{width: int, height: int, srcset: string|null}>
     */
    private function imageMap(array $sources): array
    {
        $map = [];

        foreach ($sources as $src) {
            if (! is_string($src) || $src === '' || isset($map[$src])) {
                continue;
            }

            $meta = ResponsiveImage::meta($src);
            if ($meta !== null) {
                $map[$src] = $meta;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $segments
     * @return array<string, mixed>
     */
    private function mapPath(array $data, array $segments, callable $callback): array
    {
        $segment = array_shift($segments);

        if ($segment === '*') {
            foreach ($data as $key => $value) {
                $data[$key] = $segments === [] ? $callback($value) : (is_array($value) ? $this->mapPath($value, $segments, $callback) : $value);
            }

            return $data;
        }

        if (! array_key_exists($segment, $data)) {
            return $data;
        }

        $data[$segment] = $segments === []
            ? $callback($data[$segment])
            : (is_array($data[$segment]) ? $this->mapPath($data[$segment], $segments, $callback) : $data[$segment]);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $segments
     * @return list<mixed>
     */
    private function valuesAt(array $data, array $segments): array
    {
        $segment = array_shift($segments);

        if ($segment === '*') {
            $values = [];
            foreach ($data as $value) {
                if ($segments === []) {
                    $values[] = $value;
                } elseif (is_array($value)) {
                    array_push($values, ...$this->valuesAt($value, $segments));
                }
            }

            return $values;
        }

        $value = Arr::get($data, $segment);

        if ($segments === []) {
            return [$value];
        }

        return is_array($value) ? $this->valuesAt($value, $segments) : [];
    }

    /**
     * @return list<array{image: string, alt: string, href: string}>
     */
    private function footerGallery(?SiteSetting $site): array
    {
        return collect(is_array($site?->footer_gallery) ? $site->footer_gallery : [])
            ->filter(fn ($item) => is_array($item) && is_string($item['image'] ?? null) && $item['image'] !== '')
            ->map(fn (array $item) => [
                'image' => (string) $item['image'],
                'alt' => (string) ($item['alt'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, MenuItem>|null  $items
     * @return list<array{label: string, href: string, children: list<array<string, mixed>>}>
     */
    private function menuNodes(?Collection $items): array
    {
        if ($items === null) {
            return [];
        }

        return $items->map(function (MenuItem $item) {
            $children = $item->relationLoaded('children') ? $item->children : collect();

            return [
                'label' => (string) $item->label,
                'href' => (string) $item->href,
                'children' => $this->menuNodes($children),
            ];
        })->values()->all();
    }
}
