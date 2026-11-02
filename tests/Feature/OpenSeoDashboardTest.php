<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\Seo\OpenSeoClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenSeoDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSetting::query()->create(['name' => 'Quatro Ventos', 'og_locale' => 'pt_BR', 'twitter_card' => 'summary_large_image']);

        config([
            'services.openseo.key' => null,
            'services.openseo.url' => 'http://openseo.test/mcp',
            'services.openseo.host_header' => '127.0.0.1:3002',
            'services.openseo.project' => 'proj_1',
            'services.openseo.site_url' => 'https://quatroventos.com.br',
            'services.openseo.paid' => true,
        ]);
    }

    /**
     * Simula o MCP self-hosted: cada ferramenta devolve o payload informado em structuredContent.
     *
     * @param  array<string, array<string, mixed>>  $tools
     */
    private function fakeOpenSeo(array $tools): void
    {
        Http::fake([
            'openseo.test/*' => function (Request $request) use ($tools) {
                $method = $request['method'] ?? null;

                if ($method === 'initialize') {
                    return Http::response(['jsonrpc' => '2.0', 'id' => $request['id'], 'result' => ['protocolVersion' => '2025-06-18']]);
                }

                if ($method !== 'tools/call') {
                    return Http::response('', 202);
                }

                $payload = $tools[$request['params']['name']] ?? ['status' => 'error', 'error' => ['code' => 'unknown_tool', 'message' => 'tool não simulada']];

                return Http::response(['jsonrpc' => '2.0', 'id' => $request['id'], 'result' => ['content' => [['type' => 'text', 'text' => 'ok']], 'structuredContent' => $payload]]);
            },
        ]);
    }

    private function toolCalls(string $tool): int
    {
        return Http::recorded(fn (Request $request) => ($request['method'] ?? null) === 'tools/call' && $request['params']['name'] === $tool)->count();
    }

    public function test_audit_crawls_the_audit_url_when_set(): void
    {
        config(['services.openseo.audit_url' => 'https://qv-local.ngrok-free.app/']);
        $this->fakeOpenSeo([
            'run_site_audit' => ['status' => 'ok'],
            'get_audit_status' => ['status' => null, 'meta' => ['url' => 'http://127.0.0.1:3002/p/proj_1/audit']],
        ]);

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/seo/openseo/audit', ['max_pages' => 50])
            ->assertOk()
            ->assertJsonPath('site_url', 'https://quatroventos.com.br');

        Http::assertSent(fn (Request $request) => ($request['params']['name'] ?? null) === 'run_site_audit'
            && $request['params']['arguments']['url'] === 'https://qv-local.ngrok-free.app/');
    }

    public function test_audit_is_summarized_in_portuguese_and_served_from_cache_until_refresh(): void
    {
        $this->fakeOpenSeo([
            'get_audit_status' => ['status' => ['id' => 'a1', 'startUrl' => 'https://quatroventos.com.br/', 'status' => 'completed', 'pagesCrawled' => 35, 'pagesTotal' => 35, 'currentPhase' => 'completed', 'completedAt' => '2026-10-21T12:51:39Z'], 'meta' => ['url' => 'http://127.0.0.1:3002/p/proj_1/audit']],
            'get_audit_issues' => [
                'summary' => [
                    ['issueType' => 'duplicate-title', 'title' => 'Duplicate title', 'severity' => 'warning', 'count' => 2],
                    ['issueType' => 'broken-internal-link', 'title' => 'Broken internal link', 'severity' => 'critical', 'count' => 1],
                ],
                'issues' => [
                    ['severity' => 'warning', 'issueType' => 'duplicate-title', 'url' => 'https://quatroventos.com.br/sites', 'details' => ['groupSize' => 2, 'otherUrls' => ['https://www.quatroventos.com.br/sites']]],
                    ['severity' => 'critical', 'issueType' => 'broken-internal-link', 'url' => 'https://quatroventos.com.br/en/index', 'details' => ['targetUrl' => 'https://quatroventos.com.br/en/x', 'targetStatus' => 404]],
                ],
            ],
        ]);
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->getJson('/gestao/api/seo/openseo/audit')
            ->assertOk()
            ->assertJsonPath('state', 'ready')
            ->assertJsonPath('counts', ['critical' => 1, 'warning' => 2, 'info' => 0])
            ->assertJsonPath('issues.0.type', 'broken-internal-link')
            ->assertJsonPath('issues.0.title', 'Link interno quebrado')
            ->assertJsonPath('issues.0.pages.0.detail', 'Aponta para https://quatroventos.com.br/en/x (404)')
            ->assertJsonPath('issues.1.pages.0.detail', 'Igual a https://www.quatroventos.com.br/sites');

        $this->actingAs($editor)->getJson('/gestao/api/seo/openseo/audit')->assertOk();
        $this->assertSame(1, $this->toolCalls('get_audit_status'));

        $this->actingAs($editor)->getJson('/gestao/api/seo/openseo/audit?fresh=1')->assertOk();
        $this->assertSame(2, $this->toolCalls('get_audit_status'));

        Http::assertSent(fn (Request $request) => $request->hasHeader('Host', '127.0.0.1:3002') && ! $request->hasHeader('Authorization'));
    }

    public function test_disconnected_search_console_shows_how_to_fix_instead_of_failing(): void
    {
        $this->fakeOpenSeo([
            'get_search_console_performance' => ['ok' => false, 'reason' => 'api_error', 'connectUrl' => 'http://127.0.0.1:3002/p/proj_1/search-performance'],
        ]);

        $this->actingAs($this->makeEditor())
            ->getJson('/gestao/api/seo/openseo/search-console?range=last_7_days')
            ->assertOk()
            ->assertJsonPath('state', 'unavailable')
            ->assertJsonPath('action_url', 'http://127.0.0.1:3002/p/proj_1/search-performance')
            ->assertJsonPath('message', 'O Google devolveu erro ao OpenSEO. Reconecte o Search Console nas integrações do projeto.');
    }

    public function test_search_console_totals_weight_position_by_impressions(): void
    {
        $this->fakeOpenSeo([
            'get_search_console_performance' => ['rows' => [
                ['keys' => ['2026-10-01'], 'clicks' => 10, 'impressions' => 100, 'ctr' => 0.1, 'position' => 4],
                ['keys' => ['2026-10-02'], 'clicks' => 0, 'impressions' => 300, 'ctr' => 0, 'position' => 12],
            ]],
        ]);

        $this->actingAs($this->makeEditor())
            ->getJson('/gestao/api/seo/openseo/search-console')
            ->assertOk()
            ->assertJsonPath('state', 'ready')
            ->assertJsonPath('totals.clicks', 10)
            ->assertJsonPath('totals.impressions', 400)
            ->assertJsonPath('totals.ctr', 0.025)
            ->assertJsonPath('totals.position', 10);
    }

    public function test_researched_keywords_are_saved_with_their_metrics(): void
    {
        $this->fakeOpenSeo([
            'research_keywords' => ['results' => [['seed' => 'criação de sites', 'keywords' => [
                ['keyword' => 'criação de sites curitiba', 'searchVolume' => 320, 'keywordDifficulty' => 25, 'intent' => 'commercial', 'cpc' => 3.1],
                ['keyword' => 'criação de sites', 'searchVolume' => 2400, 'keywordDifficulty' => 61, 'intent' => 'commercial'],
            ]]]],
            'save_keywords' => ['saved' => 1],
            'list_saved_keywords' => ['rows' => [['id' => 'k1', 'keyword' => 'criação de sites curitiba', 'searchVolume' => 320, 'keywordDifficulty' => 25, 'intent' => 'commercial', 'tags' => []]]],
        ]);
        $editor = $this->makeEditor();

        $rows = $this->actingAs($editor)
            ->postJson('/gestao/api/seo/openseo/keywords/research', ['seed' => 'criação de sites'])
            ->assertOk()
            ->assertJsonPath('rows.0.keyword', 'criação de sites')
            ->assertJsonPath('rows.1.intent', 'comercial')
            ->json('rows');

        $this->actingAs($editor)
            ->postJson('/gestao/api/seo/openseo/keywords', ['keywords' => [$rows[1]]])
            ->assertOk()
            ->assertJsonPath('rows.0.id', 'k1');

        Http::assertSent(fn (Request $request) => ($request['params']['name'] ?? null) === 'save_keywords'
            && $request['params']['arguments']['keywords'] === ['criação de sites curitiba']
            && $request['params']['arguments']['metrics'][0] === ['keyword' => 'criação de sites curitiba', 'searchVolume' => 320, 'keywordDifficulty' => 25, 'cpc' => 3.1, 'intent' => 'commercial']);
    }

    public function test_domain_overview_only_spends_credits_on_explicit_request(): void
    {
        $this->fakeOpenSeo([
            'get_domain_overview' => ['overview' => ['organicTraffic' => 1520, 'organicKeywords' => 340, 'backlinks' => 2100, 'referringDomains' => 85]],
        ]);
        $editor = $this->makeEditor();

        $this->actingAs($editor)->getJson('/gestao/api/seo/openseo/domain')->assertOk()->assertJsonPath('state', 'idle');
        $this->assertSame(0, $this->toolCalls('get_domain_overview'));

        $this->actingAs($editor)
            ->postJson('/gestao/api/seo/openseo/domain')
            ->assertOk()
            ->assertJsonPath('metrics', ['traffic' => 1520, 'keywords' => 340, 'backlinks' => 2100, 'referring_domains' => 85]);

        $this->actingAs($editor)->getJson('/gestao/api/seo/openseo/domain')->assertOk()->assertJsonPath('metrics.traffic', 1520);
        $this->assertSame(1, $this->toolCalls('get_domain_overview'));
    }

    public function test_paid_queries_are_blocked_when_disabled(): void
    {
        config(['services.openseo.paid' => false]);
        $this->fakeOpenSeo([]);
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->postJson('/gestao/api/seo/openseo/keywords/research', ['seed' => 'criação de sites'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Consultas pagas do OpenSEO (DataForSEO) estão desligadas em SEO > Integrações.');

        $this->actingAs($editor)->postJson('/gestao/api/seo/openseo/domain')->assertStatus(422);

        $this->assertSame(0, $this->toolCalls('research_keywords'));
        $this->assertSame(0, $this->toolCalls('get_domain_overview'));
    }

    public function test_tool_without_arguments_sends_an_empty_object(): void
    {
        $this->fakeOpenSeo(['list_projects' => ['projects' => []]]);

        app(OpenSeoClient::class)->call('list_projects', [], false);

        Http::assertSent(fn (Request $request) => ($request['method'] ?? null) === 'tools/call'
            && str_contains($request->body(), '"arguments":{}'));
    }

    public function test_without_project_the_screen_reports_setup_and_never_calls_openseo(): void
    {
        config(['services.openseo.project' => null]);
        Http::fake();

        $this->actingAs($this->makeEditor())
            ->getJson('/gestao/api/seo/openseo')
            ->assertOk()
            ->assertJsonPath('configured', false);

        Http::assertNothingSent();
    }
}
