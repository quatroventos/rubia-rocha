<?php

namespace Tests\Feature;

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

    public function test_guest_cannot_use_preview(): void
    {
        $page = $this->page();

        $this->postJson("/gestao/api/preview/pages/{$page->id}", ['content' => ['sections' => []]])->assertUnauthorized();
        $this->get("/gestao/preview/{$page->id}")->assertNotFound();
    }

    public function test_editor_opens_page_preview_with_draft_url(): void
    {
        $page = $this->page();

        $this->actingAs($this->makeEditor())
            ->get("/gestao/preview/{$page->id}")
            ->assertOk()
            ->assertSee('site-root', false)
            ->assertSee('"draftUrl":"\\/gestao\\/api\\/preview\\/pages\\/'.$page->id.'\\/draft"', false);
    }
}
