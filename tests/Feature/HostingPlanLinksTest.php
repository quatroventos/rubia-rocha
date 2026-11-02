<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\PublicContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostingPlanLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saves_known_plan_links_and_drops_the_rest(): void
    {
        SiteSetting::query()->create(['name' => 'Quatro Ventos']);

        $this->actingAs($this->makeAdmin())
            ->putJson('/gestao/api/site', [
                'name' => 'Quatro Ventos',
                'plan_links' => [
                    'sites-basic' => 'https://loja.example.com/basic',
                    'wp-pro' => '',
                    'inexistente' => 'https://loja.example.com/x',
                ],
            ])
            ->assertOk();

        $this->assertSame(['sites-basic' => 'https://loja.example.com/basic'], SiteSetting::query()->first()->plan_links);
    }

    public function test_plan_link_must_be_http_url(): void
    {
        SiteSetting::query()->create(['name' => 'Quatro Ventos']);

        $this->actingAs($this->makeAdmin())
            ->putJson('/gestao/api/site', [
                'name' => 'Quatro Ventos',
                'plan_links' => ['sites-basic' => 'javascript:alert(1)'],
            ])
            ->assertJsonValidationErrors('plan_links.sites-basic');
    }

    public function test_public_payload_exposes_plan_links(): void
    {
        SiteSetting::query()->create([
            'name' => 'Quatro Ventos',
            'plan_links' => ['woo-pro' => 'https://loja.example.com/woo-pro'],
        ]);

        $site = app(PublicContent::class)->site();

        $this->assertSame(['woo-pro' => 'https://loja.example.com/woo-pro'], (array) $site['plan_links']);
    }

    public function test_pricing_plan_href_uses_settings_link_with_same_key(): void
    {
        SiteSetting::query()->create([
            'name' => 'Quatro Ventos',
            'plan_links' => ['sites-basic' => 'https://loja.example.com/basic'],
        ]);

        Page::query()->create([
            'slug' => 'hospedagem-de-sites',
            'template' => 'custom',
            'status' => 'published',
            'seo_title' => 'Hospedagem de sites | Quatro Ventos',
            'published_at' => now(),
            'content' => ['sections' => [
                ['id' => 'pricing-1', 'type' => 'site.pricing', 'data' => ['categories' => [
                    ['id' => 'sites', 'plans' => [
                        ['name' => 'Basic', 'href' => 'https://antigo.example.com/basic', 'link_key' => 'sites-basic'],
                        ['name' => 'Pro', 'href' => 'https://antigo.example.com/pro', 'link_key' => 'sites-pro'],
                        ['name' => 'Ultra', 'href' => 'https://antigo.example.com/ultra'],
                    ]],
                ]]],
            ]],
        ]);

        $this->getJson('/api/pages/hospedagem-de-sites')
            ->assertOk()
            ->assertJsonPath('sections.0.data.categories.0.plans.0.href', 'https://loja.example.com/basic')
            ->assertJsonPath('sections.0.data.categories.0.plans.1.href', 'https://antigo.example.com/pro')
            ->assertJsonPath('sections.0.data.categories.0.plans.2.href', 'https://antigo.example.com/ultra');
    }
}
