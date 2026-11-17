<?php

namespace App\Services\Seo;

use App\Models\Page;
use App\Services\PublicContent;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Renderiza o rascunho do editor com os mesmos componentes React do prerender, sem gravar nada,
 * para a análise de SEO ler a hierarquia e a ordem reais do HTML.
 * Também grava o HTML público de uma página publicada para o Blade servir na hora.
 */
class PageRenderer
{
    public function __construct(private PublicContent $content) {}

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $sections
     */
    public function page(Page $page, array $sections, ?string $seoTitle, ?string $seoDescription): string
    {
        $page->forceFill([
            'content' => ['sections' => $this->withIds($sections)],
            'seo_title' => $seoTitle ?? $page->seo_title,
            'seo_description' => $seoDescription ?? $page->seo_description,
        ]);

        return $this->render($this->content->page($page), $this->content->site());
    }

    /**
     * Gera e grava o HTML público da página publicada. Usado no save e antes do Lighthouse
     * para o visitante e o PageSpeed não lerem a versão antiga enquanto o prerender completo ainda corre.
     */
    public function publish(Page $page): void
    {
        if ($page->status !== 'published') {
            return;
        }

        $html = $this->render($this->content->page($page), $this->content->site());
        $file = $this->fileFor($page);
        File::ensureDirectoryExists(dirname($file));
        File::put($file, $html);
    }

    public function fileFor(Page $page): string
    {
        $locale = $page->localeCode() ?: 'pt-BR';
        $path = trim($page->publicPath(), '/');
        $name = $path === '' ? 'index.html' : $path.'.html';

        return storage_path('app/prerender/'.$locale.'/'.$name);
    }

    public function findPublished(string $path): ?Page
    {
        $relative = trim($path, '/');

        if ($relative === '') {
            return Page::query()->published()->get()->first(fn (Page $page) => $page->isHome());
        }

        return Page::query()->published()->where('slug', $relative)->first();
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $site
     */
    private function render(array $page, array $site): string
    {
        $process = new Process(['node', base_path('scripts/render-page.mjs')], base_path(), null, null, 90);
        $process->setInput(json_encode(['page' => $page, 'site' => $site], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $process->run();

        if (! $process->isSuccessful() || trim($process->getOutput()) === '') {
            throw new RuntimeException('Não foi possível renderizar a página: '.mb_substr(trim($process->getErrorOutput()) ?: 'saída vazia', 0, 300));
        }

        return $process->getOutput();
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>, id?: string}>  $sections
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    private function withIds(array $sections): array
    {
        return array_values(array_map(fn (array $section, int $index) => [
            'id' => (string) ($section['id'] ?? 's'.$index),
            'type' => $section['type'],
            'data' => $section['data'],
        ], $sections, array_keys($sections)));
    }
}
