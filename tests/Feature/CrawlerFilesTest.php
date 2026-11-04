<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrawlerFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        SiteSetting::query()->create([
            'name' => 'Rubia Rocha Fotografia',
            'seo_description' => 'Fotógrafa de família em Curitiba.',
            'whatsapp_url' => 'https://wa.me/5541988658624',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function page(array $overrides = []): Page
    {
        return Page::query()->create([
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'content' => ['sections' => []],
            ...$overrides,
        ]);
    }

    public function test_robots_is_regenerated_with_extra_rules_when_settings_change(): void
    {
        SiteSetting::query()->firstOrFail()->update(['robots_extra' => "User-agent: *\nDisallow: /privado/"]);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee("User-agent: ClaudeBot\nAllow: /", false)
            ->assertSee("Disallow: /privado/\n\nSitemap: ".url('/sitemap.xml'), false);
    }

    public function test_llms_lists_published_pages_grouped_and_updates_when_a_page_is_saved(): void
    {
        $this->page(['slug' => 'home', 'template' => 'home', 'seo_title' => 'Fotógrafa em Curitiba | Rubia Rocha Fotografia']);
        $this->page(['slug' => 'ensaios/gestante', 'seo_title' => 'Ensaio de gestante', 'seo_description' => 'Ensaio leve e natural.']);
        $this->page(['slug' => 'rascunho', 'status' => 'draft', 'seo_title' => 'Rascunho']);
        $this->page(['slug' => 'oculta', 'seo_title' => 'Oculta', 'robots' => 'noindex, nofollow']);

        $llms = $this->get('/llms.txt')->assertOk()->getContent();

        $this->assertStringStartsWith("# Rubia Rocha Fotografia\n\n> Fotógrafa de família em Curitiba.", $llms);
        $this->assertStringContainsString('- [Fotógrafa em Curitiba]('.url('/').'/): Fotógrafa de família em Curitiba.', $llms);
        $this->assertStringContainsString("## Ensaios\n\n- [Ensaio de gestante](".url('/ensaios/gestante').'): Ensaio leve e natural.', $llms);
        $this->assertStringContainsString("## Contato\n\n- WhatsApp: https://wa.me/5541988658624", $llms);
        $this->assertStringNotContainsString('Rascunho', $llms);
        $this->assertStringNotContainsString('Oculta', $llms);

        $this->page(['slug' => 'quem-sou', 'seo_title' => 'Quem sou']);

        $this->get('/llms.txt')->assertSee('[Quem sou]('.url('/quem-sou').')', false);
    }

    public function test_seo_settings_manage_crawler_fields_and_return_previews(): void
    {
        $payload = [
            'seo_separator' => '|',
            'seo_title_template' => '%page% %sep% %site%',
            'seo_robots' => 'index, follow',
            'og_site_name' => 'Rubia Rocha Fotografia',
            'og_locale' => 'pt_BR',
            'twitter_card' => 'summary_large_image',
            'robots_extra' => 'Disallow: /teste/',
            'llms_summary' => 'Fotografia afetiva em Curitiba.',
            'llms_details' => 'Atendo em estúdio e ao ar livre.',
        ];

        $this->actingAs($this->makeEditor())->putJson('/gestao/api/seo', $payload)
            ->assertOk()
            ->assertJsonPath('data.llms_summary', 'Fotografia afetiva em Curitiba.')
            ->assertJsonPath('data.llms_url', url('/llms.txt'));

        $this->get('/llms.txt')->assertSee("> Fotografia afetiva em Curitiba.\n\nAtendo em estúdio e ao ar livre.", false);
        $this->get('/robots.txt')->assertSee('Disallow: /teste/', false);
    }
}
