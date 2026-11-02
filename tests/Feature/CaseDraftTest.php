<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\ImageOptimizationService;
use App\Services\Mockup\DeviceMockupComposer;
use App\Services\Mockup\DeviceSceneGenerator;
use App\Services\Mockup\DeviceScreenshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class CaseDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.key' => 'test-key',
            'services.openai.base_url' => 'https://openai.test/v1',
            'services.openai.queue_connection' => 'sync',
        ]);
    }

    private function fakeSiteWithPrints(): void
    {
        Http::fake([
            'openai.test/*' => Http::response(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'title' => 'Plataforma',
                'summary' => '',
                'tags' => [],
                'seo_title' => '',
                'seo_description' => '',
                'sections' => [
                    $this->section('prose', ['title' => 'Desafio', 'body' => 'Texto.']),
                    $this->section('checklist', ['title' => 'O que entregamos', 'items' => [$this->item(['title' => 'Painel'])]]),
                ],
                'prints' => [
                    ['url' => 'https://93.184.216.34/', 'device' => 'desktop', 'caption' => 'Início', 'alt' => 'Página inicial'],
                    ['url' => 'https://93.184.216.34/produto', 'device' => 'mobile', 'caption' => 'Produto', 'alt' => 'Tela do produto'],
                    ['url' => 'https://outro.test/', 'device' => 'desktop', 'caption' => 'Fora', 'alt' => 'Fora'],
                ],
            ])]]]]]),
            '93.184.216.34/*' => Http::response('<html><body><p>Plataforma de gestão.</p><a href="/produto">Produto</a><a href="https://outro.test/">Fora</a></body></html>'),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    private function fakeOpenAi(array $sections): void
    {
        Http::fake([
            'openai.test/*' => Http::response(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                'client' => 'Fiscal Hub',
                'sector' => 'financas',
                'services' => ['agentes-de-ia', 'inexistente'],
                'year' => '2026',
                'title' => 'Cobrança no WhatsApp com acordo na conversa',
                'summary' => 'Plataforma de cobrança.',
                'tags' => ['Finanças', 'WhatsApp', '2026', 'SaaS', 'Extra'],
                'seo_title' => 'Cobrança no WhatsApp',
                'seo_description' => 'Case de cobrança.',
                'sections' => $sections,
            ])]]]]]),
            '93.184.216.34/*' => Http::response('<html><head><title>Fiscal</title><script>var x = 1;</script></head><body><h1>Cobrança</h1><p>Recupere crédito.</p></body></html>'),
        ]);
    }

    private function section(string $type, array $overrides = []): array
    {
        return array_merge(['type' => $type, 'title' => '', 'intro' => '', 'body' => '', 'items' => []], $overrides);
    }

    private function item(array $overrides = []): array
    {
        return array_merge(['title' => '', 'text' => '', 'value' => '', 'author' => '', 'company' => '', 'layer' => ''], $overrides);
    }

    public function test_editor_gets_a_draft_mapped_to_case_sections(): void
    {
        $this->fakeOpenAi([
            $this->section('prose', ['title' => 'Desafio', 'body' => "Primeiro parágrafo.\n\n- item um\n- item <dois>"]),
            $this->section('checklist', ['title' => 'O que entregamos', 'items' => [$this->item(['title' => 'Chat em tempo real'])]]),
            $this->section('results', ['title' => 'Resultados', 'items' => [$this->item(['value' => '24h', 'text' => 'Janela controlada'])]]),
            $this->section('list', ['title' => 'Stack', 'items' => [
                $this->item(['title' => 'Laravel', 'text' => 'API e regras de negócio', 'layer' => 'aplicacao']),
                $this->item(['title' => 'Kafka', 'layer' => 'inventada']),
            ]]),
        ]);
        Page::query()->create([
            'slug' => 'agentes-de-ia',
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Agentes de IA | Quatro Ventos',
            'content' => ['sections' => []],
        ]);

        $editor = $this->makeEditor();

        $token = $this->actingAs($editor)
            ->postJson('/gestao/api/cases/draft', [
                'prompt' => 'Sistema de cobrança pelo WhatsApp com acordos e boletos.',
                'url' => 'https://93.184.216.34/',
                'case' => ['client' => 'Fiscal'],
            ])
            ->assertStatus(202)
            ->json('token');

        $this->actingAs($editor)
            ->getJson("/gestao/api/cases/draft/{$token}")
            ->assertOk()
            ->assertJsonPath('state', 'done')
            ->assertJsonPath('draft.source_read', true)
            ->assertJsonPath('draft.client', 'Fiscal Hub')
            ->assertJsonPath('draft.sector', 'financas')
            ->assertJsonPath('draft.services', ['agentes-de-ia'])
            ->assertJsonPath('draft.year', 2026)
            ->assertJsonCount(4, 'draft.tags')
            ->assertJsonPath('draft.sections.0.type', 'site.prose')
            ->assertJsonPath('draft.sections.0.data.body', '<p>Primeiro parágrafo.</p><ul><li>item um</li><li>item &lt;dois&gt;</li></ul>')
            ->assertJsonPath('draft.sections.1.data.items', ['Chat em tempo real'])
            ->assertJsonPath('draft.sections.2.type', 'site.results')
            ->assertJsonPath('draft.sections.2.data.items.0.label', 'Janela controlada')
            ->assertJsonPath('draft.sections.3.type', 'site.list')
            ->assertJsonPath('draft.sections.3.data.layout', 'stack')
            ->assertJsonPath('draft.sections.3.data.items.0.layer', 'aplicacao')
            ->assertJsonMissingPath('draft.sections.3.data.items.1.layer');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'openai.test')
            && str_contains((string) json_encode($request->data()), 'Recupere cr')
            && str_contains((string) json_encode($request->data(), JSON_UNESCAPED_SLASHES), 'URL do projeto: https://93.184.216.34/')
            && ! str_contains((string) json_encode($request->data()), 'var x'));
    }

    public function test_url_alone_is_enough_to_generate_the_case(): void
    {
        $this->fakeOpenAi([$this->section('prose', ['title' => 'Desafio', 'body' => 'Texto.'])]);

        $token = $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/cases/draft', ['url' => 'https://93.184.216.34/'])
            ->assertStatus(202)
            ->json('token');

        $this->getJson("/gestao/api/cases/draft/{$token}")->assertJsonPath('state', 'done');

        $this->postJson('/gestao/api/cases/draft', [])->assertJsonValidationErrors(['prompt', 'url']);
    }

    public function test_prints_capture_only_pages_listed_on_the_site(): void
    {
        Storage::fake('public');
        $this->fakeSiteWithPrints();

        $this->mock(DeviceScreenshot::class, function (MockInterface $mock) {
            $mock->shouldReceive('capture')->once()->with('https://93.184.216.34/', 'macbook')->andReturn('png-desktop');
            $mock->shouldReceive('capture')->once()->with('https://93.184.216.34/produto', 'iphone')->andReturn('png-mobile');
        });
        $this->mock(ImageOptimizationService::class, function (MockInterface $mock) {
            $mock->shouldReceive('optimize')->twice();
            $mock->shouldReceive('publicUrlFor')->twice()->andReturnUsing(fn (string $path) => 'http://localhost/storage/optimized/'.str_replace('.png', '.webp', $path));
        });

        $editor = $this->makeEditor();
        $token = $this->actingAs($editor)
            ->postJson('/gestao/api/cases/draft', [
                'prompt' => 'Plataforma de gestão com painel e app.',
                'url' => 'https://93.184.216.34/',
                'prints' => true,
            ])
            ->assertStatus(202)
            ->json('token');

        $draft = $this->getJson("/gestao/api/cases/draft/{$token}")->assertJsonPath('state', 'done')->json('draft');

        $this->assertSame(['site.prose', 'site.gallery', 'site.checklist'], array_column($draft['sections'], 'type'));
        $this->assertSame(0, $draft['prints_failed']);
        $this->assertCount(2, $draft['sections'][1]['data']['items']);
        $this->assertStringStartsWith('/storage/optimized/media/cases/', $draft['sections'][1]['data']['items'][0]['image']);
        $this->assertSame('Tela do produto', $draft['sections'][1]['data']['items'][1]['alt']);
        $this->assertCount(2, Storage::disk('public')->files('media/cases'));
    }

    public function test_mockups_wrap_prints_and_fall_back_to_the_plain_print(): void
    {
        config(['services.meta.key' => 'meta-key']);
        Storage::fake('public');
        $this->fakeSiteWithPrints();

        $this->mock(DeviceScreenshot::class, function (MockInterface $mock) {
            $mock->shouldReceive('capture')->with('https://93.184.216.34/', 'macbook')->andReturn('png-desktop');
            $mock->shouldReceive('capture')->with('https://93.184.216.34/produto', 'iphone')->andReturn('png-mobile');
        });
        $this->mock(DeviceSceneGenerator::class, function (MockInterface $mock) {
            $mock->shouldReceive('generate')->once()->withArgs(fn (array $input) => $input['device'] === 'macbook' && $input['size'] === '2048x1152')->andReturn('scene-macbook');
            $mock->shouldReceive('generate')->twice()->withArgs(fn (array $input) => $input['device'] === 'iphone')->andReturn('scene-iphone');
        });
        $this->mock(DeviceMockupComposer::class, function (MockInterface $mock) {
            $mock->shouldReceive('compose')->once()->with('scene-macbook', 'png-desktop')->andReturn('mockup-desktop');
            $mock->shouldReceive('compose')->twice()->with('scene-iphone', 'png-mobile')->andReturn(null);
        });

        $token = $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/cases/draft', [
                'prompt' => 'Plataforma de gestão com painel e app.',
                'url' => 'https://93.184.216.34/',
                'prints' => true,
                'mockups' => true,
            ])
            ->assertStatus(202)
            ->json('token');

        $draft = $this->getJson("/gestao/api/cases/draft/{$token}")->assertJsonPath('state', 'done')->json('draft');
        $items = $draft['sections'][1]['data']['items'];

        $this->assertSame(1, $draft['mockups']);
        $this->assertSame(1, $draft['mockups_failed']);
        $this->assertSame(['mockup-desktop', 'png-mobile'], array_map(fn (array $item) => Storage::disk('public')->get(ltrim(str_replace('/storage/', '', $item['image']), '/')), $items));
        $this->assertSame(['path' => $items[0]['image'], 'alt' => 'Página inicial'], $draft['cover']);
    }

    public function test_mockups_are_skipped_without_meta_key(): void
    {
        config(['services.meta.key' => null]);
        Storage::fake('public');
        $this->fakeSiteWithPrints();

        $this->mock(DeviceScreenshot::class, fn (MockInterface $mock) => $mock->shouldReceive('capture')->twice()->andReturn('png'));
        $this->mock(DeviceSceneGenerator::class, fn (MockInterface $mock) => $mock->shouldNotReceive('generate'));

        $token = $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/cases/draft', [
                'prompt' => 'Plataforma de gestão com painel e app.',
                'url' => 'https://93.184.216.34/',
                'prints' => true,
                'mockups' => true,
            ])
            ->json('token');

        $this->getJson("/gestao/api/cases/draft/{$token}")
            ->assertJsonPath('draft.mockups_unavailable', true)
            ->assertJsonPath('draft.cover', null)
            ->assertJsonCount(2, 'draft.sections.1.data.items');
    }

    public function test_private_hosts_are_not_read(): void
    {
        $this->fakeOpenAi([]);

        $token = $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/cases/draft', [
                'prompt' => 'Sistema interno de cobrança com acordos.',
                'url' => 'http://127.0.0.1/admin',
            ])
            ->assertStatus(202)
            ->json('token');

        $this->getJson("/gestao/api/cases/draft/{$token}")->assertJsonPath('draft.source_read', false);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '127.0.0.1'));
    }

    public function test_draft_status_is_private_to_its_author(): void
    {
        $this->fakeOpenAi([]);

        $token = $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/cases/draft', ['prompt' => 'Sistema interno de cobrança com acordos.'])
            ->json('token');

        $this->actingAs($this->makeEditor(['email' => 'outro@example.com']))
            ->getJson("/gestao/api/cases/draft/{$token}")
            ->assertNotFound();
    }

    public function test_draft_requires_openai_key(): void
    {
        config(['services.openai.key' => null]);

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/cases/draft', ['prompt' => 'Sistema interno de cobrança com acordos.'])
            ->assertStatus(422);
    }
}
