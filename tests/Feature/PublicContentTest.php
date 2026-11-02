<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Services\PublicContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_serves_prerendered_html_without_react(): void
    {
        Page::query()->create([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Início',
            'content' => ['sections' => [['id' => 'a', 'type' => 'site.hero', 'data' => ['title' => 'Processos melhores']]]],
        ]);

        $file = storage_path('app/prerender/pt-BR/index.html');
        $backup = is_file($file) ? File::get($file) : null;
        File::ensureDirectoryExists(dirname($file));
        File::put($file, '<main id="conteudo"><h1>Processos melhores</h1></main>');

        try {
            $this->get('/')
                ->assertOk()
                ->assertSee('<title>Início', false)
                ->assertSee('<main id="conteudo"><h1>Processos melhores</h1></main>', false)
                ->assertDontSee('id="site-root"', false)
                ->assertDontSee('window.__SITE__', false)
                ->assertDontSee('site/main.tsx', false);
        } finally {
            if ($backup === null) {
                File::delete($file);
            } else {
                File::put($file, $backup);
            }
        }
    }

    public function test_missing_fragment_is_a_notice_outside_production(): void
    {
        Page::query()->create([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Início',
            'content' => ['sections' => []],
        ]);

        $file = storage_path('app/prerender/pt-BR/index.html');
        $backup = is_file($file) ? File::get($file) : null;
        File::delete($file);

        try {
            $this->get('/')
                ->assertOk()
                ->assertSee('php artisan site:prerender', false)
                ->assertDontSee('id="site-root"', false);
        } finally {
            if ($backup !== null) {
                File::ensureDirectoryExists(dirname($file));
                File::put($file, $backup);
            }
        }
    }

    public function test_site_payload_links_only_published_policies(): void
    {
        Page::query()->create([
            'slug' => 'politica-de-privacidade',
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Política de privacidade',
            'content' => ['sections' => []],
        ]);
        Page::query()->create([
            'slug' => 'politica-de-cookies',
            'template' => 'custom',
            'status' => 'draft',
            'seo_title' => 'Política de cookies',
            'content' => ['sections' => []],
        ]);

        $legal = (array) app(PublicContent::class)->site('pt-BR')['legal'];

        $this->assertSame(['privacy' => '/politica-de-privacidade'], $legal);
    }

    public function test_robots_allows_ai_crawlers(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('User-agent: GPTBot', false)
            ->assertSee('User-agent: OAI-SearchBot', false)
            ->assertSee('User-agent: Google-Extended', false)
            ->assertDontSee('Disallow', false)
            ->assertSee('Sitemap:', false);
    }

    public function test_page_api_returns_sections(): void
    {
        Page::query()->create([
            'slug' => 'sobre',
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Sobre',
            'content' => ['sections' => [['id' => 'a', 'type' => 'site.prose', 'data' => ['body' => 'Texto']]]],
        ]);

        $this->getJson('/api/pages/sobre')
            ->assertOk()
            ->assertJsonPath('sections.0.type', 'site.prose')
            ->assertJsonPath('sections.0.data.body', 'Texto');
    }
}
