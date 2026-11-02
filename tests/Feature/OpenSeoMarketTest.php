<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenSeoMarketTest extends TestCase
{
    use RefreshDatabase;

    private const KEYWORD = 'automação de processos';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openseo.key' => 'oseo_test',
            'services.openseo.url' => 'https://openseo.test/mcp',
            'services.openseo.project' => 'proj_1',
            'services.openseo.site_url' => 'https://www.quatroventos.com.br',
            'services.openseo.paid' => true,
            'services.openai.key' => 'test-key',
            'services.openai.base_url' => 'https://openai.test/v1',
        ]);
    }

    private function page(): Page
    {
        return Page::query()->create([
            'slug' => 'automacao-de-processos',
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Automação de processos',
            'content' => ['sections' => []],
        ]);
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function sections(): array
    {
        return [
            ['type' => 'site.hero', 'data' => ['title' => 'Automação de processos sem planilha', 'subtitle' => '<p>Tiramos tarefas repetidas do time.</p>']],
        ];
    }

    /**
     * Simula o MCP do OpenSEO: handshake em JSON e as ferramentas, uma delas em SSE.
     */
    private function fakeOpenSeo(): void
    {
        Http::fake([
            'openai.test/*' => Http::response(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['value' => 'Automação de processos para empresas'])]]]]]),
            'openseo.test/*' => function (Request $request) {
                $method = $request['method'] ?? null;

                if ($method === 'initialize') {
                    return Http::response(['jsonrpc' => '2.0', 'id' => $request['id'], 'result' => ['protocolVersion' => '2025-06-18']], 200, ['Mcp-Session-Id' => 'sess_1']);
                }

                if ($method !== 'tools/call') {
                    return Http::response('', 202);
                }

                $payload = match ($request['params']['name']) {
                    'get_keyword_metrics' => ['keywords' => [['keyword' => 'automação de processos', 'searchVolume' => 1300, 'keywordDifficulty' => 34, 'intent' => 'commercial', 'cpc' => 4.2]]],
                    'research_keywords' => ['results' => [['seed' => 'automação de processos', 'keywords' => [
                        ['keyword' => 'automação de processos empresariais', 'searchVolume' => 480, 'keywordDifficulty' => 28],
                        ['keyword' => 'automação de processos com ia', 'searchVolume' => 210, 'keywordDifficulty' => 22],
                        ['keyword' => 'receita de bolo', 'searchVolume' => 90000],
                    ]]]],
                    'get_serp_results' => ['results' => [['query' => 'automação de processos', 'items' => [
                        ['rank_group' => 1, 'title' => 'O que é automação de processos | Concorrente', 'url' => 'https://concorrente.com.br/automacao', 'description' => 'Guia completo.'],
                        ['rank_group' => 2, 'title' => 'Automação de processos | Quatro Ventos', 'url' => 'https://quatroventos.com.br/automacao-de-processos', 'description' => 'Nossa página.'],
                    ]]]],
                    'get_search_console_performance' => ['rows' => [
                        ['keys' => ['automatizar processos'], 'clicks' => 3, 'impressions' => 120, 'ctr' => 0.025, 'position' => 8.4],
                    ]],
                };

                $message = ['jsonrpc' => '2.0', 'id' => $request['id'], 'result' => ['content' => [['type' => 'text', 'text' => json_encode($payload)]]]];

                if ($request['params']['name'] === 'get_serp_results') {
                    return Http::response("event: message\ndata: ".json_encode($message)."\n\n", 200, ['Content-Type' => 'text/event-stream']);
                }

                return Http::response($message);
            },
        ]);
    }

    public function test_fetch_normalizes_the_market_data_and_caches_it(): void
    {
        $this->fakeOpenSeo();
        $page = $this->page();
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->postJson('/gestao/api/seo/market', ['kind' => 'page', 'id' => $page->id, 'keyword' => self::KEYWORD])
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('metrics.volume', 1300)
            ->assertJsonPath('metrics.difficulty', 34)
            ->assertJsonPath('metrics.intent', 'comercial')
            ->assertJsonPath('related.0.keyword', 'automação de processos empresariais')
            ->assertJsonCount(2, 'related')
            ->assertJsonPath('serp.0.title', 'O que é automação de processos | Concorrente')
            ->assertJsonPath('own_position', 2)
            ->assertJsonPath('search_console.0.query', 'automatizar processos')
            ->assertJsonPath('url', 'https://www.quatroventos.com.br/automacao-de-processos');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'openseo.test')
            && ($request['method'] ?? null) === 'tools/call'
            && $request['params']['arguments']['projectId'] === 'proj_1'
            && $request->hasHeader('Authorization', 'Bearer oseo_test')
            && $request->hasHeader('Mcp-Session-Id', 'sess_1'));

        Http::fake();

        $this->actingAs($editor)
            ->getJson('/gestao/api/seo/market?'.http_build_query(['kind' => 'page', 'id' => $page->id, 'keyword' => self::KEYWORD]))
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('metrics.volume', 1300);

        Http::assertNothingSent();
    }

    public function test_free_mode_reads_only_search_console_and_skips_paid_tools(): void
    {
        config(['services.openseo.paid' => false]);
        $this->fakeOpenSeo();
        $page = $this->page();

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/seo/market', ['kind' => 'page', 'id' => $page->id, 'keyword' => self::KEYWORD])
            ->assertOk()
            ->assertJsonPath('paid', false)
            ->assertJsonPath('metrics', null)
            ->assertJsonPath('serp', [])
            ->assertJsonPath('own_position', null)
            ->assertJsonPath('search_console.0.query', 'automatizar processos');

        Http::assertNotSent(fn (Request $request) => in_array(data_get($request->data(), 'params.name'), ['get_keyword_metrics', 'research_keywords', 'get_serp_results'], true));
    }

    public function test_cached_market_data_enters_the_score_and_the_ai_prompt(): void
    {
        $this->fakeOpenSeo();
        $page = $this->page();
        $editor = $this->makeEditor();

        $this->actingAs($editor)->postJson('/gestao/api/seo/market', ['kind' => 'page', 'id' => $page->id, 'keyword' => self::KEYWORD])->assertOk();

        $checks = collect($this->actingAs($editor)->postJson('/gestao/api/seo/analyze', [
            'kind' => 'page',
            'id' => $page->id,
            'keyword' => self::KEYWORD,
            'seo_title' => 'Automação de processos',
            'path' => '/automacao-de-processos',
            'sections' => $this->sections(),
        ])->assertOk()->json('checks'))->keyBy('id');

        $this->assertSame('good', $checks['keyword_demand']['status']);
        $this->assertSame('good', $checks['keyword_difficulty']['status']);
        $this->assertSame('good', $checks['keyword_intent']['status']);
        $this->assertSame('ok', $checks['search_console_fit']['status']);
        $this->assertSame(['type' => 'seo_title'], $checks['search_console_fit']['fix']);

        $this->actingAs($editor)->postJson('/gestao/api/seo/fix', [
            'kind' => 'page',
            'id' => $page->id,
            'check' => 'search_console_fit',
            'keyword' => self::KEYWORD,
            'seo_title' => 'Automação de processos',
            'path' => '/automacao-de-processos',
            'sections' => $this->sections(),
        ])->assertOk();

        Http::assertSent(function (Request $request): bool {
            $body = (string) json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($request->url(), 'openai.test')
                && str_contains($body, '1300 buscas por mês')
                && str_contains($body, 'O que é automação de processos | Concorrente')
                && str_contains($body, 'busca real \"automatizar processos\"');
        });
    }

    public function test_without_openseo_project_reports_unconfigured_and_never_calls_it(): void
    {
        config(['services.openseo.project' => null]);
        Http::fake();
        $page = $this->page();
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->getJson('/gestao/api/seo/market?'.http_build_query(['kind' => 'page', 'id' => $page->id, 'keyword' => self::KEYWORD]))
            ->assertOk()
            ->assertJsonPath('status', 'unconfigured');

        $this->actingAs($editor)
            ->postJson('/gestao/api/seo/market', ['kind' => 'page', 'id' => $page->id, 'keyword' => self::KEYWORD])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_rejected_key_returns_a_clear_error(): void
    {
        Http::fake(['openseo.test/*' => Http::response('unauthorized', 401)]);
        $page = $this->page();

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/seo/market', ['kind' => 'page', 'id' => $page->id, 'keyword' => self::KEYWORD])
            ->assertStatus(422)
            ->assertJsonPath('message', 'O OpenSEO recusou a chave de API.');
    }
}
