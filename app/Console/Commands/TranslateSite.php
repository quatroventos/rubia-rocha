<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Services\Translation\PageTranslator;
use App\Services\Translation\SiteTranslator;
use App\Services\Translation\TextTranslator;
use App\Support\Locales;
use Illuminate\Console\Command;
use Throwable;

class TranslateSite extends Command
{
    protected $signature = 'site:translate
        {--locale= : Idiomas separados por vírgula (padrão: todos os traduzidos)}
        {--page= : Traduz só a página com este slug (pt-BR)}
        {--pages : Traduz as páginas}
        {--menus : Recria os menus e textos do site no idioma}
        {--publish : Publica as traduções criadas}
        {--force : Retraduz mesmo o que já está em dia com o pt-BR}';

    protected $description = 'Traduz páginas e menus com a OpenAI. Grava como rascunho, editável no /gestao.';

    public function handle(TextTranslator $text, PageTranslator $pages, SiteTranslator $site): int
    {
        if (! $text->configured()) {
            $this->error('Defina OPENAI_API_KEY no .env antes de traduzir.');

            return self::FAILURE;
        }

        $locales = $this->locales();
        if ($locales === []) {
            $this->error('Nenhum idioma válido. Use: '.implode(', ', Locales::translated()));

            return self::FAILURE;
        }

        $explicit = $this->option('pages') || $this->option('menus') || $this->option('page');
        $failures = 0;

        foreach ($locales as $locale) {
            $this->info("== {$locale} ==");

            if (! $explicit || $this->option('pages') || $this->option('page')) {
                $failures += $this->pages($pages, $locale);
            }

            if (! $explicit || $this->option('menus')) {
                $failures += $this->attempt('menus e textos do site', fn () => [$site->menus($locale), $site->settings($locale)]);
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function pages(PageTranslator $translator, string $locale): int
    {
        $query = Page::query()->originals()->published()->orderBy('slug');
        if (is_string($this->option('page')) && $this->option('page') !== '') {
            $query->where('slug', $this->option('page'));
        }

        $failures = 0;
        foreach ($query->get() as $page) {
            $existing = $page->translationFor($locale);
            if ($existing !== null && ! $this->option('force') && ! $existing->isTranslationStale()) {
                $this->line("  - {$page->slug}: em dia");

                continue;
            }

            $failures += $this->attempt($page->slug, function () use ($translator, $page, $locale) {
                $row = $translator->translate($page, $locale);
                if ($this->option('publish')) {
                    $row->forceFill(['status' => 'published', 'published_at' => $row->published_at ?? now()])->save();
                }

                return $row->publicPath();
            });
        }

        return $failures;
    }

    private function attempt(string $label, callable $callback): int
    {
        try {
            $result = $callback();
            $this->line('  + '.$label.(is_string($result) ? ' -> '.$result : ''));

            return 0;
        } catch (Throwable $e) {
            $this->error('  x '.$label.': '.$e->getMessage());

            return 1;
        }
    }

    /**
     * @return list<string>
     */
    private function locales(): array
    {
        $option = $this->option('locale');
        if (! is_string($option) || trim($option) === '') {
            return Locales::translated();
        }

        return array_values(array_intersect(
            array_map('trim', explode(',', $option)),
            Locales::translated()
        ));
    }
}
