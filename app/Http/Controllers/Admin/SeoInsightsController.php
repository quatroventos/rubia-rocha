<?php

namespace App\Http\Controllers\Admin;

use App\Cms\SectionCatalog;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Content\SectionCopyWriter;
use App\Services\LighthouseService;
use App\Services\Seo\Keyword;
use App\Services\Seo\MarketInsights;
use App\Services\Seo\PageDocument;
use App\Services\Seo\PageRenderer;
use App\Services\Seo\SeoAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Painel de SEO do editor: nota por palavra-chave, correções por IA e Lighthouse da versão publicada.
 */
class SeoInsightsController extends Controller
{
    /**
     * Auditorias do Lighthouse que se resolvem reescrevendo texto.
     */
    private const LIGHTHOUSE_FIXES = [
        'lighthouse:meta-description' => 'seo_description',
        'lighthouse:document-title' => 'seo_title',
    ];

    public function analyze(Request $request, SeoAnalyzer $analyzer): JsonResponse
    {
        $input = $this->input($request);

        return response()->json($analyzer->analyze($input));
    }

    /**
     * Renderiza o rascunho com os componentes do site e analisa o HTML final: hierarquia real dos títulos,
     * primeiro parágrafo depois do H1, texto do <main> e imagens.
     */
    public function analyzeRendered(Request $request, SeoAnalyzer $analyzer, PageRenderer $renderer): JsonResponse
    {
        $input = $this->input($request);

        try {
            $html = $renderer->page(Page::query()->findOrFail($input['id']), $input['sections'], $input['seo_title'] ?? null, $input['seo_description'] ?? null);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $document = PageDocument::fromHtml($html, PageDocument::make($input['kind'], $input['sections'], $input));

        return response()->json([...$analyzer->analyze($input, $document), 'rendered_at' => now()->toIso8601String()]);
    }

    /**
     * Dados do OpenSEO em cache para a palavra-chave. Não gasta créditos.
     */
    public function market(Request $request, MarketInsights $market): JsonResponse
    {
        [$keyword, $path] = $this->marketTarget($request);

        if (! $market->configured()) {
            return response()->json(['status' => 'unconfigured', 'keyword' => $keyword, 'message' => 'Dados de busca não configurados. Configure o OpenSEO em SEO > Integrações.']);
        }

        return response()->json($market->cached($keyword, $path) ?? ['status' => 'idle', 'keyword' => $keyword, 'paid' => $market->paidEnabled()]);
    }

    /**
     * Consulta as fontes agora (com consultas pagas liberadas, gasta créditos) e guarda por 30 dias.
     */
    public function fetchMarket(Request $request, MarketInsights $market): JsonResponse
    {
        [$keyword, $path] = $this->marketTarget($request);

        try {
            return response()->json($market->fetch($keyword, $path));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function fix(Request $request, SeoAnalyzer $analyzer, SectionCopyWriter $writer, MarketInsights $market): JsonResponse
    {
        $request->validate([
            'check' => ['required', 'string', 'max:80'],
            'target' => ['sometimes', 'nullable', 'array'],
            'target.type' => ['required_with:target', Rule::in(['seo_title', 'seo_description', 'section', 'field'])],
            'target.index' => ['sometimes', 'integer', 'min:0'],
            'target.path' => ['sometimes', 'string', 'max:200'],
            'target.name' => ['sometimes', 'string', 'max:60'],
            'status' => ['sometimes', Rule::in(['ok', 'bad'])],
            'message' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        $input = $this->input($request);
        $checkId = (string) $request->input('check');

        if (! $writer->configured()) {
            return response()->json(['message' => 'Defina a chave da OpenAI em SEO > Integrações para usar a IA.'], 422);
        }

        $keyword = trim((string) ($input['keyword'] ?? ''));

        if ($keyword === '') {
            return response()->json(['message' => 'Defina a palavra-chave antes de corrigir.'], 422);
        }

        if (isset(self::LIGHTHOUSE_FIXES[$checkId])) {
            $check = ['id' => $checkId, 'status' => 'bad', 'fix' => ['type' => self::LIGHTHOUSE_FIXES[$checkId]]];
        } elseif (is_array($request->input('target'))) {
            $check = $this->renderedCheck($request, $input, $checkId);
        } else {
            $check = collect($analyzer->analyze($input)['checks'])->firstWhere('id', $checkId);
        }

        if (! is_array($check) || empty($check['fix'])) {
            return response()->json(['message' => 'Este item já está resolvido ou não se corrige com texto.'], 422);
        }

        $target = $check['fix'];
        $context = [
            'kind' => $input['kind'],
            'title' => $input['seo_title'] ?? null,
            'description' => null,
            'path' => $input['path'] ?? null,
        ];

        try {
            if (in_array($target['type'], SectionCopyWriter::SEO_FIELDS, true)) {
                $value = $writer->seo($input['sections'], $context, [
                    'seo_title' => $input['seo_title'] ?? null,
                    'seo_description' => $input['seo_description'] ?? null,
                    'seo_keyword' => $keyword,
                ], $target['type'], 'melhorar', $this->seoGoal($checkId, $keyword, $this->topQuery($market, $keyword, $input['path'] ?? null)));
            } else {
                $document = PageDocument::make($input['kind'], $input['sections'], $input);
                $chunk = $document->find($target);

                if ($chunk === null) {
                    return response()->json(['message' => 'O campo indicado não foi encontrado na página.'], 422);
                }

                $value = $writer->keywordRewrite($document, $chunk, $keyword, $this->goal($checkId, $check['status'], $keyword, str_contains((string) ($check['message'] ?? ''), 'demais')), $this->maxLength($target, $chunk), $context);
            }
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'check' => $checkId,
            'target' => $target,
            'value' => $value,
            'keyword_used' => Keyword::contains(strip_tags($value), $keyword),
        ]);
    }

    public function lighthouse(Request $request, string $kind, int $id, LighthouseService $lighthouse): JsonResponse
    {
        $model = $this->target($kind, $id);
        $this->authorize('view', $model);

        if ($model->status !== 'published') {
            return response()->json($this->unpublished($model));
        }

        return response()->json($lighthouse->pageReport($model->publicPath()));
    }

    public function measure(Request $request, string $kind, int $id, LighthouseService $lighthouse): JsonResponse
    {
        $model = $this->target($kind, $id);
        $this->authorize('update', $model);

        if ($model->status !== 'published') {
            return response()->json($this->unpublished($model));
        }

        return response()->json($lighthouse->startPage($model->publicPath()));
    }

    /**
     * Check vindo da análise renderizada: o alvo vem do cliente e só vale se existir no rascunho.
     *
     * @param  array<string, mixed>  $input
     * @return array{id: string, status: string, message: string, fix: array<string, mixed>}|null
     */
    private function renderedCheck(Request $request, array $input, string $checkId): ?array
    {
        $target = collect((array) $request->input('target'))->only(['type', 'index', 'path', 'name'])->all();

        if (isset($target['index'])) {
            $target['index'] = (int) $target['index'];
        }

        if (! in_array($target['type'], SectionCopyWriter::SEO_FIELDS, true) && PageDocument::make($input['kind'], $input['sections'], $input)->find($target) === null) {
            return null;
        }

        return ['id' => $checkId, 'status' => (string) $request->input('status', 'bad'), 'message' => (string) $request->input('message', ''), 'fix' => $target];
    }

    private function target(string $kind, int $id): Page
    {
        return Page::query()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function unpublished(Page $model): array
    {
        return [
            'url' => $model->publicPath(),
            'status' => 'unavailable',
            'message' => 'Publique para medir. O Lighthouse mede a versão que está no ar.',
            'fetched_at' => null,
            'mobile' => null,
            'desktop' => null,
        ];
    }

    /**
     * @return array{kind: string, id: int|null, keyword: string|null, seo_title: string|null, seo_description: string|null, slug: string|null, home: bool, path: string|null, title: string|null, summary: string|null, client: string|null, cover_alt: string|null, og_image_alt: string|null, sections: list<array{type: string, data: array<string, mixed>}>}
     */
    private function input(Request $request): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['page'])],
            'id' => ['required', 'integer'],
            'keyword' => ['nullable', 'string', 'max:120'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:120'],
            'path' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:300'],
            'client' => ['nullable', 'string', 'max:255'],
            'cover_alt' => ['nullable', 'string', 'max:255'],
            'og_image_alt' => ['nullable', 'string', 'max:255'],
            'sections' => ['present', 'array', 'max:80'],
            'sections.*.type' => ['required', 'string', Rule::in(SectionCatalog::types())],
            'sections.*.data' => ['nullable', 'array'],
        ]);

        $page = Page::query()->findOrFail($data['id']);
        $this->authorize('update', $page);
        $home = $page->isHome();

        return [
            ...$data,
            'id' => isset($data['id']) ? (int) $data['id'] : null,
            'home' => $home,
            'sections' => array_values(array_map(fn (array $section) => [
                'type' => $section['type'],
                'data' => is_array($section['data'] ?? null) ? $section['data'] : [],
            ], $data['sections'])),
        ];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function marketTarget(Request $request): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['page'])],
            'id' => ['required', 'integer'],
            'keyword' => ['required', 'string', 'max:120'],
            'path' => ['nullable', 'string', 'max:255'],
        ]);

        $model = Page::query()->findOrFail($data['id']);
        $this->authorize('update', $model);

        return [trim($data['keyword']), $model->publicPath()];
    }

    private function topQuery(MarketInsights $market, string $keyword, ?string $path): ?string
    {
        $query = $market->cached($keyword, $path)['search_console'][0]['query'] ?? null;

        return is_string($query) && $query !== '' ? $query : null;
    }

    private function seoGoal(string $checkId, string $keyword, ?string $topQuery = null): string
    {
        return match ($checkId) {
            'search_console_fit' => $topQuery !== null
                ? 'O título precisa cobrir também a busca real "'.$topQuery.'", que já traz impressões para a página, sem perder a palavra-chave "'.$keyword.'".'
                : 'Use a palavra-chave "'.$keyword.'".',
            'title_keyword', 'lighthouse:document-title' => 'Inclua a palavra-chave exata "'.$keyword.'" no título.',
            'title_start' => 'Comece o título pela palavra-chave "'.$keyword.'".',
            'title_length' => 'Ajuste o tamanho do título para caber no limite, mantendo "'.$keyword.'".',
            'description_keyword', 'lighthouse:meta-description' => 'Escreva a description com a palavra-chave exata "'.$keyword.'" na primeira frase.',
            'description_length' => 'Ajuste o tamanho da description para a faixa pedida, mantendo "'.$keyword.'".',
            default => 'Use a palavra-chave "'.$keyword.'".',
        };
    }

    private function goal(string $checkId, string $status, string $keyword, bool $densityHigh = false): string
    {
        return match ($checkId) {
            'h1_keyword' => 'Reescreva o título principal da página para conter a palavra-chave exata "'.$keyword.'". Continua curto e direto.',
            'intro_keyword' => 'Reescreva o primeiro parágrafo para usar a palavra-chave exata "'.$keyword.'" uma vez, de preferência na primeira frase. Mantenha o tamanho e as informações.',
            'subheading_keyword' => 'Reescreva este subtítulo para usar "'.$keyword.'" ou uma variação próxima, sem perder o que a seção trata. Continua curto.',
            'density' => $status === 'bad' && $densityHigh
                ? 'A palavra-chave "'.$keyword.'" aparece demais na página. Neste texto, troque repetições por sinônimos ou pronomes e deixe no máximo uma ocorrência.'
                : 'Inclua a palavra-chave exata "'.$keyword.'" uma vez neste texto, de forma natural, mantendo o resto.',
            'image_alt' => 'Reescreva o alt descrevendo a imagem de forma objetiva para leitor de tela, incluindo "'.$keyword.'" só se fizer sentido para a imagem.',
            default => 'Use a palavra-chave "'.$keyword.'" de forma natural.',
        };
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  array{text: string, rich: bool}  $chunk
     */
    private function maxLength(array $target, array $chunk): int
    {
        $name = (string) ($target['name'] ?? $target['path'] ?? '');

        return match (true) {
            str_ends_with($name, 'alt') => 125,
            $name === 'summary' => 280,
            $name === 'title' || str_ends_with($name, '.title') => max(70, mb_strlen($chunk['text']) + 25),
            default => max(160, (int) round(mb_strlen($chunk['text']) * 1.3) + 40),
        };
    }
}
