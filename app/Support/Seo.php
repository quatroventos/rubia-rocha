<?php

namespace App\Support;

use App\Models\Page;
use App\Models\SiteSetting;

class Seo
{
    public const DEFAULT_SEPARATOR = '|';

    public const DEFAULT_TITLE_TEMPLATE = '%page% %sep% %site%';

    public const DEFAULT_ROBOTS = 'index, follow, max-image-preview:large';

    public const DEFAULT_TWITTER = 'summary_large_image';

    public const DEFAULT_LOCALE = 'pt_BR';

    public const FALLBACK_SITE_NAME = 'Rubia Rocha Fotografia';

    /**
     * Limites do que o Google exibe sem cortar. O título conta já composto com o template.
     */
    public const TITLE_MAX = 60;

    public const DESCRIPTION_MIN = 120;

    public const DESCRIPTION_MAX = 160;

    /**
     * Caracteres disponíveis para o título da página depois que o template acrescenta separador e nome do site.
     */
    public static function titleBudget(?SiteSetting $site): int
    {
        return max(20, self::TITLE_MAX - (mb_strlen(self::composeTitle('x', $site)) - 1));
    }

    /**
     * Corta no último espaço antes do limite, sem deixar pontuação solta no fim.
     */
    public static function fit(string $text, int $max): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max + 1);
        $space = mb_strrpos($cut, ' ');
        $cut = $space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : mb_substr($text, 0, $max);

        return rtrim($cut, " \t,;:|-–—");
    }

    /**
     * @return array{
     *     seo_separator: string,
     *     seo_title_template: string,
     *     seo_description: ?string,
     *     seo_og_image: ?string,
     *     seo_og_image_alt: ?string,
     *     seo_robots: string,
     *     og_site_name: string,
     *     og_locale: string,
     *     twitter_card: string
     * }
     */
    public static function defaults(?SiteSetting $site): array
    {
        $siteName = filled($site?->og_site_name)
            ? (string) $site->og_site_name
            : (filled($site?->name) ? (string) $site->name : self::FALLBACK_SITE_NAME);

        return [
            'seo_separator' => filled($site?->seo_separator) ? (string) $site->seo_separator : self::DEFAULT_SEPARATOR,
            'seo_title_template' => filled($site?->seo_title_template) ? (string) $site->seo_title_template : self::DEFAULT_TITLE_TEMPLATE,
            'seo_description' => filled($site?->seo_description) ? (string) $site->seo_description : null,
            'seo_og_image' => filled($site?->seo_og_image) ? (string) $site->seo_og_image : null,
            'seo_og_image_alt' => filled($site?->seo_og_image_alt) ? (string) $site->seo_og_image_alt : null,
            'seo_robots' => filled($site?->seo_robots) ? (string) $site->seo_robots : self::DEFAULT_ROBOTS,
            'og_site_name' => $siteName,
            'og_locale' => filled($site?->og_locale) ? (string) $site->og_locale : self::DEFAULT_LOCALE,
            'twitter_card' => filled($site?->twitter_card) ? (string) $site->twitter_card : self::DEFAULT_TWITTER,
        ];
    }

    public static function composeTitle(?string $pageTitle, ?SiteSetting $site): string
    {
        $defaults = self::defaults($site);
        $siteName = $defaults['og_site_name'];
        $pageTitle = trim((string) $pageTitle);

        if ($pageTitle === '') {
            return $siteName;
        }

        if (mb_stripos($pageTitle, $siteName) !== false) {
            return $pageTitle;
        }

        $composed = str_replace(
            ['%page%', '%sep%', '%site%'],
            [$pageTitle, $defaults['seo_separator'], $siteName],
            $defaults['seo_title_template']
        );

        return trim(preg_replace('/\s+/', ' ', $composed) ?? $composed);
    }

    /**
     * @param  array<string, string>  $alternates
     * @return array{
     *     title: string,
     *     description: ?string,
     *     robots: string,
     *     canonical: string,
     *     og_image: ?string,
     *     og_image_alt: ?string,
     *     twitter_card: string,
     *     og_site_name: string,
     *     og_locale: string,
     *     og_locale_alternates: list<string>,
     *     hreflang: list<array{hreflang: string, href: string}>
     * }
     */
    public static function resolve(?Page $page, ?SiteSetting $site, ?string $canonicalFallback = null, ?string $locale = null, array $alternates = []): array
    {
        $defaults = self::defaults($site);
        $locale = Locales::normalize($locale ?? $page?->locale);

        return [
            'title' => self::composeTitle($page?->seo_title, $site),
            'description' => filled($page?->seo_description) ? (string) $page->seo_description : $defaults['seo_description'],
            'robots' => filled($page?->robots) ? (string) $page->robots : $defaults['seo_robots'],
            'canonical' => filled($page?->canonical) ? (string) $page->canonical : ($canonicalFallback ?: url()->current()),
            'og_image' => filled($page?->og_image) ? (string) $page->og_image : $defaults['seo_og_image'],
            'og_image_alt' => filled($page?->og_image_alt)
                ? (string) $page->og_image_alt
                : ($defaults['seo_og_image_alt'] ?: $defaults['og_site_name']),
            'twitter_card' => filled($page?->twitter_card) ? (string) $page->twitter_card : $defaults['twitter_card'],
            'og_site_name' => $defaults['og_site_name'],
            'og_locale' => Locales::isDefault($locale) ? $defaults['og_locale'] : Locales::ogLocale($locale),
            'og_locale_alternates' => self::alternateOgLocales($locale, $alternates),
            'hreflang' => self::hreflangLinks($alternates),
        ];
    }

    /**
     * @param  array<string, string>  $alternates
     * @return list<string>
     */
    public static function alternateOgLocales(string $locale, array $alternates): array
    {
        return array_values(array_map(
            fn (string $code) => Locales::ogLocale($code),
            array_filter(array_keys($alternates), fn (string $code) => $code !== $locale)
        ));
    }

    /**
     * @param  array<string, string>  $alternates
     * @return list<array{hreflang: string, href: string}>
     */
    public static function hreflangLinks(array $alternates): array
    {
        if (count($alternates) < 2) {
            return [];
        }

        $links = [];
        foreach ($alternates as $code => $path) {
            $links[] = ['hreflang' => Locales::hreflang($code), 'href' => url($path)];
        }

        $default = $alternates[Locales::default()] ?? null;
        if ($default !== null) {
            $links[] = ['hreflang' => 'x-default', 'href' => url($default)];
        }

        return $links;
    }

    public static function fillEmptyPages(SiteSetting $site): int
    {
        $updated = 0;

        Page::query()->each(function (Page $page) use ($site, &$updated): void {
            $changes = [];

            if (blank($page->seo_description) && filled($site->seo_description)) {
                $changes['seo_description'] = $site->seo_description;
            }

            if (blank($page->og_image) && filled($site->seo_og_image)) {
                $changes['og_image'] = $site->seo_og_image;
            }

            if (blank($page->og_image_alt) && filled($site->seo_og_image_alt)) {
                $changes['og_image_alt'] = $site->seo_og_image_alt;
            }

            if (blank($page->robots) && filled($site->seo_robots)) {
                $changes['robots'] = $site->seo_robots;
            }

            if (blank($page->twitter_card) && filled($site->twitter_card)) {
                $changes['twitter_card'] = $site->twitter_card;
            }

            if ($changes === []) {
                return;
            }

            $page->update($changes);
            $updated++;
        });

        return $updated;
    }
}
