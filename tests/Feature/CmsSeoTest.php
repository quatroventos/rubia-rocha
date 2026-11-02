<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SiteSetting;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_view_seo(): void
    {
        $this->makeSettings();

        $this->actingAs($this->makeEditor())
            ->getJson('/gestao/api/seo')
            ->assertOk();
    }

    public function test_admin_can_view_and_update_seo(): void
    {
        $this->makeSettings();
        $this->makePage('home', 'Home');

        $this->actingAs($this->makeAdmin())
            ->getJson('/gestao/api/seo')
            ->assertOk()
            ->assertJsonPath('data.seo_title_template', '%page% %sep% %site%')
            ->assertJsonPath('data.sitemap_url', url('/sitemap.xml'));

        $this->actingAs($this->makeAdmin())
            ->putJson('/gestao/api/seo', [
                'seo_separator' => '-',
                'seo_title_template' => '%page% %sep% %site%',
                'seo_description' => 'Descrição padrão do escritório',
                'seo_og_image' => null,
                'seo_og_image_alt' => 'Quatro Ventos',
                'seo_robots' => 'index, follow',
                'og_site_name' => 'Quatro Ventos Studio',
                'og_locale' => 'pt_BR',
                'twitter_card' => 'summary_large_image',
                'sitemap_extra' => ['/extra'],
            ])
            ->assertOk()
            ->assertJsonPath('data.seo_separator', '-')
            ->assertJsonPath('data.og_site_name', 'Quatro Ventos Studio')
            ->assertJsonPath('data.sitemap_extra.0', '/extra');
    }

    public function test_empty_page_fields_inherit_site_defaults(): void
    {
        $this->makeSettings([
            'og_site_name' => 'Quatro Ventos Studio',
            'seo_separator' => '|',
            'seo_title_template' => '%page% %sep% %site%',
            'seo_description' => 'Descrição padrão do site',
            'seo_robots' => 'index, follow',
        ]);

        $this->makePage('home', 'Home', [
            'seo_description' => null,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Home | Quatro Ventos Studio</title>', false)
            ->assertSee('Descrição padrão do site', false);
    }

    public function test_page_title_that_already_includes_site_name_is_kept(): void
    {
        $this->makeSettings([
            'og_site_name' => 'Quatro Ventos',
            'seo_title_template' => '%page% %sep% %site%',
        ]);

        $this->makePage('home', 'Home | Quatro Ventos');

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Home | Quatro Ventos</title>', false)
            ->assertDontSee('Home | Quatro Ventos | Quatro Ventos', false);
    }

    public function test_fill_empty_pages_writes_defaults(): void
    {
        $this->makeSettings();
        $page = $this->makePage('home', 'Home', [
            'seo_description' => null,
            'og_image' => null,
        ]);

        $this->actingAs($this->makeAdmin())
            ->putJson('/gestao/api/seo', [
                'seo_separator' => '|',
                'seo_title_template' => '%page% %sep% %site%',
                'seo_description' => 'Texto herdado',
                'seo_og_image' => '/storage/assets/logo.png',
                'seo_og_image_alt' => 'Logo',
                'seo_robots' => 'index, follow',
                'og_site_name' => 'Quatro Ventos',
                'og_locale' => 'pt_BR',
                'twitter_card' => 'summary_large_image',
                'sitemap_extra' => [],
                'fill_empty_pages' => true,
            ])
            ->assertOk();

        $page->refresh();

        $this->assertSame('Texto herdado', $page->seo_description);
        $this->assertSame('/storage/assets/logo.png', $page->og_image);
    }

    public function test_compose_title_without_site_name(): void
    {
        $site = $this->makeSettings([
            'og_site_name' => 'Quatro Ventos',
            'seo_separator' => '·',
            'seo_title_template' => '%page% %sep% %site%',
        ]);

        $this->assertSame('Contato · Quatro Ventos', Seo::composeTitle('Contato', $site));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSettings(array $overrides = []): SiteSetting
    {
        return SiteSetting::query()->create(array_merge([
            'name' => 'Quatro Ventos',
            'og_site_name' => 'Quatro Ventos',
            'og_locale' => 'pt_BR',
            'twitter_card' => 'summary_large_image',
            'seo_separator' => '|',
            'seo_title_template' => '%page% %sep% %site%',
            'seo_robots' => 'index, follow, max-image-preview:large',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePage(string $slug, string $title, array $overrides = []): Page
    {
        return Page::query()->create(array_merge([
            'slug' => $slug,
            'template' => $slug === 'home' ? 'home' : $slug,
            'status' => 'published',
            'seo_title' => $title,
            'content' => [],
            'published_at' => now(),
        ], $overrides));
    }
}
