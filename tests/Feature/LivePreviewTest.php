<?php

namespace Tests\Feature;

use App\Models\CaseStudy;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LivePreviewTest extends TestCase
{
    use RefreshDatabase;

    private function page(): Page
    {
        return Page::query()->create([
            'slug' => 'agentes-de-ia',
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Agentes de IA',
            'content' => ['sections' => [
                ['id' => 'a', 'type' => 'site.prose', 'data' => ['title' => 'Salvo', 'body' => '<p>Texto salvo.</p>']],
            ]],
        ]);
    }

    private function case(): CaseStudy
    {
        return CaseStudy::query()->create([
            'slug' => 'digio',
            'status' => 'published',
            'client' => 'Digio',
            'title' => 'Web app de onboarding',
            'summary' => 'Resumo.',
            'services' => [],
            'content' => ['sections' => []],
            'published_at' => now(),
        ]);
    }

    public function test_page_preview_renders_unsaved_content_without_saving(): void
    {
        $page = $this->page();

        $this->actingAs($this->makeEditor())
            ->postJson("/gestao/api/preview/pages/{$page->id}", [
                'content' => ['sections' => [
                    ['id' => 'a', 'type' => 'site.prose', 'data' => ['title' => 'Rascunho', 'body' => '<p>Texto novo.</p>']],
                ]],
                'seo_title' => 'Agentes de IA',
            ])
            ->assertOk()
            ->assertJsonPath('slug', 'agentes-de-ia')
            ->assertJsonPath('sections.0.data.title', 'Rascunho');

        $this->assertSame('Salvo', $page->fresh()->content['sections'][0]['data']['title']);
    }

    public function test_preview_tab_reads_the_latest_draft_of_the_same_user(): void
    {
        $page = $this->page();
        $editor = $this->makeEditor();

        $this->actingAs($editor)->getJson("/gestao/api/preview/pages/{$page->id}/draft")->assertNoContent();

        $this->actingAs($editor)->postJson("/gestao/api/preview/pages/{$page->id}", [
            'content' => ['sections' => [
                ['id' => 'a', 'type' => 'site.prose', 'data' => ['title' => 'Rascunho', 'body' => '']],
            ]],
        ])->assertOk();

        $version = $this->actingAs($editor)
            ->getJson("/gestao/api/preview/pages/{$page->id}/draft")
            ->assertOk()
            ->assertJsonPath('page.sections.0.data.title', 'Rascunho')
            ->json('version');

        $this->actingAs($editor)->getJson("/gestao/api/preview/pages/{$page->id}/draft?since={$version}")->assertNoContent();
        $this->actingAs($this->makeEditor())->getJson("/gestao/api/preview/pages/{$page->id}/draft")->assertNoContent();
    }

    public function test_case_preview_renders_unsaved_fields_without_saving(): void
    {
        $case = $this->case();

        $this->actingAs($this->makeEditor())
            ->postJson("/gestao/api/preview/cases/{$case->id}", [
                'content' => ['sections' => [
                    ['id' => 'a', 'type' => 'site.prose', 'data' => ['title' => 'Desafio', 'body' => '<p>Novo.</p>']],
                ]],
                'client' => 'Digio',
                'title' => 'Título em edição',
                'tags' => ['SaaS'],
                'services' => [],
            ])
            ->assertOk()
            ->assertJsonPath('case.title', 'Título em edição')
            ->assertJsonPath('sections.0.data.title', 'Desafio');

        $this->assertSame('Web app de onboarding', $case->fresh()->title);
    }

    public function test_guest_cannot_use_preview(): void
    {
        $page = $this->page();
        $case = $this->case();

        $this->postJson("/gestao/api/preview/pages/{$page->id}", ['content' => ['sections' => []]])->assertUnauthorized();
        $this->get("/gestao/preview/cases/{$case->id}")->assertNotFound();
    }

    public function test_editor_opens_case_preview_page(): void
    {
        $case = $this->case();

        $this->actingAs($this->makeEditor())
            ->get("/gestao/preview/cases/{$case->id}")
            ->assertOk()
            ->assertSee('site-root', false);
    }
}
