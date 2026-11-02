<?php

namespace App\Console\Commands;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Services\Seo\OgImageGenerator;
use App\Support\Locales;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Gera o card de OG (logo, fundo do hero, título e URL) de páginas e cases publicados, sobrescrevendo a OG atual.
 */
class GenerateOgImages extends Command
{
    protected $signature = 'site:og-images
        {--pages : Só páginas}
        {--cases : Só cases}
        {--locale= : Idiomas separados por vírgula (padrão: todos)}
        {--slug= : Só o item com este slug}
        {--only-missing : Só onde a OG está vazia}';

    protected $description = 'Gera as OG images (1200x630) de páginas e cases com o card de marca.';

    public function handle(OgImageGenerator $generator): int
    {
        $locales = $this->locales();
        $both = ! $this->option('pages') && ! $this->option('cases');
        $failures = 0;

        if ($both || $this->option('pages')) {
            foreach ($this->filter(Page::query(), $locales)->get() as $page) {
                $failures += $this->attempt($page->localeCode().' '.$page->publicPath(), fn () => $generator->page($page));
            }
        }

        if ($both || $this->option('cases')) {
            foreach ($this->filter(CaseStudy::query(), $locales)->get() as $case) {
                $failures += $this->attempt($case->localeCode().' '.$case->publicPath(), fn () => $generator->caseStudy($case));
            }
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @template TModel of Page|CaseStudy
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $locales
     * @return Builder<TModel>
     */
    private function filter(Builder $query, array $locales): Builder
    {
        $query->published()->whereIn('locale', $locales)->orderBy('locale')->orderBy('slug');

        if (is_string($this->option('slug')) && $this->option('slug') !== '') {
            $query->where('slug', $this->option('slug'));
        }

        if ($this->option('only-missing')) {
            $query->where(fn (Builder $inner) => $inner->whereNull('og_image')->orWhere('og_image', ''));
        }

        return $query;
    }

    /**
     * @param  callable(): string  $callback
     */
    private function attempt(string $label, callable $callback): int
    {
        try {
            $this->line('  + '.$label.' -> '.$callback());

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
            return Locales::all();
        }

        return array_values(array_intersect(array_map('trim', explode(',', $option)), Locales::all()));
    }
}
