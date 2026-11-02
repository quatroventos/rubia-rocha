<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsPageRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_revisions(): void
    {
        $this->makePage();

        $this->getJson('/gestao/api/pages/home/revisions')
            ->assertStatus(401);
    }

    public function test_save_creates_revision_and_editor_can_restore(): void
    {
        $this->makeSettings();
        $page = $this->makePage([
            'seo_title' => 'Home original',
            'content' => ['hero' => ['subtitle' => 'antes']],
        ]);

        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->putJson('/gestao/api/pages/home', [
                'status' => 'published',
                'seo_title' => 'Home nova',
                'content' => ['hero' => ['subtitle' => 'depois']],
            ])
            ->assertOk()
            ->assertJsonPath('data.seo_title', 'Home nova');

        $this->assertDatabaseCount('page_revisions', 1);

        $revision = PageRevision::query()->firstOrFail();
        $this->assertSame('Home original', $revision->snapshot['seo_title']);
        $this->assertSame('antes', $revision->snapshot['content']['hero']['subtitle']);

        $this->actingAs($editor)
            ->getJson('/gestao/api/pages/home/revisions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $revision->id);

        $this->actingAs($editor)
            ->postJson("/gestao/api/pages/home/revisions/{$revision->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.seo_title', 'Home original')
            ->assertJsonPath('data.content.hero.subtitle', 'antes');

        $this->assertSame('Home original', $page->fresh()->seo_title);
        $this->assertDatabaseCount('page_revisions', 2);
    }

    public function test_unchanged_save_does_not_create_revision(): void
    {
        $this->makePage([
            'seo_title' => 'Home',
            'content' => ['hero' => ['subtitle' => 'ok']],
        ]);

        $this->actingAs($this->makeEditor())
            ->putJson('/gestao/api/pages/home', [
                'status' => 'published',
                'seo_title' => 'Home',
                'content' => ['hero' => ['subtitle' => 'ok']],
            ])
            ->assertOk();

        $this->assertDatabaseCount('page_revisions', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSettings(array $overrides = []): SiteSetting
    {
        return SiteSetting::query()->create(array_merge([
            'name' => 'Quatro Ventos',
            'og_locale' => 'pt_BR',
            'twitter_card' => 'summary_large_image',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePage(array $overrides = []): Page
    {
        return Page::query()->create(array_merge([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'published',
            'seo_title' => 'Home',
            'content' => [],
            'published_at' => now(),
        ], $overrides));
    }
}
