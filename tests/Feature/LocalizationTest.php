<?php

namespace Tests\Feature;

use App\Models\CaseStudy;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\Translation\ContentStrings;
use App\Services\Translation\PageTranslator;
use App\Services\Translation\TextTranslator;
use App\Support\Locales;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    private function page(array $attributes = []): Page
    {
        return Page::query()->create(array_merge([
            'slug' => 'agentes-de-ia',
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Agentes de IA',
            'seo_description' => 'Agentes que trabalham na operação.',
            'content' => ['sections' => [['id' => 'a', 'type' => 'site.prose', 'data' => ['title' => 'Agentes', 'body' => '<p>Veja o <a href="/contato">contato</a>.</p>']]]],
        ], $attributes));
    }

    private function translation(Page $source, string $locale, string $slug, string $status = 'published'): Page
    {
        return Page::query()->create([
            'slug' => $slug,
            'locale' => $locale,
            'translation_of' => $source->id,
            'template' => $source->template,
            'status' => $status,
            'published_at' => now(),
            'seo_title' => 'AI agents',
            'seo_description' => 'Agents that work inside the operation.',
            'content' => ['sections' => [['id' => 'a', 'type' => 'site.prose', 'data' => ['title' => 'Agents', 'body' => '<p>See <a href="/contato">contact</a>.</p>']]]],
            'source_hash' => $source->sourceHash(),
        ]);
    }

    public function test_translated_page_is_served_under_prefix_with_hreflang(): void
    {
        $source = $this->page();
        $this->translation($source, 'en', 'ai-agents');

        $this->get('/en/ai-agents')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('<link rel="canonical" href="'.url('/en/ai-agents').'" />', false)
            ->assertSee('hreflang="pt-BR" href="'.url('/agentes-de-ia').'"', false)
            ->assertSee('hreflang="en" href="'.url('/en/ai-agents').'"', false)
            ->assertSee('hreflang="x-default" href="'.url('/agentes-de-ia').'"', false)
            ->assertSee('<meta property="og:locale" content="en_US" />', false);

        $this->withHeaders(['Accept-Language' => 'pt-BR,pt;q=0.9'])
            ->get('/agentes-de-ia')
            ->assertOk()
            ->assertSee('<html lang="pt-BR">', false)
            ->assertSee('hreflang="en" href="'.url('/en/ai-agents').'"', false);
    }

    public function test_draft_or_missing_translation_returns_404(): void
    {
        $source = $this->page();
        $this->translation($source, 'es', 'agentes-de-ia', 'draft');

        $this->get('/es/agentes-de-ia')->assertNotFound();
        $this->get('/en/ai-agents')->assertNotFound();
        $this->get('/agentes-de-ia')->assertOk()->assertDontSee('hreflang="es"', false);
    }

    public function test_page_api_resolves_locale_and_localizes_internal_links(): void
    {
        $source = $this->page();
        $contact = $this->page(['slug' => 'contato', 'seo_title' => 'Contato', 'template' => 'custom']);
        $this->translation($source, 'en', 'ai-agents');
        $this->translation($contact, 'en', 'contact');

        $this->getJson('/api/pages/ai-agents?locale=en')
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('alternates.pt-BR', '/agentes-de-ia')
            ->assertJsonPath('alternates.en', '/en/ai-agents')
            ->assertJsonPath('sections.0.data.body', '<p>See <a href="/en/contact">contact</a>.</p>');

        $this->getJson('/api/pages/ai-agents')->assertNotFound();
    }

    public function test_same_slug_is_allowed_in_different_locales_only(): void
    {
        $source = $this->page();
        $this->translation($source, 'es', 'agentes-de-ia');

        $this->assertSame(2, Page::query()->where('slug', 'agentes-de-ia')->count());

        $this->expectException(QueryException::class);
        $this->page(['seo_title' => 'Duplicada']);
    }

    public function test_browser_with_english_preference_is_redirected_once(): void
    {
        $source = $this->page();
        $this->translation($source, 'en', 'ai-agents');

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9,pt;q=0.5', 'User-Agent' => self::BROWSER])
            ->get('/agentes-de-ia')
            ->assertRedirect('/en/ai-agents')
            ->assertCookie(Locales::cookie(), 'en', false);
    }

    public function test_bots_and_explicit_choice_are_not_redirected(): void
    {
        $source = $this->page();
        $this->translation($source, 'en', 'ai-agents');

        $this->withHeaders(['Accept-Language' => 'en-US,en;q=0.9', 'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])
            ->get('/agentes-de-ia')
            ->assertOk();

        $this->withUnencryptedCookie(Locales::cookie(), 'pt-BR')
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9', 'User-Agent' => self::BROWSER])
            ->get('/agentes-de-ia')
            ->assertOk();
    }

    public function test_portuguese_browser_gets_cookie_and_vary_header(): void
    {
        $this->page();

        $response = $this->withHeaders(['Accept-Language' => 'pt-BR,pt;q=0.9', 'User-Agent' => self::BROWSER])
            ->get('/agentes-de-ia')
            ->assertOk()
            ->assertCookie(Locales::cookie(), 'pt-BR', false);

        $this->assertStringContainsString('Accept-Language', implode(',', $response->headers->all('vary')));
    }

    public function test_sitemap_lists_alternates(): void
    {
        $source = $this->page();
        $this->translation($source, 'en', 'ai-agents');
        $this->translation($source, 'es', 'agentes-de-ia', 'draft');

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $xml);
        $this->assertStringContainsString('<loc>'.url('/en/ai-agents').'</loc>', $xml);
        $this->assertStringContainsString('hreflang="en" href="'.url('/en/ai-agents').'"', $xml);
        $this->assertStringContainsString('hreflang="x-default" href="'.url('/agentes-de-ia').'"', $xml);
        $this->assertStringNotContainsString('/es/agentes-de-ia', $xml);
    }

    public function test_site_payload_uses_locale_menus(): void
    {
        MenuItem::query()->create(['label' => 'Contato', 'href' => '/contato', 'group' => 'header', 'sort' => 1]);
        MenuItem::query()->create(['label' => 'Contact', 'href' => '/contato', 'group' => 'header', 'sort' => 1, 'locale' => 'en']);
        $contact = $this->page(['slug' => 'contato', 'seo_title' => 'Contato']);
        $this->translation($contact, 'en', 'contact');

        $this->getJson('/api/site?locale=en')
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('menus.header.0.label', 'Contact')
            ->assertJsonPath('menus.header.0.href', '/en/contact');

        $this->getJson('/api/site')
            ->assertJsonPath('menus.header.0.label', 'Contato')
            ->assertJsonPath('menus.header.0.href', '/contato');
    }

    public function test_legacy_english_urls_redirect(): void
    {
        $this->page(['slug' => 'desenvolvimento-de-sites', 'seo_title' => 'Sites']);

        $this->get('/en/webdesign.php')->assertRedirect('/en');
        $this->get('/en/webdesign')->assertRedirect('/en');
    }

    public function test_content_strings_skip_links_media_and_references(): void
    {
        $strings = ContentStrings::extract(['sections' => [
            ['id' => 'x1', 'type' => 'site.hero', 'data' => [
                'title' => 'Processos melhores',
                'primary_href' => '/contato',
                'primary_label' => 'Falar com a equipe',
                'image' => 'media/hero.png',
            ]],
            ['id' => 'x2', 'type' => 'site.cases', 'data' => ['title' => 'Cases', 'items' => ['conexme', 'tripfy']]],
        ]]);

        $this->assertSame([
            'sections.0.data.title' => 'Processos melhores',
            'sections.0.data.primary_label' => 'Falar com a equipe',
            'sections.1.data.title' => 'Cases',
        ], $strings);
    }

    public function test_page_translator_creates_draft_preserving_structure(): void
    {
        config(['services.openai.key' => 'test-key', 'services.openai.model' => 'test-model']);
        $source = $this->page();

        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['items' => [
                    ['id' => 'sections.0.data.title', 'text' => 'Agents'],
                    ['id' => 'sections.0.data.body', 'text' => '<p>See the <a href="/contato">contact</a>.</p>'],
                ]])]]]]])
                ->push(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode([
                    'slug' => 'AI Agents',
                    'seo_title' => 'AI agents for operations',
                    'seo_description' => 'Agents that work inside your operation.',
                ])]]]]]),
        ]);

        $row = app(PageTranslator::class)->translate($source, 'en');

        $this->assertSame('en', $row->locale);
        $this->assertSame($source->id, $row->translation_of);
        $this->assertSame('draft', $row->status);
        $this->assertSame('ai-agents', $row->slug);
        $this->assertSame('AI agents for operations', $row->seo_title);
        $this->assertSame('site.prose', $row->content['sections'][0]['type']);
        $this->assertSame('a', $row->content['sections'][0]['id']);
        $this->assertSame('<p>See the <a href="/contato">contact</a>.</p>', $row->content['sections'][0]['data']['body']);
        $this->assertFalse($row->isTranslationStale());

        $source->update(['seo_title' => 'Agentes de IA para empresas']);
        $this->assertTrue($row->fresh()->isTranslationStale());

        Http::assertSentCount(2);
    }

    public function test_text_translator_retries_items_missing_from_the_response(): void
    {
        config(['services.openai.key' => 'test-key', 'services.openai.model' => 'test-model']);
        $reply = fn (array $items) => ['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['items' => $items])]]]]];

        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push($reply([['id' => 'a', 'text' => 'Agents']]))
                ->push($reply([['id' => 'b', 'text' => 'Contact']])),
        ]);

        $result = app(TextTranslator::class)->translate(['a' => 'Agentes', 'b' => 'Contato'], 'en');

        $this->assertSame(['a' => 'Agents', 'b' => 'Contact'], $result);
        Http::assertSentCount(2);
        $retry = (string) Http::recorded()[1][0]['input'];
        $this->assertStringContainsString('Contato', $retry);
        $this->assertStringNotContainsString('Agentes', $retry);
    }

    public function test_text_translator_fails_when_retry_still_misses_items(): void
    {
        config(['services.openai.key' => 'test-key', 'services.openai.model' => 'test-model']);
        $reply = ['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['items' => [['id' => 'a', 'text' => 'Agents']]])]]]]];

        Http::fake(['api.openai.com/*' => Http::response($reply)]);

        $this->expectExceptionMessage('Tradução incompleta: 1 trecho(s) sem retorno.');

        app(TextTranslator::class)->translate(['a' => 'Agentes', 'b' => 'Contato'], 'en');
    }

    public function test_case_translation_uses_locale_path(): void
    {
        $case = CaseStudy::query()->create([
            'slug' => 'conexme',
            'status' => 'published',
            'client' => 'Conexme',
            'title' => 'Plataforma',
            'published_at' => now(),
        ]);
        CaseStudy::query()->create([
            'slug' => 'conexme',
            'locale' => 'es',
            'translation_of' => $case->id,
            'status' => 'published',
            'client' => 'Conexme',
            'title' => 'Plataforma web',
            'published_at' => now(),
        ]);

        $this->get('/es/cases/conexme')
            ->assertOk()
            ->assertSee('<html lang="es">', false)
            ->assertSee('hreflang="pt-BR" href="'.url('/cases/conexme').'"', false);
    }
}
