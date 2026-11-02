<?php

namespace App\Services\Translation;

use App\Cms\PageContentMigrator;
use App\Models\Page;
use App\Services\SystemOptions;
use App\Support\Locales;
use App\Support\Seo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PageTranslator
{
    public function __construct(private TextTranslator $translator) {}

    public function translate(Page $page, string $locale, ?int $userId = null): Page
    {
        $source = $page->sourceRow();

        if (Locales::isDefault($locale) || ! Locales::isSupported($locale)) {
            throw new InvalidArgumentException('Idioma inválido para tradução: '.$locale);
        }

        $content = PageContentMigrator::normalize($source->content, $source->template);
        $strings = ContentStrings::extract($content);

        $meta = array_filter([
            'meta.og_image_alt' => (string) $source->og_image_alt,
        ], fn (string $text) => ContentStrings::isText($text));

        $translated = $this->translator->translate(
            $strings + $meta,
            $locale,
            'Page "'.$source->seo_title.'" ('.$source->publicPath().') of the Quatro Ventos website.'
        );

        $translatedContent = ContentStrings::apply($content, $translated);
        $isHome = $source->isHome();

        $seo = $this->translator->seo([
            'slug' => $isHome ? '' : $source->slug,
            'title' => trim((string) preg_replace('/\s*\|.*$/', '', (string) $source->seo_title)),
            'description' => (string) $source->seo_description,
            'summary' => mb_substr(strip_tags(implode(' ', array_slice(array_values($translated), 0, 12))), 0, 1500),
        ], $locale);

        return DB::transaction(function () use ($source, $locale, $translatedContent, $translated, $seo, $isHome, $userId) {
            /** @var Page|null $row */
            $row = Page::query()->where('translation_of', $source->id)->where('locale', $locale)->lockForUpdate()->first();

            if ($row !== null) {
                $row->revisions()->create([
                    'user_id' => $userId,
                    'snapshot' => $row->revisionSnapshot(),
                    'created_at' => now(),
                ]);
            }

            $row ??= new Page([
                'translation_of' => $source->id,
                'locale' => $locale,
                'status' => 'draft',
            ]);

            $row->forceFill([
                'slug' => $isHome ? $source->slug : $this->uniqueSlug($seo['slug'] ?: $source->slug, $locale, $row->id),
                'template' => $source->template,
                'seo_title' => $seo['seo_title'] !== '' ? $seo['seo_title'] : (string) $source->seo_title,
                'seo_description' => $seo['seo_description'] !== '' ? $seo['seo_description'] : $source->seo_description,
                'canonical' => null,
                'og_image' => $source->og_image,
                'og_image_alt' => $translated['meta.og_image_alt'] ?? $source->og_image_alt,
                'robots' => $source->robots ?: Seo::DEFAULT_ROBOTS,
                'twitter_card' => $source->twitter_card ?: Seo::DEFAULT_TWITTER,
                'content' => $translatedContent,
                'source_hash' => $source->sourceHash(),
                'translated_at' => now(),
            ])->save();

            app(SystemOptions::class)->flush();

            return $row->fresh() ?? $row;
        });
    }

    private function uniqueSlug(string $slug, string $locale, ?int $ignoreId): string
    {
        $base = Str::slug($slug) ?: 'page';
        if (in_array($base, Page::reservedSlugs(), true)) {
            $base .= '-page';
        }

        $candidate = $base;
        $suffix = 2;

        while (Page::query()
            ->where('locale', $locale)
            ->where('slug', $candidate)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
