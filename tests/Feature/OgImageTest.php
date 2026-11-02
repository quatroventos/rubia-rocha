<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\Seo\OgImageCapture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OgImageTest extends TestCase
{
    use RefreshDatabase;

    private function page(): Page
    {
        return Page::query()->create([
            'slug' => 'agentes-de-ia',
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Agentes de IA',
            'content' => ['sections' => [['id' => 'a', 'type' => 'site.prose', 'data' => ['title' => 'Salvo', 'body' => '']]]],
        ]);
    }

    public function test_capture_uses_the_unsaved_content_and_returns_the_image_path(): void
    {
        $page = $this->page();

        $this->mock(OgImageCapture::class, function (MockInterface $mock): void {
            $mock->shouldReceive('capture')
                ->once()
                ->with(Mockery::on(fn (array $payload) => $payload['sections'][0]['data']['title'] === 'Rascunho'), Mockery::type('array'), 'agentes-de-ia-pt-BR')
                ->andReturn('/storage/media/og/agentes-de-ia-1.jpg');
        });

        $this->actingAs($this->makeEditor())
            ->postJson("/gestao/api/og-image/pages/{$page->id}", [
                'content' => ['sections' => [['id' => 'a', 'type' => 'site.prose', 'data' => ['title' => 'Rascunho', 'body' => '']]]],
            ])
            ->assertOk()
            ->assertJsonPath('path', '/storage/media/og/agentes-de-ia-1.jpg')
            ->assertJsonPath('width', 1200)
            ->assertJsonPath('height', 630);

        $this->assertSame('Salvo', $page->fresh()->content['sections'][0]['data']['title']);
    }

    public function test_capture_failure_returns_the_message(): void
    {
        $page = $this->page();

        $this->mock(OgImageCapture::class, function (MockInterface $mock): void {
            $mock->shouldReceive('capture')->andThrow(new \RuntimeException('Não achei o Chrome no servidor para gerar a OG image.'));
        });

        $this->actingAs($this->makeEditor())
            ->postJson("/gestao/api/og-image/pages/{$page->id}", ['content' => ['sections' => []]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Não achei o Chrome no servidor para gerar a OG image.');
    }

    public function test_capture_page_renders_only_with_a_valid_token(): void
    {
        $token = str_repeat('a', 40);

        $this->get('/__og/'.$token)->assertNotFound();

        Cache::put(OgImageCapture::tokenKey($token), [
            'payload' => ['locale' => 'pt', 'sections' => [], 'seo' => [
                'title' => 'Agentes de IA', 'description' => null, 'robots' => 'index', 'canonical' => 'http://x', 'og_image' => null,
                'og_image_alt' => null, 'twitter_card' => 'summary', 'og_site_name' => 'Quatro Ventos', 'og_locale' => 'pt_BR',
            ]],
            'site' => [],
        ], 60);

        $this->get('/__og/'.$token)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('window.__SITE__', false);
    }

    public function test_guest_cannot_capture(): void
    {
        $page = $this->page();

        $this->postJson("/gestao/api/og-image/pages/{$page->id}", ['content' => ['sections' => []]])->assertUnauthorized();
    }
}
