<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageSession;
use App\Models\PageView;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SiteAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pageview_creates_session_and_view(): void
    {
        $page = $this->makePage('quem-somos', 'Quem somos');

        $this->track([
            't' => 'view',
            'sid' => '11111111-1111-4111-8111-111111111111',
            'vid' => '22222222-2222-4222-8222-222222222222',
            'path' => '/quem-somos',
            'ref' => 'https://www.google.com/search?q=quatroventos',
            'lt' => 842,
        ], [
            'CF-IPCountry' => 'BR',
            'CF-IPCity' => 'Ribeirão Preto',
            'CF-Region' => 'São Paulo',
            'CF-IPLatitude' => '-21.1775',
            'CF-IPLongitude' => '-47.8103',
        ])->assertNoContent();

        $this->assertDatabaseHas('page_sessions', [
            'session_key' => '11111111-1111-4111-8111-111111111111',
            'visitor_key' => '22222222-2222-4222-8222-222222222222',
            'origin' => 'www.google.com',
            'city' => 'Ribeirão Preto',
            'country' => 'BR',
            'device' => 'desktop',
            'page_count' => 1,
        ]);

        $this->assertDatabaseHas('page_views', [
            'page_id' => $page->id,
            'path' => '/quem-somos',
            'load_time_ms' => 842,
        ]);
    }

    public function test_heartbeat_updates_last_seen_and_duration(): void
    {
        Carbon::setTestNow('2026-09-17 12:00:00');

        $this->track([
            't' => 'view',
            'sid' => '11111111-1111-4111-8111-111111111111',
            'vid' => '22222222-2222-4222-8222-222222222222',
            'path' => '/',
        ])->assertNoContent();

        Carbon::setTestNow('2026-09-17 12:02:00');

        $this->track([
            't' => 'beat',
            'sid' => '11111111-1111-4111-8111-111111111111',
            'vid' => '22222222-2222-4222-8222-222222222222',
            'path' => '/',
        ])->assertNoContent();

        $session = PageSession::query()->first();

        $this->assertNotNull($session);
        $this->assertSame(1, $session->page_count);
        $this->assertSame(120, $session->duration_seconds);
        $this->assertTrue($session->last_seen_at->equalTo(Carbon::parse('2026-09-17 12:02:00')));
        $this->assertSame(1, PageView::query()->count());
    }

    public function test_bots_are_ignored(): void
    {
        $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])
            ->postJson('/t', [
                't' => 'view',
                'sid' => '11111111-1111-4111-8111-111111111111',
                'vid' => '22222222-2222-4222-8222-222222222222',
                'path' => '/',
            ])
            ->assertNoContent();

        $this->assertDatabaseCount('page_sessions', 0);
        $this->assertDatabaseCount('page_views', 0);
    }

    public function test_admin_path_is_ignored(): void
    {
        $this->track([
            't' => 'view',
            'sid' => '11111111-1111-4111-8111-111111111111',
            'vid' => '22222222-2222-4222-8222-222222222222',
            'path' => '/gestao',
        ])->assertNoContent();

        $this->assertDatabaseCount('page_sessions', 0);
    }

    public function test_dnt_is_ignored(): void
    {
        $this->track([
            't' => 'view',
            'sid' => '11111111-1111-4111-8111-111111111111',
            'vid' => '22222222-2222-4222-8222-222222222222',
            'path' => '/',
        ], [
            'DNT' => '1',
        ])->assertNoContent();

        $this->assertDatabaseCount('page_sessions', 0);
    }

    public function test_home_includes_tracker_and_preview_does_not(): void
    {
        $this->makeSettings();
        $this->makePage('home', 'Home');

        $this->get('/')->assertOk()
            ->assertSee('type="text/plain" data-consent="analytics" data-src="'.asset('js/t.js').'"', false)
            ->assertDontSee('<script src="'.asset('js/t.js').'"', false);

        $this->actingAs($this->makeEditor())
            ->get('/gestao/preview/home')
            ->assertOk()
            ->assertDontSee('js/t.js', false);
    }

    public function test_custom_tags_wait_for_cookie_consent_but_verification_meta_does_not(): void
    {
        $this->makeSettings([
            'tag_header' => '<meta name="google-site-verification" content="abc"><script>window.gtagLoaded = true;</script>',
        ]);
        $this->makePage('home', 'Home');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="google-site-verification" content="abc">', $html);
        $this->assertStringContainsString('<template data-consent="analytics"><script>window.gtagLoaded = true;</script></template>', $html);
    }

    public function test_public_api_remains_hidden(): void
    {
        $this->get('/api')->assertNotFound();
        $this->post('/api')->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    private function track(array $payload, array $headers = []): TestResponse
    {
        return $this->withHeaders(array_merge([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ], $headers))->postJson('/t', $payload);
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePage(string $slug, string $title, array $overrides = []): Page
    {
        return Page::query()->create(array_merge([
            'slug' => $slug,
            'template' => $slug === 'home' ? 'home' : $slug,
            'status' => 'published',
            'seo_title' => $title,
            'content' => [],
            'published_at' => now(),
        ], $overrides));
    }
}
