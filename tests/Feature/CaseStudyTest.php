<?php

namespace Tests\Feature;

use App\Jobs\PrerenderSiteJob;
use App\Models\CaseStudy;
use App\Models\Page;
use Database\Seeders\CaseStudySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CaseStudyTest extends TestCase
{
    use RefreshDatabase;

    private function servicePage(string $slug, array $sections = []): Page
    {
        return Page::query()->create([
            'slug' => $slug,
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Serviço '.$slug.' | Quatro Ventos',
            'content' => ['sections' => $sections],
        ]);
    }

    private function makeCase(array $overrides = []): CaseStudy
    {
        return CaseStudy::query()->create(array_merge([
            'slug' => 'digio',
            'status' => 'published',
            'client' => 'Digio',
            'title' => 'Web app de onboarding',
            'summary' => 'Resumo do case.',
            'sector' => 'financas',
            'services' => ['software-sob-medida'],
            'cover_path' => 'https://cdn.test/capa.png',
            'content' => ['sections' => [
                ['id' => 'a', 'type' => 'site.prose', 'data' => ['title' => 'Desafio', 'body' => '<p>Desafio</p>']],
                ['id' => 'b', 'type' => 'site.gallery', 'data' => ['title' => 'Prints', 'items' => [
                    ['image' => 'https://cdn.test/1.png', 'caption' => 'Tela 1'],
                    ['image' => 'https://cdn.test/2.png', 'caption' => 'Tela 2'],
                    ['image' => 'https://cdn.test/3.png', 'caption' => 'Tela 3'],
                    ['image' => 'https://cdn.test/4.png', 'caption' => 'Tela 4'],
                ]]],
            ]],
            'published_at' => now(),
        ], $overrides));
    }

    public function test_editor_can_create_and_update_case(): void
    {
        $this->servicePage('software-sob-medida');
        $editor = $this->makeEditor();

        $id = $this->actingAs($editor)
            ->postJson('/gestao/api/cases', [
                'slug' => 'digio',
                'status' => 'published',
                'client' => 'Digio',
                'title' => 'Web app de onboarding',
                'services' => ['software-sob-medida', ''],
                'content' => ['sections' => [
                    ['id' => 'a', 'type' => 'site.prose', 'data' => ['title' => 'Solução', 'body' => '<p>Painel</p>']],
                    ['id' => 'b', 'type' => 'site.results', 'data' => ['items' => [['value' => '24h', 'label' => 'Janela']]]],
                ]],
                'featured' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.services', ['software-sob-medida'])
            ->assertJsonPath('data.content.sections.0.data.title', 'Solução')
            ->assertJsonPath('data.content.sections.1.type', 'site.results')
            ->json('data.id');

        $this->actingAs($editor)
            ->getJson("/gestao/api/cases/{$id}")
            ->assertOk()
            ->assertJsonCount(2, 'data.content.sections')
            ->assertJsonMissing(['type' => 'site.hero']);

        $this->assertNotNull(CaseStudy::query()->find($id)?->published_at);

        $this->actingAs($editor)
            ->getJson('/gestao/api/cases')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'digio')
            ->assertJsonPath('services.0.value', 'software-sob-medida');

        $this->actingAs($editor)
            ->putJson("/gestao/api/cases/{$id}", [
                'slug' => 'digio',
                'status' => 'draft',
                'client' => 'Digio',
                'title' => 'Novo título',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Novo título')
            ->assertJsonCount(2, 'data.content.sections');
    }

    public function test_case_rejects_sections_reserved_to_pages(): void
    {
        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/cases', [
                'slug' => 'digio',
                'status' => 'draft',
                'client' => 'Digio',
                'title' => 'Web app',
                'content' => ['sections' => [
                    ['id' => 'a', 'type' => 'site.hero', 'data' => ['title' => 'Hero']],
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('content.sections.0.type');
    }

    public function test_case_slug_must_be_unique(): void
    {
        $this->makeCase();

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/cases', [
                'slug' => 'digio',
                'status' => 'draft',
                'client' => 'Outro',
                'title' => 'Outro',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_case_page_renders_with_seo_and_payload(): void
    {
        $this->servicePage('software-sob-medida');
        $this->makeCase();

        $html = $this->get('/cases/digio')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Web app de onboarding | Case Digio | Quatro Ventos</title>', $html);
        $this->assertStringContainsString('content="Resumo do case."', $html);
        $this->assertStringContainsString('https://cdn.test/capa.png', $html);
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('BreadcrumbList', $html);
    }

    public function test_site_payload_lists_published_cases_by_slug(): void
    {
        $this->makeCase();
        $this->makeCase(['slug' => 'rascunho', 'status' => 'draft', 'published_at' => null]);

        $this->getJson('/api/site')
            ->assertOk()
            ->assertJsonPath('cases.digio.client', 'Digio')
            ->assertJsonPath('cases.digio.cover.src', 'https://cdn.test/capa.png')
            ->assertJsonPath('cases.digio.path', '/cases/digio')
            ->assertJsonMissingPath('cases.rascunho');
    }

    public function test_saving_content_schedules_one_prerender(): void
    {
        config(['cms.prerender_on_save' => true]);
        Queue::fake();

        $case = $this->makeCase();
        $case->update(['title' => 'Outro título']);
        $this->servicePage('software-sob-medida');

        Queue::assertPushed(PrerenderSiteJob::class, 1);
    }

    public function test_draft_case_is_not_public(): void
    {
        $this->makeCase(['status' => 'draft']);

        $this->get('/cases/digio')->assertNotFound();
        $this->getJson('/api/cases/digio')->assertNotFound();
        $this->getJson('/api/cases')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_cases_api_filters_by_service(): void
    {
        $this->servicePage('software-sob-medida');
        $this->makeCase();
        $this->makeCase(['slug' => 'ivorecap', 'client' => 'Ivorecap', 'services' => ['desenvolvimento-de-ecommerce']]);

        $this->getJson('/api/cases')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/cases?servico=software-sob-medida')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'digio')
            ->assertJsonCount(3, 'data.0.gallery')
            ->assertJsonPath('data.0.services.0.label', 'Serviço software-sob-medida');

        $this->getJson('/api/cases/digio')
            ->assertOk()
            ->assertJsonPath('template', 'case')
            ->assertJsonPath('case.next.slug', 'ivorecap');
    }

    public function test_case_page_payload_carries_its_sections(): void
    {
        $this->makeCase();

        $this->getJson('/api/cases/digio')
            ->assertOk()
            ->assertJsonCount(2, 'sections')
            ->assertJsonPath('sections.0.type', 'site.prose')
            ->assertJsonPath('sections.0.data.body', '<p>Desafio</p>')
            ->assertJsonPath('sections.1.type', 'site.gallery')
            ->assertJsonCount(4, 'sections.1.data.items');
    }

    public function test_service_page_resolves_selected_and_automatic_cases(): void
    {
        $this->makeCase();
        $this->makeCase(['slug' => 'dialogos', 'client' => 'Diálogos de Carreira']);
        $this->makeCase(['slug' => 'rascunho', 'client' => 'Rascunho', 'status' => 'draft']);

        $this->servicePage('software-sob-medida', [
            ['id' => 'a', 'type' => 'site.cases', 'data' => ['items' => ['dialogos', 'rascunho'], 'layout' => 'par']],
            ['id' => 'b', 'type' => 'site.cases', 'data' => ['layout' => 'destaque']],
        ]);

        $this->getJson('/api/pages/software-sob-medida')
            ->assertOk()
            ->assertJsonCount(1, 'sections.0.data.cases')
            ->assertJsonPath('sections.0.data.cases.0.slug', 'dialogos')
            ->assertJsonCount(1, 'sections.1.data.cases')
            ->assertJsonPath('sections.1.data.layout', 'destaque');
    }

    public function test_cases_list_section_returns_all_published(): void
    {
        $this->makeCase();
        $this->makeCase(['slug' => 'ivorecap', 'client' => 'Ivorecap', 'featured' => true]);

        $this->servicePage('cases', [
            ['id' => 'a', 'type' => 'site.cases-list', 'data' => ['filter' => true]],
        ]);

        $this->getJson('/api/pages/cases')
            ->assertOk()
            ->assertJsonCount(2, 'sections.0.data.cases')
            ->assertJsonPath('sections.0.data.cases.0.slug', 'ivorecap');
    }

    public function test_showcase_and_case_grid_keep_chosen_order_and_skip_drafts(): void
    {
        $this->makeCase(['tags' => ['Webapp', '2025'], 'cover_ratio' => '16x9']);
        $this->makeCase(['slug' => 'dialogos', 'client' => 'Diálogos de Carreira']);
        $this->makeCase(['slug' => 'rascunho', 'client' => 'Rascunho', 'status' => 'draft']);

        $this->servicePage('desenvolvimento-de-saas', [
            ['id' => 'a', 'type' => 'site.showcase', 'data' => ['items' => ['dialogos', 'rascunho', 'digio']]],
            ['id' => 'b', 'type' => 'site.case-grid', 'data' => ['featured' => ['digio'], 'items' => ['rascunho', 'dialogos']]],
        ]);

        $this->getJson('/api/pages/desenvolvimento-de-saas')
            ->assertOk()
            ->assertJsonCount(2, 'sections.0.data.cases')
            ->assertJsonPath('sections.0.data.cases.0.slug', 'dialogos')
            ->assertJsonPath('sections.0.data.cases.1.tags', ['Webapp', '2025'])
            ->assertJsonPath('sections.1.data.featured_cases.0.cover_ratio', '16x9')
            ->assertJsonCount(1, 'sections.1.data.cases')
            ->assertJsonPath('sections.1.data.cases.0.slug', 'dialogos');
    }

    public function test_showcase_cases_expose_only_http_external_url(): void
    {
        $this->makeCase(['url' => 'https://digio.example.com/']);
        $this->makeCase(['slug' => 'dialogos', 'client' => 'Diálogos de Carreira', 'url' => 'javascript:alert(1)']);

        $this->servicePage('desenvolvimento-de-saas', [
            ['id' => 'a', 'type' => 'site.showcase', 'data' => ['items' => ['digio', 'dialogos'], 'link_to' => 'url', 'cta_label' => 'Ver o produto']],
        ]);

        $this->getJson('/api/pages/desenvolvimento-de-saas')
            ->assertOk()
            ->assertJsonPath('sections.0.data.cases.0.url', 'https://digio.example.com/')
            ->assertJsonPath('sections.0.data.cases.1.url', null)
            ->assertJsonPath('sections.0.data.link_to', 'url');
    }

    public function test_cases_browser_assigns_first_matching_category(): void
    {
        $this->makeCase(['services' => ['agentes-de-ia', 'software-sob-medida']]);
        $this->makeCase(['slug' => 'dialogos', 'client' => 'Diálogos de Carreira', 'services' => ['sites-wordpress']]);
        $this->makeCase(['slug' => 'avulso', 'client' => 'Avulso', 'services' => []]);

        $this->servicePage('cases', [
            ['id' => 'a', 'type' => 'site.cases-browser', 'data' => ['categories' => [
                ['id' => 'ia', 'label' => 'IA', 'services' => ['agentes-de-ia']],
                ['id' => 'software', 'label' => 'Software', 'services' => ['software-sob-medida']],
                ['id' => 'web', 'label' => 'Web', 'services' => ['sites-wordpress']],
            ]]],
        ]);

        $cases = collect($this->getJson('/api/pages/cases')->assertOk()->json('sections.0.data.cases'))
            ->pluck('category', 'slug');

        $this->assertSame('ia', $cases['digio']);
        $this->assertSame('web', $cases['dialogos']);
        $this->assertSame('', $cases['avulso']);
    }

    public function test_admin_page_editor_keeps_site_sections(): void
    {
        $page = $this->servicePage('software-sob-medida', [
            ['id' => 'a', 'type' => 'site.hero', 'data' => ['title' => 'Hero']],
            ['id' => 'b', 'type' => 'site.cases', 'data' => ['items' => ['digio']]],
        ]);

        $this->actingAs($this->makeEditor())
            ->getJson("/gestao/api/pages/{$page->slug}")
            ->assertOk()
            ->assertJsonCount(2, 'data.content.sections')
            ->assertJsonPath('schema.1.type', 'site.cases');

        $this->actingAs($this->makeEditor())
            ->putJson("/gestao/api/pages/{$page->slug}", [
                'seo_title' => 'Software',
                'content' => ['sections' => [
                    ['id' => 'a', 'type' => 'site.hero', 'data' => ['title' => 'Hero']],
                    ['id' => 'b', 'type' => 'site.cases', 'data' => ['items' => ['digio'], 'layout' => 'destaque']],
                ]],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.content.sections');
    }

    public function test_sitemap_lists_published_cases(): void
    {
        $this->makeCase();
        $this->makeCase(['slug' => 'rascunho', 'status' => 'draft']);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString(url('/cases/digio'), $xml);
        $this->assertStringNotContainsString(url('/cases/rascunho'), $xml);
    }

    public function test_seeder_publishes_cases_with_text_and_keeps_panel_edits(): void
    {
        $this->seed(CaseStudySeeder::class);

        $this->get('/cases/seucommerce')->assertOk();
        $this->getJson('/api/cases/seucommerce')->assertOk()->assertSee('seucommerce.com.br', false);
        $this->get('/cases/tripfy')->assertNotFound();

        CaseStudy::query()->where('slug', 'seucommerce')->update(['title' => 'Editado no painel']);
        $this->seed(CaseStudySeeder::class);

        $this->assertSame('Editado no painel', CaseStudy::query()->where('slug', 'seucommerce')->value('title'));
        $this->assertSame(13, CaseStudy::query()->count());
    }
}
