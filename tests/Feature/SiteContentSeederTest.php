<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_rubia_pages_settings_and_menu_removing_legacy(): void
    {
        SiteSetting::query()->create(['name' => 'Quatro Ventos']);
        $legacy = Page::query()->create([
            'slug' => 'consultoria-de-ia',
            'template' => 'custom',
            'status' => 'published',
            'seo_title' => 'Consultoria de IA',
            'content' => ['sections' => []],
        ]);
        MenuItem::query()->create(['group' => 'header', 'label' => 'Consultoria de IA', 'href' => '/consultoria-de-ia', 'page_id' => $legacy->id, 'sort' => 9]);
        MenuItem::query()->create(['group' => 'footer_1', 'label' => 'Quem somos', 'href' => '/quem-somos', 'sort' => 1]);

        $this->seed(SiteContentSeeder::class);

        $this->assertSame(count(glob(database_path('data/pages/*.json'))), Page::query()->count());
        $this->assertDatabaseMissing('pages', ['slug' => 'consultoria-de-ia']);
        $this->assertDatabaseMissing('menu_items', ['href' => '/quem-somos']);
        $this->assertDatabaseHas('pages', ['slug' => 'ensaios/gestante', 'status' => 'published']);

        $settings = SiteSetting::query()->findOrFail(1);
        $this->assertSame('Rubia Rocha Fotografia', $settings->name);
        $this->assertSame('https://wa.me/5541988658624', $settings->whatsapp_url);
        $this->assertCount(count(SiteContentSeeder::FOOTER_GALLERY), $settings->footer_gallery);

        $header = MenuItem::query()->where('group', 'header')->orderBy('sort')->get();
        $this->assertSame(['/', '/quem-sou', '/ensaios', '/eventos', '/contato'], $header->pluck('href')->all());
        $this->assertSame(Page::query()->where('slug', 'ensaios')->value('id'), $header[2]->page_id);
    }

    public function test_admin_page_list_groups_nested_pages_under_their_menu_item(): void
    {
        $this->seed(SiteContentSeeder::class);
        Page::query()->create(['slug' => 'rascunho-solto', 'template' => 'custom', 'status' => 'draft', 'seo_title' => 'Solta', 'content' => ['sections' => []]]);

        $pages = collect($this->actingAs($this->makeEditor())->getJson('/gestao/api/pages')->assertOk()->json('data'));
        $section = $pages->pluck('section', 'slug');

        $this->assertSame('home', $pages->first()['slug']);
        $this->assertSame('Institucional', $section['quem-sou']);
        $this->assertSame('Institucional', $section['contato']);
        $this->assertSame('Ensaios', $section['ensaios']);
        $this->assertSame('Ensaios', $section['ensaios/gestante']);
        $this->assertSame('Eventos', $section['eventos/mini-wedding']);
        $this->assertSame('Fora do menu', $section['rascunho-solto']);
        $this->assertSame('rascunho-solto', $pages->last()['slug']);
    }

    public function test_reseeding_preserves_cms_edits(): void
    {
        $this->seed(SiteContentSeeder::class);

        $page = Page::query()->where('slug', 'quem-sou')->firstOrFail();
        $page->update(['content' => ['sections' => [['id' => 'x', 'type' => 'site.prose', 'data' => ['body' => 'Editado']]]]]);
        SiteSetting::query()->whereKey(1)->update(['phone' => '41 0000-0000']);
        MenuItem::query()->create(['group' => 'header', 'label' => 'Blog', 'href' => 'https://blog.example.com', 'sort' => 9, 'is_active' => true]);

        $this->seed(SiteContentSeeder::class);

        $this->assertSame('Editado', $page->fresh()->content['sections'][0]['data']['body']);
        $this->assertSame('41 0000-0000', SiteSetting::query()->findOrFail(1)->phone);
        $this->assertDatabaseHas('menu_items', ['label' => 'Blog']);
    }

    public function test_reseeding_replaces_legacy_content_keeping_a_revision(): void
    {
        $page = Page::query()->create([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'published',
            'seo_title' => 'Quatro Ventos',
            'content' => ['sections' => [['id' => 'h', 'type' => 'site.panel', 'data' => ['title' => 'Antigo']]]],
        ]);

        $this->seed(SiteContentSeeder::class);

        $this->assertSame(1, $page->revisions()->count());
        $this->assertSame('site.panel', $page->revisions()->first()->snapshot['content']['sections'][0]['type']);
        $this->assertNotSame('site.panel', $page->fresh()->content['sections'][0]['type']);
    }
}
