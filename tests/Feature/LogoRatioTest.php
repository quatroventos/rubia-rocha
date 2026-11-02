<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoRatioTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_payload_exposes_logo_ratio_for_svg_and_raster(): void
    {
        SiteSetting::query()->create(['name' => 'Quatro Ventos']);

        Page::query()->create([
            'slug' => 'clientes',
            'template' => 'custom',
            'status' => 'published',
            'seo_title' => 'Clientes',
            'published_at' => now(),
            'content' => ['sections' => [
                ['id' => 'logos-1', 'type' => 'site.logos', 'data' => ['mono' => true, 'own_logos' => true, 'items' => [
                    ['name' => 'Produtive', 'image' => '/assets/clientes/lampa/produtive.svg'],
                    ['name' => 'Costi', 'image' => '/assets/clientes/lampa/costi.png'],
                    ['name' => 'Sumiu', 'image' => '/assets/clientes/nao-existe.png'],
                    ['name' => 'Fora', 'image' => '/../.env'],
                ]]],
            ]],
        ]);

        $this->getJson('/api/pages/clientes')
            ->assertOk()
            ->assertJsonPath('sections.0.data.items.0.ratio', 1.472)
            ->assertJsonPath('sections.0.data.items.1.ratio', 4.169)
            ->assertJsonPath('sections.0.data.items.2.ratio', null)
            ->assertJsonPath('sections.0.data.items.3.ratio', null);
    }
}
