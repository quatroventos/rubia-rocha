<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteFaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_favicon_path(): void
    {
        $this->makeSettings();

        $this->actingAs($this->makeAdmin())
            ->putJson('/gestao/api/site', [
                'name' => 'Quatro Ventos',
                'favicon_path' => '/storage/assets/favicon.png',
            ])
            ->assertOk()
            ->assertJsonPath('data.favicon_path', '/storage/assets/favicon.png');

        $this->assertSame('/storage/assets/favicon.png', SiteSetting::query()->value('favicon_path'));
    }

    public function test_public_layout_uses_configured_favicon(): void
    {
        $this->makeSettings([
            'favicon_path' => 'https://cdn.example.com/brand-favicon.png',
        ]);

        Page::query()->create([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'published',
            'seo_title' => 'Home',
            'content' => [],
            'published_at' => now(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('rel="icon"', false)
            ->assertSee('https://cdn.example.com/brand-favicon.png', false);
    }

    public function test_public_layout_falls_back_to_default_favicon(): void
    {
        $this->makeSettings(['favicon_path' => null]);

        Page::query()->create([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'published',
            'seo_title' => 'Home',
            'content' => [],
            'published_at' => now(),
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('favicon.ico', false);
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
            'email' => 'adv@quatroventos.com.br',
        ], $overrides));
    }
}
