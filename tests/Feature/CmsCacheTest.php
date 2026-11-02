<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\SystemOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CmsCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_manage_cache(): void
    {
        $this->makeSettings();

        $this->actingAs($this->makeEditor())
            ->getJson('/gestao/api/cache')
            ->assertOk();
    }

    public function test_admin_can_inspect_and_flush_options_cache(): void
    {
        $this->makeSettings();

        $this->actingAs($this->makeAdmin())
            ->getJson('/gestao/api/cache')
            ->assertOk()
            ->assertJsonPath('data.store', 'array')
            ->assertJsonPath('data.keys.site', false);

        app(SystemOptions::class)->site();

        $this->actingAs($this->makeAdmin())
            ->getJson('/gestao/api/cache')
            ->assertOk()
            ->assertJsonPath('data.keys.site', true);

        $this->actingAs($this->makeAdmin())
            ->postJson('/gestao/api/cache/flush')
            ->assertOk()
            ->assertJsonPath('data.keys.site', false);
    }

    public function test_admin_can_update_ttl_and_warm_cache(): void
    {
        $this->makeSettings();
        MenuItem::query()->create([
            'label' => 'Home',
            'href' => '/',
            'group' => 'header',
            'sort' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($this->makeAdmin())
            ->putJson('/gestao/api/cache', ['ttl' => 900])
            ->assertOk()
            ->assertJsonPath('data.ttl', 900);

        $this->actingAs($this->makeAdmin())
            ->postJson('/gestao/api/cache/warm')
            ->assertOk()
            ->assertJsonPath('data.keys.site', true)
            ->assertJsonPath('data.keys.menus:locale', true);
    }

    public function test_public_page_reads_cached_site_options(): void
    {
        $settings = $this->makeSettings(['name' => 'Quatro Ventos']);
        $this->makePage();

        $this->get('/')->assertOk();
        $this->assertTrue(Cache::has(SystemOptions::PREFIX.'site'));
        $this->assertSame('Quatro Ventos', app(SystemOptions::class)->site()?->name);

        SiteSetting::withoutEvents(fn () => $settings->update(['name' => 'Outro']));

        $this->assertSame('Quatro Ventos', app(SystemOptions::class)->site()?->name);

        $settings->update(['name' => 'Novo Nome']);

        $this->assertFalse(Cache::has(SystemOptions::PREFIX.'site'));
        $this->assertSame('Novo Nome', app(SystemOptions::class)->site()?->name);
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
        ], $overrides));
    }

    private function makePage(): Page
    {
        return Page::query()->create([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'published',
            'seo_title' => 'Home',
            'content' => [],
            'published_at' => now(),
        ]);
    }
}
