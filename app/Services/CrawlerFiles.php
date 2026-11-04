<?php

namespace App\Services;

use App\Models\Page;
use App\Models\SiteSetting;
use App\Support\Seo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gera o robots.txt e o llms.txt a partir das páginas publicadas e das configurações de SEO.
 * Os arquivos são gravados a cada alteração de página, menu ou configuração e servidos prontos.
 */
class CrawlerFiles
{
    public const ROBOTS = 'robots.txt';

    public const LLMS = 'llms.txt';

    private const DIRECTORY = 'crawler';

    private const AI_CRAWLERS = ['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'PerplexityBot', 'Google-Extended'];

    public function __construct(private SitemapService $sitemap) {}

    public function write(): void
    {
        $disk = Storage::disk('local');
        $disk->put(self::DIRECTORY.'/'.self::ROBOTS, $this->robots());
        $disk->put(self::DIRECTORY.'/'.self::LLMS, $this->llms());
    }

    public function read(string $file): string
    {
        $disk = Storage::disk('local');
        $path = self::DIRECTORY.'/'.$file;

        if (! $disk->exists($path)) {
            $this->write();
        }

        return (string) $disk->get($path);
    }

    public function robots(): string
    {
        $site = SiteSetting::query()->first();
        $blocks = ["User-agent: *\nAllow: /"];

        foreach (self::AI_CRAWLERS as $agent) {
            $blocks[] = "User-agent: {$agent}\nAllow: /";
        }

        $extra = trim(str_replace("\r\n", "\n", (string) $site?->robots_extra));

        if ($extra !== '') {
            $blocks[] = $extra;
        }

        $blocks[] = 'Sitemap: '.$this->sitemap->sitemapUrl();

        return implode("\n\n", $blocks)."\n";
    }

    public function llms(): string
    {
        $site = SiteSetting::query()->first();
        $defaults = Seo::defaults($site);
        $siteName = $defaults['og_site_name'];
        $summary = trim((string) ($site?->llms_summary ?: $defaults['seo_description']));
        $details = trim(str_replace("\r\n", "\n", (string) $site?->llms_details));

        $lines = ['# '.$siteName, ''];

        if ($summary !== '') {
            $lines[] = '> '.preg_replace('/\s+/', ' ', $summary);
            $lines[] = '';
        }

        if ($details !== '') {
            $lines[] = $details;
            $lines[] = '';
        }

        foreach ($this->pageGroups() as $heading => $pages) {
            $lines[] = '## '.$heading;
            $lines[] = '';

            foreach ($pages as $page) {
                $lines[] = $this->pageLine($page, $siteName, $defaults['seo_description']);
            }

            $lines[] = '';
        }

        $contact = $this->contactLines($site);

        if ($contact !== []) {
            $lines[] = '## Contato';
            $lines[] = '';
            array_push($lines, ...$contact);
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines))."\n";
    }

    /**
     * Páginas de primeiro nível em "Páginas"; as aninhadas (ensaios/gestante) num grupo com o nome do pai.
     *
     * @return array<string, Collection<int, Page>>
     */
    private function pageGroups(): array
    {
        $pages = Page::query()
            ->published()
            ->originals()
            ->orderedByMenu()
            ->get()
            ->reject(fn (Page $page) => str_contains(strtolower((string) $page->robots), 'noindex'));

        $groups = ['Páginas' => $pages->reject(fn (Page $page) => str_contains($page->slug, '/'))->values()];

        foreach ($pages->filter(fn (Page $page) => str_contains($page->slug, '/'))->groupBy(fn (Page $page) => Str::before($page->slug, '/')) as $parent => $children) {
            $groups[Str::ucfirst(str_replace('-', ' ', (string) $parent))] = $children->values();
        }

        return array_filter($groups, fn (Collection $items) => $items->isNotEmpty());
    }

    private function pageLine(Page $page, string $siteName, ?string $fallbackDescription): string
    {
        $title = trim((string) preg_replace('/\s*[|\-–—]\s*'.preg_quote($siteName, '/').'\s*$/u', '', (string) $page->seo_title));
        $title = $title !== '' ? $title : $siteName;
        $description = trim((string) preg_replace('/\s+/', ' ', (string) ($page->seo_description ?: $fallbackDescription)));
        $line = '- ['.str_replace(['[', ']'], ['(', ')'], $title).']('.$this->sitemap->absolute($page->publicPath()).')';

        return $description !== '' ? $line.': '.$description : $line;
    }

    /**
     * @return list<string>
     */
    private function contactLines(?SiteSetting $site): array
    {
        if ($site === null) {
            return [];
        }

        return array_values(array_filter([
            filled($site->whatsapp_url) ? '- WhatsApp: '.$site->whatsapp_url : null,
            filled($site->phone) ? '- Telefone: '.$site->phone : null,
            filled($site->email) ? '- E-mail: '.$site->email : null,
            filled($site->instagram_url) ? '- Instagram: '.$site->instagram_url : null,
            filled($site->address) ? '- Endereço: '.$site->address : null,
        ]));
    }
}
