<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Services\Seo\OgImageGenerator;
use App\Support\Locales;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Gera o card de OG (logo, fundo do hero, título e URL) das páginas publicadas, sobrescrevendo a OG atual.
 */
class GenerateOgImages extends Command
{
    protected $signature = 'site:og-images
        {--locale= : Idiomas separados por vírgula (padrão: todos)}
        {--slug= : Só o item com este slug}
        {--only-missing : Só onde a OG está vazia}';

    protected $description = 'Gera as OG images (1200x630) das páginas com o card de marca.';

    public function handle(OgImageGenerator $generator): int
    {
        $locales = $this->locales();
        $failures = 0;

        foreach ($this->filter(Page::query(), $locales)->get() as $page) {
            $failures += $this->attempt($page->publicPath(), fn () => $generator->page($page));
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @template TModel of Page
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
