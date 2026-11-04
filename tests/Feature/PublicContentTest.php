<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\PublicContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicContentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  callable(): void  $assertions
     */
    private function withFragment(string $relative, string $html, callable $assertions): void
    {
        $file = storage_path('app/prerender/pt-BR/'.$relative);
        $backup = is_file($file) ? File::get($file) : null;
        File::ensureDirectoryExists(dirname($file));
        File::put($file, $html);

        try {
            $assertions();
        } finally {
            if ($backup === null) {
                File::delete($file);
            } else {
                File::put($file, $backup);
            }
        }
    }

    public function test_home_serves_prerendered_html_without_react(): void
    {
        Page::query()->create([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Fotografia de família em Curitiba',
            'content' => ['sections' => [['id' => 'a', 'type' => 'site.intro', 'data' => ['title' => 'Olá, eu sou a Rubia']]]],
        ]);

        $this->withFragment('index.html', '<main id="conteudo"><h1>Olá, eu sou a Rubia</h1></main>', function (): void {
            $this->get('/')
                ->assertOk()
                ->assertSee('<title>Fotografia de família em Curitiba', false)
                ->assertSee('<main id="conteudo"><h1>Olá, eu sou a Rubia</h1></main>', false)
                ->assertSee('"@type":"ProfessionalService"', false)
                ->assertDontSee('id="site-root"', false)
                ->assertDontSee('window.__SITE__', false)
                ->assertDontSee('preview.tsx', false);
        });
    }

    public function test_nested_page_is_served_with_breadcrumb_to_its_parent(): void
    {
        Page::query()->create(['slug' => 'ensaios', 'template' => 'custom', 'status' => 'published', 'published_at' => now(), 'seo_title' => 'Ensaios fotográficos | Rubia', 'content' => ['sections' => []]]);
        Page::query()->create(['slug' => 'ensaios/gestante', 'template' => 'custom', 'status' => 'published', 'published_at' => now(), 'seo_title' => 'Ensaio de gestante | Rubia', 'content' => ['sections' => []]]);

        $this->withFragment('ensaios/gestante.html', '<main id="conteudo"><h1>Gestante</h1></main>', function (): void {
            $this->get('/ensaios/gestante')
                ->assertOk()
                ->assertSee('<h1>Gestante</h1>', false)
                ->assertSee('<link rel="canonical" href="'.url('/ensaios/gestante').'"', false)
                ->assertSee('"@type":"BreadcrumbList"', false)
                ->assertSee('"name":"Ensaios fotográficos","item":"'.url('/ensaios').'"', false);
        });
    }

    public function test_draft_and_unknown_pages_are_not_found(): void
    {
        Page::query()->create(['slug' => 'rascunho', 'template' => 'custom', 'status' => 'draft', 'seo_title' => 'Rascunho', 'content' => ['sections' => []]]);

        $this->get('/rascunho')->assertNotFound();
        $this->get('/ensaios/inexistente')->assertNotFound();
    }

    public function test_not_found_uses_prerendered_shell(): void
    {
        $this->withFragment('_404.html', '<main id="conteudo"><section class="lost"><h1>Essa página não existe mais.</h1></section></main>', function (): void {
            $this->get('/ensaios/inexistente')
                ->assertNotFound()
                ->assertSee('<section class="lost"><h1>Essa página não existe mais.</h1></section>', false)
                ->assertSee('<meta name="robots" content="noindex, nofollow" />', false);
        });
    }

    public function test_not_found_falls_back_without_prerendered_shell(): void
    {
        $file = storage_path('app/prerender/pt-BR/_404.html');
        $backup = is_file($file) ? File::get($file) : null;
        File::delete($file);

        try {
            $this->get('/nao-existe')
                ->assertNotFound()
                ->assertSee('Erro 404')
                ->assertSee('Voltar ao início');
        } finally {
            if ($backup !== null) {
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

    public function test_page_payload_sanitizes_rich_text_and_builds_faq_schema(): void
    {
        $page = Page::query()->create([
            'slug' => 'contato',
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Contato',
            'content' => ['sections' => [
                ['id' => 'a', 'type' => 'site.prose', 'data' => ['body' => '<p onclick="x()">Oi</p><script>alert(1)</script>']],
                ['id' => 'b', 'type' => 'site.faq', 'data' => ['items' => [['question' => 'Onde fica o estúdio?', 'answer' => 'Em Curitiba.']]]],
            ]],
        ]);

        $payload = app(PublicContent::class)->page($page);
        $faq = collect($payload['jsonld']['@graph'])->firstWhere('@type', 'FAQPage');

        $this->assertSame('/contato', $payload['path']);
        $this->assertStringNotContainsString('<script', $payload['sections'][0]['data']['body']);
        $this->assertStringNotContainsString('onclick', $payload['sections'][0]['data']['body']);
        $this->assertSame('Onde fica o estúdio?', $faq['mainEntity'][0]['name']);
    }

    public function test_site_payload_exposes_menu_footer_gallery_and_contact_form(): void
    {
        SiteSetting::query()->create([
            'name' => 'Rubia Rocha Fotografia',
            'whatsapp_url' => 'https://wa.me/5541988658624',
            'footer_gallery_title' => 'Galerias',
            'footer_gallery' => [['image' => '/rubia/img/folha.png', 'alt' => 'Folha', 'href' => '/ensaios']],
        ]);
        MenuItem::query()->create(['group' => 'header', 'label' => 'Ensaios', 'href' => '/ensaios', 'sort' => 1, 'is_active' => true]);

        $site = app(PublicContent::class)->site();

        $this->assertSame('Rubia Rocha Fotografia', $site['name']);
        $this->assertSame('https://wa.me/5541988658624', $site['whatsapp_url']);
        $this->assertSame('Ensaios', $site['menus']['header'][0]['label']);
        $this->assertSame('/ensaios', $site['footer_gallery'][0]['href']);
        $this->assertSame(['nome', 'email', 'mensagem'], collect($site['form']['sections'][0]['fields'])->pluck('name')->all());
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

    public function test_sitemap_lists_nested_pages(): void
    {
        Page::query()->create(['slug' => 'home', 'template' => 'home', 'status' => 'published', 'published_at' => now(), 'seo_title' => 'Início', 'content' => ['sections' => []]]);
        Page::query()->create(['slug' => 'eventos/mini-wedding', 'template' => 'custom', 'status' => 'published', 'published_at' => now(), 'seo_title' => 'Mini wedding', 'content' => ['sections' => []]]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.url('/').'/</loc>', false)
            ->assertSee('<loc>'.url('/eventos/mini-wedding').'</loc>', false);
    }
}
