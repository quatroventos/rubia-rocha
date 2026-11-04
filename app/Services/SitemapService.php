<?php

namespace App\Services;

use App\Models\Page;
use App\Support\Locales;
use Illuminate\Support\Collection;

class SitemapService
{
    public function baseUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public function sitemapUrl(): string
    {
        return $this->baseUrl().'/sitemap.xml';
    }

    public function robotsUrl(): string
    {
        return $this->baseUrl().'/robots.txt';
    }

    /** @return list<array{source: string, slug: ?string, locale: ?string, path: string, loc: string, lastmod: ?string, alternates: array<string, string>}> */
    public function entries(): array
    {
        $pages = $this->localized(
            Page::query()->published()->get()
                ->reject(fn (Page $page) => str_contains(strtolower((string) $page->robots), 'noindex')),
            'page'
        );

        $extra = collect(app(SystemOptions::class)->site()?->sitemap_extra ?? [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->unique()
            ->values()
            ->map(fn (string $path) => [
                'source' => 'extra',
                'slug' => null,
                'locale' => null,
                'path' => $this->normalizePath($path),
                'loc' => $this->absolute($path),
                'lastmod' => now()->toAtomString(),
                'alternates' => [],
            ]);

        return $pages->concat($extra)->values()->all();
    }

    /**
     * @param  Collection<int, Page>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function localized(Collection $rows, string $source): Collection
    {
        $groups = $rows->groupBy(fn ($row) => (int) ($row->translation_of ?? $row->id));

        return $rows
            ->sortBy(fn ($row) => [array_search(Locales::normalize($row->locale), Locales::all(), true), $row->slug])
            ->values()
            ->map(function ($row) use ($groups, $source) {
                $siblings = $groups->get((int) ($row->translation_of ?? $row->id), collect());
                $alternates = $siblings->count() > 1
                    ? $siblings
                        ->sortBy(fn ($item) => array_search(Locales::normalize($item->locale), Locales::all(), true))
                        ->mapWithKeys(fn ($item) => [Locales::normalize($item->locale) => $this->absolute($item->publicPath())])
                        ->all()
                    : [];

                return [
                    'source' => $source,
                    'slug' => $row->slug,
                    'locale' => Locales::normalize($row->locale),
                    'path' => $row->publicPath(),
                    'loc' => $this->absolute($row->publicPath()),
                    'lastmod' => $row->updated_at?->toAtomString(),
                    'alternates' => $alternates,
                ];
            });
    }

    public function xml(): string
    {
        $urls = collect($this->entries())->map(function (array $entry) {
            $lastmod = $entry['lastmod']
                ? '<lastmod>'.e($entry['lastmod']).'</lastmod>'
                : '';

            $links = '';
            $alternates = $entry['alternates'] ?? [];
            foreach ($alternates as $locale => $href) {
                $links .= '<xhtml:link rel="alternate" hreflang="'.e(Locales::hreflang($locale)).'" href="'.e($href).'"/>';
            }
            if ($alternates !== [] && isset($alternates[Locales::default()])) {
                $links .= '<xhtml:link rel="alternate" hreflang="x-default" href="'.e($alternates[Locales::default()]).'"/>';
            }

            return '<url><loc>'.e($entry['loc']).'</loc>'.$lastmod.$links.'</url>';
        })->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'
            .$urls
            .'</urlset>';
    }

    public function absolute(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->baseUrl().$this->normalizePath($path);
    }

    private function normalizePath(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsed = parse_url($path, PHP_URL_PATH);

            return $parsed ?: '/';
        }

        return '/'.ltrim($path, '/');
    }
}
