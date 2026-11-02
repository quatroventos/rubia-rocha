<?php

namespace App\Services\Seo;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Services\PublicContent;

/**
 * Gera o card de OG com o conteúdo salvo e grava o caminho na página ou no case, apagando o card anterior
 * quando nenhum outro registro o usa (traduções nascem com a OG da página original).
 */
class OgImageGenerator
{
    public function __construct(
        private readonly OgImageCapture $capture,
        private readonly PublicContent $content,
    ) {}

    public function page(Page $page): string
    {
        $path = $this->capture->capture(
            $this->content->page($page),
            $this->content->site($page->localeCode()),
            ($page->slug ?: 'home').'-'.$page->localeCode(),
        );

        $previous = $page->og_image;
        $page->forceFill([
            'og_image' => $path,
            'og_image_alt' => filled($page->og_image_alt) ? $page->og_image_alt : $page->seo_title,
        ])->saveQuietly();

        $this->discardUnused($previous, $path);

        return $path;
    }

    public function caseStudy(CaseStudy $case): string
    {
        $path = $this->capture->capture(
            $this->content->caseStudy($case),
            $this->content->site($case->localeCode()),
            'case-'.$case->slug.'-'.$case->localeCode(),
        );

        $previous = $case->og_image;
        $case->forceFill(['og_image' => $path])->saveQuietly();

        $this->discardUnused($previous, $path);

        return $path;
    }

    private function discardUnused(?string $previous, string $current): void
    {
        if (blank($previous) || $previous === $current) {
            return;
        }

        $inUse = Page::query()->where('og_image', $previous)->exists()
            || CaseStudy::withTrashed()->where('og_image', $previous)->exists();

        if (! $inUse) {
            $this->capture->discard($previous);
        }
    }
}
