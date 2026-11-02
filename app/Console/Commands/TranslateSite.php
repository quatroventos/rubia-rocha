<?php

namespace App\Console\Commands;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Services\Translation\CaseStudyTranslator;
use App\Services\Translation\PageTranslator;
use App\Services\Translation\SiteTranslator;
use App\Services\Translation\TextTranslator;
use App\Support\Locales;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class TranslateSite extends Command
{
    protected $signature = 'site:translate
        {--locale= : Idiomas separados por vírgula (padrão: todos os traduzidos)}
        {--page= : Traduz só a página com este slug (pt-BR)}
        {--pages : Traduz as páginas}
        {--cases : Traduz os cases}
        {--menus : Recria os menus e textos do site no idioma}
        {--ui : Traduz o dicionário de interface do front (resources/js/site/i18n)}
        {--publish : Publica as traduções criadas}
        {--force : Retraduz mesmo o que já está em dia com o pt-BR}';

    protected $description = 'Traduz páginas, cases, menus e interface com a OpenAI. Grava como rascunho, editável no /gestao.';

    public function handle(TextTranslator $text, PageTranslator $pages, CaseStudyTranslator $cases, SiteTranslator $site): int
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

        $explicit = $this->option('pages') || $this->option('cases') || $this->option('menus') || $this->option('ui') || $this->option('page');
        $failures = 0;

        foreach ($locales as $locale) {
            $this->info("== {$locale} ==");

            if (! $explicit || $this->option('pages') || $this->option('page')) {
                $failures += $this->pages($pages, $locale);
            }

            if (! $explicit || $this->option('cases')) {
                $failures += $this->cases($cases, $locale);
            }

            if (! $explicit || $this->option('menus')) {
                $failures += $this->attempt('menus e textos do site', fn () => [$site->menus($locale), $site->settings($locale)]);
            }

            if ($this->option('ui')) {
                $failures += $this->attempt('interface', fn () => $this->ui($text, $locale));
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

    private function cases(CaseStudyTranslator $translator, string $locale): int
    {
        $failures = 0;
        foreach (CaseStudy::query()->originals()->published()->orderBy('slug')->get() as $case) {
            $existing = $case->translationFor($locale);
            if ($existing !== null && ! $this->option('force') && ! $existing->isTranslationStale()) {
                $this->line("  - case {$case->slug}: em dia");

                continue;
            }

            $failures += $this->attempt('case '.$case->slug, function () use ($translator, $case, $locale) {
                $row = $translator->translate($case, $locale);
                if ($this->option('publish')) {
                    $row->forceFill(['status' => 'published', 'published_at' => $row->published_at ?? now()])->save();
                }

                return $row->publicPath();
            });
        }

        return $failures;
    }

    private function ui(TextTranslator $text, string $locale): string
    {
        $dir = resource_path('js/site/i18n');
        $source = json_decode((string) File::get($dir.'/'.Locales::default().'.json'), true);
        $target = $dir.'/'.$locale.'.json';
        $current = File::exists($target) ? (json_decode((string) File::get($target), true) ?: []) : [];

        $pending = $this->option('force')
            ? $source
            : array_diff_key($source, array_filter($current, fn ($value) => is_string($value) && $value !== ''));

        $translated = $text->translate($pending, $locale, 'Short user interface strings (buttons, labels, aria-labels). Keep {placeholders} untouched.');

        $result = [];
        foreach (array_keys($source) as $key) {
            $result[$key] = $translated[$key] ?? $current[$key] ?? $source[$key];
        }

        File::put($target, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        return count($pending).' chave(s)';
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
