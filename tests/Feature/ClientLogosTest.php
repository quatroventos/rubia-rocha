<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientLogosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $data
     */
    private function logosPage(array $data): void
    {
        Page::query()->create([
            'slug' => 'clientes',
            'template' => 'custom',
            'status' => 'published',
            'seo_title' => 'Clientes',
            'published_at' => now(),
            'content' => ['sections' => [
                ['id' => 'logos-1', 'type' => 'site.logos', 'data' => ['mono' => true, ...$data]],
            ]],
        ]);
    }

    public function test_logos_section_uses_site_library_by_default(): void
    {
        SiteSetting::query()->create([
            'name' => 'Quatro Ventos',
            'client_logos' => [
                ['name' => 'Costi', 'image' => '/assets/clientes/lampa/costi.png', 'image_alt' => ''],
                ['name' => 'Produtive', 'image' => '/assets/clientes/lampa/produtive.svg', 'image_alt' => ''],
            ],
        ]);
        $this->logosPage(['items' => [['name' => 'Antigo', 'image' => '/assets/antigo.png']]]);

        $this->getJson('/api/pages/clientes')
            ->assertOk()
            ->assertJsonCount(2, 'sections.0.data.items')
            ->assertJsonPath('sections.0.data.items.0.name', 'Costi')
            ->assertJsonPath('sections.0.data.items.0.ratio', 4.169)
            ->assertJsonPath('sections.0.data.items.1.name', 'Produtive');
    }

    public function test_logos_section_keeps_its_own_items_when_flagged(): void
    {
        SiteSetting::query()->create([
            'name' => 'Quatro Ventos',
            'client_logos' => [['name' => 'Costi', 'image' => '/assets/clientes/lampa/costi.png', 'image_alt' => '']],
        ]);
        $this->logosPage(['own_logos' => true, 'items' => [['name' => 'Digio', 'image' => '/assets/digio.png']]]);

        $this->getJson('/api/pages/clientes')
            ->assertOk()
            ->assertJsonCount(1, 'sections.0.data.items')
            ->assertJsonPath('sections.0.data.items.0.name', 'Digio');
    }

    public function test_admin_saves_client_logos_and_drops_entries_without_image(): void
    {
        SiteSetting::query()->create(['name' => 'Quatro Ventos']);

        $this->actingAs($this->makeAdmin())
            ->putJson('/gestao/api/site', [
                'name' => 'Quatro Ventos',
                'client_logos' => [['name' => ' Costi ', 'image' => '/storage/media/costi.png']],
            ])
            ->assertOk()
            ->assertJsonPath('data.client_logos.0.name', 'Costi')
            ->assertJsonPath('data.client_logos.0.image', '/storage/media/costi.png');

        $this->actingAs($this->makeAdmin(['email' => 'outro@example.com']))
            ->putJson('/gestao/api/site', [
                'name' => 'Quatro Ventos',
                'client_logos' => [['name' => 'Sem imagem']],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_logos.0.image');
    }
}
