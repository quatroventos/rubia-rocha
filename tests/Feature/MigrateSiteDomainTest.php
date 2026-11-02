<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrateSiteDomainTest extends TestCase
{
    use RefreshDatabase;

    private function seedContent(): Page
    {
        SiteSetting::query()->create([
            'name' => 'Quatro Ventos',
            'tag_footer' => '<a href="http://127.0.0.1:8080/contato">Contato</a>',
        ]);

        MenuItem::query()->create([
            'locale' => 'pt-BR',
            'group' => 'header',
            'label' => 'Suporte',
            'href' => 'https://quatroventos.freshdesk.com/support/home',
            'sort' => 1,
        ]);

        return Page::query()->create([
            'slug' => 'servicos',
            'template' => 'custom',
            'status' => 'published',
            'seo_title' => 'Serviços',
            'canonical' => 'http://localhost:8080/servicos',
            'published_at' => now(),
            'content' => ['sections' => [
                ['id' => 's1', 'type' => 'site.panel', 'data' => [
                    'body' => '<p><a href="http://localhost:8080/agentes-de-ia#faq">Agentes</a>, <a href="https://abc-12.ngrok-free.app">início</a> e <a href="http://localhostx.com/">outro</a>.</p>',
                    'cta_href' => 'https://quatroventos.freshdesk.com/',
                ]],
            ]],
        ]);
    }

    public function test_replaces_local_hosts_with_the_publishing_domain(): void
    {
        $page = $this->seedContent();

        $this->artisan('site:migrate-domain', ['to' => 'https://www.quatroventos.com.br/', '--skip-prerender' => true])->assertSuccessful();

        $page->refresh();
        $body = $page->content['sections'][0]['data']['body'];
        $this->assertSame('https://www.quatroventos.com.br/servicos', $page->canonical);
        $this->assertStringContainsString('href="https://www.quatroventos.com.br/agentes-de-ia#faq"', $body);
        $this->assertStringContainsString('href="https://www.quatroventos.com.br"', $body);
        $this->assertStringContainsString('href="http://localhostx.com/"', $body);
        $this->assertSame('https://quatroventos.freshdesk.com/', $page->content['sections'][0]['data']['cta_href']);
        $this->assertSame('https://quatroventos.freshdesk.com/support/home', MenuItem::query()->value('href'));
        $this->assertStringContainsString('href="https://www.quatroventos.com.br/contato"', (string) SiteSetting::query()->value('tag_footer'));
    }

    public function test_dry_run_changes_nothing(): void
    {
        $page = $this->seedContent();

        $this->artisan('site:migrate-domain', ['to' => 'https://www.quatroventos.com.br', '--dry-run' => true])
            ->expectsOutputToContain('Simulação')
            ->assertSuccessful();

        $this->assertSame('http://localhost:8080/servicos', $page->fresh()->canonical);
    }

    public function test_relative_mode_turns_links_into_paths(): void
    {
        $page = $this->seedContent();

        $this->artisan('site:migrate-domain', ['--relative' => true, '--skip-prerender' => true])->assertSuccessful();

        $body = $page->fresh()->content['sections'][0]['data']['body'];
        $this->assertStringContainsString('href="/agentes-de-ia#faq"', $body);
        $this->assertStringContainsString('href="/"', $body);
    }

    public function test_custom_from_domain_and_escaped_json(): void
    {
        $page = $this->seedContent();
        DB::table('pages')->where('id', $page->id)->update(['og_image' => 'https:\/\/quatroventos.com.br\/og.jpg']);

        $this->artisan('site:migrate-domain', [
            'to' => 'https://www.quatroventos.com.br',
            '--from' => ['quatroventos.com.br'],
            '--skip-prerender' => true,
        ])->assertSuccessful();

        $page->refresh();
        $this->assertSame('https:\/\/www.quatroventos.com.br\/og.jpg', $page->og_image);
        $this->assertSame('http://localhost:8080/servicos', $page->canonical);
    }

    public function test_requires_a_valid_target_unless_relative(): void
    {
        $this->artisan('site:migrate-domain', ['to' => 'quatroventos'])->assertFailed();
    }
}
