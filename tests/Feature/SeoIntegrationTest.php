<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SeoIntegration;
use App\Models\SiteSetting;
use App\Services\Content\SectionCopyWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeoIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSetting::query()->create(['name' => 'Quatro Ventos', 'og_locale' => 'pt_BR', 'twitter_card' => 'summary_large_image']);
    }

    public function test_secrets_are_encrypted_write_only_and_applied_to_the_config(): void
    {
        $editor = $this->makeEditor();

        $response = $this->actingAs($editor)
            ->putJson('/gestao/api/seo/integrations', [
                'openseo_url' => 'http://openseo.test/mcp/',
                'openseo_project_id' => 'proj_1',
                'openseo_paid_enabled' => false,
                'secrets' => ['openseo_api_key' => 'oseo_secret', 'openai_api_key' => 'sk-secret'],
            ])
            ->assertOk()
            ->assertJsonPath('data.secrets.openseo_api_key.set', true)
            ->assertJsonPath('data.values.openseo_project_id', 'proj_1')
            ->assertJsonPath('data.paid', false);

        $this->assertStringNotContainsString('oseo_secret', $response->getContent());
        $this->assertStringNotContainsString('sk-secret', $response->getContent());
        $this->assertNotSame('oseo_secret', DB::table('seo_integrations')->value('openseo_api_key'));
        $this->assertSame('oseo_secret', config('services.openseo.key'));
        $this->assertSame('http://openseo.test/mcp', config('services.openseo.url'));
        $this->assertSame('sk-secret', config('services.openai.key'));

        $this->actingAs($editor)
            ->putJson('/gestao/api/seo/integrations', ['openseo_project_id' => 'proj_1', 'secrets' => ['openseo_api_key' => '']])
            ->assertOk();

        $this->assertSame('oseo_secret', SeoIntegration::current()->openseo_api_key);

        $this->actingAs($editor)
            ->putJson('/gestao/api/seo/integrations', ['clear' => ['openseo_api_key']])
            ->assertOk()
            ->assertJsonPath('data.secrets.openseo_api_key.set', false);

        $this->assertNull(SeoIntegration::current()->openseo_api_key);
    }

    public function test_custom_prompts_reach_the_ai_and_the_default_text_is_not_stored(): void
    {
        config(['services.openai.base_url' => 'https://openai.test/v1']);
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->putJson('/gestao/api/seo/integrations', ['prompt_writer' => SectionCopyWriter::defaultPrompt()])
            ->assertOk()
            ->assertJsonPath('data.values.prompt_writer', null);

        $this->actingAs($editor)
            ->putJson('/gestao/api/seo/integrations', [
                'prompt_writer' => 'Escreva como a Quatro Ventos fala com clientes.',
                'prompt_seo_description' => 'Cite Curitiba quando fizer sentido.',
                'secrets' => ['openai_api_key' => 'sk-test'],
            ])
            ->assertOk();

        Http::fake([
            'openai.test/*' => Http::response(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['value' => 'Agentes de IA que atendem clientes no WhatsApp, integrados ao seu sistema, com resposta rápida e histórico em um só lugar.'])]]]]]),
        ]);

        $page = Page::query()->create(['slug' => 'agentes-de-ia', 'template' => 'custom', 'status' => 'published', 'published_at' => now(), 'seo_title' => 'Agentes de IA', 'content' => ['sections' => []]]);

        $this->actingAs($editor)
            ->postJson('/gestao/api/sections/copy', [
                'kind' => 'page',
                'target' => 'seo',
                'page_id' => $page->id,
                'mode' => 'melhorar',
                'field' => 'seo_description',
                'sections' => [['type' => 'site.prose', 'data' => ['title' => 'Atendimento', 'body' => '<p>Respondemos clientes no WhatsApp.</p>']]],
            ])
            ->assertOk();

        Http::assertSent(function (Request $request): bool {
            $body = (string) json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return $request->hasHeader('Authorization', 'Bearer sk-test')
                && str_contains($body, 'Escreva como a Quatro Ventos fala com clientes.')
                && ! str_contains($body, 'AI Slop')
                && str_contains($body, 'Instruções do site para este campo: Cite Curitiba quando fizer sentido.');
        });
    }

    public function test_guest_cannot_read_integrations(): void
    {
        $this->getJson('/gestao/api/seo/integrations')->assertUnauthorized();
    }
}
