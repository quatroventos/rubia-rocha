<?php

namespace App\Services\Seo;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Services\PublicContent;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Renderiza o rascunho do editor com os mesmos componentes React do prerender, sem gravar nada,
 * para a análise de SEO ler a hierarquia e a ordem reais do HTML.
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

        return $this->render($this->content->page($page), $this->content->site($page->localeCode()));
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $sections
     * @param  array{title?: string|null, summary?: string|null, client?: string|null, cover_alt?: string|null, seo_title?: string|null, seo_description?: string|null}  $fields
     */
    public function caseStudy(CaseStudy $case, array $sections, array $fields): string
    {
        $case->forceFill([
            ...collect($fields)->only(['title', 'summary', 'client', 'cover_alt', 'seo_title', 'seo_description'])->filter(fn ($value) => $value !== null)->all(),
            'content' => ['sections' => $this->withIds($sections)],
        ]);

        return $this->render($this->content->caseStudy($case), $this->content->site($case->localeCode()));
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
