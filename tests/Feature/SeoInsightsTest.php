<?php

namespace Tests\Feature;

use App\Jobs\MeasurePageLighthouseJob;
use App\Models\CaseStudy;
use App\Models\Page;
use App\Services\LighthouseService;
use App\Services\Seo\PageRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SeoInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.key' => 'test-key',
            'services.openai.base_url' => 'https://openai.test/v1',
        ]);
    }

    private function page(array $overrides = []): Page
    {
        return Page::query()->create([
            'slug' => 'automacao-de-processos',
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Automação de processos',
            'content' => ['sections' => []],
            ...$overrides,
        ]);
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function sections(): array
    {
        return [
            ['type' => 'site.hero', 'data' => ['title' => 'Menos planilha no seu dia', 'subtitle' => '<p>Tiramos tarefas repetidas do time.</p>']],
            ['type' => 'site.prose', 'data' => ['title' => 'Como trabalhamos', 'body' => '<p>Mapeamos o processo e integramos os sistemas que você já usa.</p>']],
        ];
    }

    private function fakeAi(string $value): void
    {
        Http::fake([
            'openai.test/*' => Http::response(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['value' => $value])]]]]]),
        ]);
    }

    public function test_analysis_scores_the_unsaved_page_and_points_fixable_fields(): void
    {
        $page = $this->page();

        $response = $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/seo/analyze', [
                'kind' => 'page',
                'id' => $page->id,
                'keyword' => 'automação de processos',
                'seo_title' => 'Automação de processos para empresas',
                'seo_description' => '',
                'slug' => 'automacao-de-processos',
                'sections' => $this->sections(),
            ])
            ->assertOk()
            ->assertJsonPath('keyword', 'automação de processos');

        $checks = collect($response->json('checks'))->keyBy('id');

        $this->assertSame('good', $checks['title_keyword']['status']);
        $this->assertSame('good', $checks['slug_keyword']['status']);
        $this->assertSame('bad', $checks['h1_keyword']['status']);
        $this->assertSame(['type' => 'section', 'index' => 0, 'path' => 'title'], $checks['h1_keyword']['fix']);
        $this->assertSame(['type' => 'seo_description'], $checks['description_keyword']['fix']);
        $this->assertNull($checks['title_keyword']['fix']);
        $this->assertGreaterThan(0, $response->json('score'));
        $this->assertLessThan(100, $response->json('score'));
    }

    public function test_analysis_without_keyword_has_no_score(): void
    {
        $page = $this->page();

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/seo/analyze', ['kind' => 'page', 'id' => $page->id, 'sections' => $this->sections()])
            ->assertOk()
            ->assertJsonPath('score', null)
            ->assertJsonPath('checks.0.id', 'keyword');
    }

    public function test_keyword_used_by_another_page_is_flagged(): void
    {
        $page = $this->page();
        $this->page(['slug' => 'outra', 'seo_title' => 'Outra página | Quatro Ventos', 'seo_keyword' => 'Automacao de Processos']);

        $response = $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/seo/analyze', ['kind' => 'page', 'id' => $page->id, 'keyword' => 'automação de processos', 'sections' => $this->sections()])
            ->assertOk();

        $unique = collect($response->json('checks'))->firstWhere('id', 'unique_keyword');
        $this->assertSame('bad', $unique['status']);
        $this->assertStringContainsString('Outra página', $unique['message']);
    }

    public function test_rendered_analysis_reads_hierarchy_and_text_from_the_final_html(): void
    {
        $page = $this->page();
        $this->mock(PageRenderer::class, fn ($mock) => $mock->shouldReceive('page')->once()->andReturn(
            '<div><header><nav>Menu com muitas palavras de navegação</nav></header><main id="conteudo">'
            .'<section><h1>Menos planilha no <em>seu dia</em></h1><p>Tiramos tarefas repetidas do time.</p></section>'
            .'<section><h3>Como trabalhamos</h3><p>Mapeamos o processo e integramos os sistemas que você já usa.</p><img src="/a.png"></section>'
            .'<div hidden><h2>Obrigado pelo contato</h2></div>'
            .'</main><footer>Rodapé</footer></div>'
        ));

        $response = $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/seo/analyze/rendered', [
                'kind' => 'page',
                'id' => $page->id,
                'keyword' => 'automação de processos',
                'seo_title' => 'Automação de processos para empresas',
                'slug' => 'automacao-de-processos',
                'sections' => $this->sections(),
            ])
            ->assertOk()
            ->assertJsonPath('rendered', true)
            ->assertJsonPath('words', 23);

        $checks = collect($response->json('checks'))->keyBy('id');

        $this->assertSame('ok', $checks['heading_structure']['status']);
        $this->assertStringContainsString('H1 para H3 em "Como trabalhamos"', $checks['heading_structure']['message']);
        $this->assertSame('bad', $checks['image_alt_missing']['status']);
        $this->assertSame(['type' => 'section', 'index' => 0, 'path' => 'title'], $checks['h1_keyword']['fix']);
        $this->assertSame(['type' => 'section', 'index' => 1, 'path' => 'title'], $checks['subheading_keyword']['fix']);
    }

    public function test_fix_accepts_a_rendered_target_only_when_it_exists_in_the_draft(): void
    {
        $this->fakeAi('Como automatizamos processos');
        $page = $this->page();
        $payload = [
            'kind' => 'page',
            'id' => $page->id,
            'check' => 'subheading_keyword',
            'status' => 'bad',
            'keyword' => 'automação de processos',
            'seo_title' => 'Automação de processos',
            'slug' => 'automacao-de-processos',
            'sections' => $this->sections(),
        ];

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/seo/fix', [...$payload, 'target' => ['type' => 'section', 'index' => 1, 'path' => 'title']])
            ->assertOk()
            ->assertJsonPath('target', ['type' => 'section', 'index' => 1, 'path' => 'title'])
            ->assertJsonPath('value', 'Como automatizamos processos');

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/seo/fix', [...$payload, 'target' => ['type' => 'section', 'index' => 9, 'path' => 'title']])
            ->assertStatus(422);
    }

    public function test_fix_rewrites_the_h1_with_the_whole_page_as_context(): void
    {
        $this->fakeAi('Automação de processos sem planilha');
        $page = $this->page();

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/seo/fix', [
                'kind' => 'page',
                'id' => $page->id,
                'check' => 'h1_keyword',
                'keyword' => 'automação de processos',
                'seo_title' => 'Automação de processos',
                'slug' => 'automacao-de-processos',
                'sections' => $this->sections(),
            ])
            ->assertOk()
            ->assertJsonPath('target', ['type' => 'section', 'index' => 0, 'path' => 'title'])
            ->assertJsonPath('value', 'Automação de processos sem planilha')
            ->assertJsonPath('keyword_used', true);

        Http::assertSent(function (Request $request): bool {
            $body = (string) json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($body, 'integramos os sistemas que você já usa')
                && str_contains($body, '[H1] Menos planilha no seu dia')
                && str_contains($body, 'Palavra-chave principal: automação de processos');
        });
    }

    public function test_seo_description_generation_uses_keyword_and_full_page(): void
    {
        $this->fakeAi('Automação de processos que tira tarefas repetidas do time e integra os sistemas que a empresa já usa, com entrega medida em horas economizadas.');
        $page = $this->page();

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/seo/fix', [
                'kind' => 'page',
                'id' => $page->id,
                'check' => 'description_keyword',
                'keyword' => 'automação de processos',
                'sections' => $this->sections(),
            ])
            ->assertOk()
            ->assertJsonPath('target.type', 'seo_description');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains((string) json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'Mapeamos o processo'));
    }

    public function test_fix_requires_keyword_and_a_pending_check(): void
    {
        Http::fake();
        $page = $this->page();
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->postJson('/gestao/api/seo/fix', ['kind' => 'page', 'id' => $page->id, 'check' => 'h1_keyword', 'sections' => $this->sections()])
            ->assertStatus(422);

        $this->actingAs($editor)
            ->postJson('/gestao/api/seo/fix', ['kind' => 'page', 'id' => $page->id, 'check' => 'slug_keyword', 'keyword' => 'outro termo', 'sections' => $this->sections()])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_lighthouse_needs_a_public_url_and_a_published_case(): void
    {
        config(['cms.lighthouse_url' => 'http://localhost:8080']);
        $page = $this->page();
        $case = CaseStudy::query()->create([
            'slug' => 'digio', 'status' => 'draft', 'client' => 'Digio', 'title' => 'Onboarding',
            'services' => [], 'content' => ['sections' => []],
        ]);
        $editor = $this->makeEditor();

        $this->actingAs($editor)->getJson("/gestao/api/seo/lighthouse/pages/{$page->id}")
            ->assertOk()
            ->assertJsonPath('status', 'unavailable');

        $this->actingAs($editor)->postJson("/gestao/api/seo/lighthouse/cases/{$case->id}")
            ->assertOk()
            ->assertJsonPath('status', 'unavailable');
    }

    public function test_lighthouse_measure_queues_the_page_and_stores_failed_audits(): void
    {
        Queue::fake();
        config(['cms.lighthouse_url' => 'https://quatroventos.com.br']);
        $page = $this->page();

        $this->actingAs($this->makeEditor())->postJson("/gestao/api/seo/lighthouse/pages/{$page->id}")
            ->assertOk()
            ->assertJsonPath('status', 'running')
            ->assertJsonPath('url', 'https://quatroventos.com.br/automacao-de-processos');

        Queue::assertPushed(MeasurePageLighthouseJob::class, fn (MeasurePageLighthouseJob $job) => $job->path === '/automacao-de-processos');

        Http::fake([
            'www.googleapis.com/*' => Http::response(['lighthouseResult' => [
                'categories' => [
                    'performance' => ['score' => 0.72, 'auditRefs' => [['id' => 'largest-contentful-paint', 'weight' => 25, 'group' => 'metrics'], ['id' => 'unused-javascript', 'weight' => 0]]],
                    'seo' => ['score' => 0.9, 'auditRefs' => [['id' => 'meta-description', 'weight' => 1], ['id' => 'document-title', 'weight' => 1]]],
                ],
                'audits' => [
                    'largest-contentful-paint' => ['score' => 0.4, 'numericValue' => 3200, 'scoreDisplayMode' => 'numeric'],
                    'unused-javascript' => ['score' => 0.5, 'title' => 'Reduza o JavaScript não usado', 'displayValue' => '120 KiB', 'scoreDisplayMode' => 'metricSavings'],
                    'meta-description' => ['score' => 0, 'title' => 'O documento não tem uma metadescrição', 'scoreDisplayMode' => 'binary'],
                    'document-title' => ['score' => 1, 'title' => 'Tem título', 'scoreDisplayMode' => 'binary'],
                ],
            ]]),
        ]);

        app(LighthouseService::class)->measurePage('/automacao-de-processos');

        $this->actingAs($this->makeEditor())->getJson("/gestao/api/seo/lighthouse/pages/{$page->id}")
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('mobile.performance', 72)
            ->assertJsonPath('mobile.audits.0.id', 'meta-description')
            ->assertJsonPath('mobile.audits.1.id', 'unused-javascript')
            ->assertJsonCount(2, 'mobile.audits');
    }

    public function test_guest_cannot_analyze(): void
    {
        $this->postJson('/gestao/api/seo/analyze', ['kind' => 'case', 'sections' => []])->assertUnauthorized();
    }
}
