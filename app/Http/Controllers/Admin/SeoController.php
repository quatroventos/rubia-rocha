<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\CrawlerFiles;
use App\Services\SitemapService;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    public function show(SitemapService $sitemap, CrawlerFiles $crawlerFiles): JsonResponse
    {
        $settings = SiteSetting::current();
        $this->authorize('view', $settings);

        return response()->json(['data' => $this->payload($settings, $sitemap, $crawlerFiles)]);
    }

    public function update(Request $request, SitemapService $sitemap, CrawlerFiles $crawlerFiles): JsonResponse
    {
        $settings = SiteSetting::current();
        $this->authorize('update', $settings);

        $data = $request->validate([
            'seo_separator' => ['required', 'string', 'max:8'],
            'seo_title_template' => ['required', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'seo_og_image' => ['nullable', 'string', 'max:500'],
            'seo_og_image_alt' => ['nullable', 'string', 'max:255'],
            'seo_robots' => ['required', 'string', 'max:255'],
            'og_site_name' => ['required', 'string', 'max:255'],
            'og_locale' => ['required', 'string', 'max:16'],
            'twitter_card' => ['required', 'string', 'max:64'],
            'sitemap_extra' => ['nullable', 'array'],
            'sitemap_extra.*' => ['string', 'max:500'],
            'robots_extra' => ['nullable', 'string', 'max:5000'],
            'llms_summary' => ['nullable', 'string', 'max:500'],
            'llms_details' => ['nullable', 'string', 'max:10000'],
            'fill_empty_pages' => ['sometimes', 'boolean'],
        ]);

        $fillEmpty = (bool) ($data['fill_empty_pages'] ?? false);
        unset($data['fill_empty_pages']);

        $settings->update($data);
        $settings = $settings->fresh();

        if ($fillEmpty) {
            Seo::fillEmptyPages($settings);
        }

        $crawlerFiles->write();

        return response()->json(['data' => $this->payload($settings, $sitemap, $crawlerFiles)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SiteSetting $settings, SitemapService $sitemap, CrawlerFiles $crawlerFiles): array
    {
        $defaults = Seo::defaults($settings);

        $pages = Page::query()->originals()->orderBy('slug')->get()->map(function (Page $page) use ($settings, $sitemap) {
            $resolved = Seo::resolve($page, $settings, $sitemap->absolute($page->publicPath()));

            return [
                'slug' => $page->slug,
                'locale' => $page->localeCode(),
                'status' => $page->status,
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'canonical' => $page->canonical,
                'path' => $page->publicPath(),
                'resolved_title' => $resolved['title'],
                'resolved_description' => $resolved['description'],
            ];
        })->values();

        return [
            ...$defaults,
            'sitemap_extra' => $settings->sitemap_extra ?? [],
            'sitemap_url' => $sitemap->sitemapUrl(),
            'robots_url' => $sitemap->robotsUrl(),
            'llms_url' => $sitemap->baseUrl().'/'.CrawlerFiles::LLMS,
            'robots_extra' => $settings->robots_extra ?? '',
            'llms_summary' => $settings->llms_summary ?? '',
            'llms_details' => $settings->llms_details ?? '',
            'robots_preview' => $crawlerFiles->read(CrawlerFiles::ROBOTS),
            'llms_preview' => $crawlerFiles->read(CrawlerFiles::LLMS),
            'search_console_url' => 'https://search.google.com/search-console',
            'pages' => $pages,
            'entries' => $sitemap->entries(),
        ];
    }
}
