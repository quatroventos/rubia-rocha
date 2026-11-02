<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Page;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_pages_from_json_and_removes_legacy(): void
    {
        $legacy = Page::query()->create([
            'slug' => 'consultoria-de-ia',
            'template' => 'custom',
            'status' => 'draft',
            'seo_title' => 'Consultoria de IA',
            'content' => ['sections' => []],
        ]);
        MenuItem::query()->create(['group' => 'header', 'label' => 'Consultoria de IA', 'href' => '/consultoria-de-ia', 'page_id' => $legacy->id, 'sort' => 9]);
        MenuItem::query()->create(['group' => 'header', 'label' => 'Quem somos', 'href' => '/quem-somos', 'sort' => 1]);

        $this->seed(SiteContentSeeder::class);

        $this->assertSame(count(glob(database_path('data/pages/*.json'))), Page::query()->count());
        $this->assertDatabaseMissing('pages', ['slug' => 'consultoria-de-ia']);
        $this->assertDatabaseMissing('menu_items', ['href' => '/quem-somos']);

        $this->getJson('/api/pages/hospedagem-de-sites')
            ->assertOk()
            ->assertJsonPath('sections.0.type', 'site.hero');

        $software = MenuItem::query()->where('group', 'header')->whereNull('parent_id')->where('href', '/desenvolvimento-de-software')->firstOrFail();
        $this->assertSame(Page::query()->where('slug', 'desenvolvimento-de-software')->value('id'), $software->page_id);
        $this->assertSame(6, $software->children()->count());
    }

    public function test_admin_page_list_is_grouped_by_menu_section(): void
    {
        $this->seed(SiteContentSeeder::class);
        Page::query()->create(['slug' => 'rascunho-solto', 'template' => 'custom', 'status' => 'published', 'seo_title' => 'Solta', 'content' => ['sections' => []]]);

        $pages = collect($this->actingAs($this->makeEditor())->getJson('/gestao/api/pages')->assertOk()->json('data'));
        $section = $pages->pluck('section', 'slug');

        $this->assertSame('home', $pages->first()['slug']);
        $this->assertSame('Institucional', $section['contato']);
        $this->assertSame('Institucional', $section['diagnostico-de-site']);
        $this->assertSame('Software', $section['desenvolvimento-de-software']);
        $this->assertSame('Software', $section['erp-e-crm']);
        $this->assertSame('Suporte e infraestrutura', $section['hospedagem-de-sites']);
        $this->assertSame('Fora do menu', $section['rascunho-solto']);
        $this->assertSame('rascunho-solto', $pages->last()['slug']);

        $software = $pages->where('section', 'Software')->pluck('slug')->values();
        $this->assertSame(['desenvolvimento-de-software', 'software-sob-medida'], $software->take(2)->all());
    }

    public function test_reseeding_keeps_a_revision_of_edited_page(): void
    {
        $this->seed(SiteContentSeeder::class);

        $page = Page::query()->where('slug', 'sobre')->firstOrFail();
        $page->update(['content' => ['sections' => [['id' => 'x', 'type' => 'site.prose', 'data' => ['body' => 'Editado']]]]]);

        $this->seed(SiteContentSeeder::class);

        $this->assertSame(1, $page->revisions()->count());
        $this->assertSame('Editado', $page->revisions()->first()->snapshot['content']['sections'][0]['data']['body']);
        $this->assertNotSame('site.prose', $page->fresh()->content['sections'][0]['type']);
    }
}
