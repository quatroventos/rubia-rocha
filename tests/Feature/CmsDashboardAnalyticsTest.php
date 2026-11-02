<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageSession;
use App\Models\PageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CmsDashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_cannot_read_dashboard(): void
    {
        $this->getJson('/gestao/api/dashboard')->assertStatus(401);
    }

    public function test_editor_receives_aggregated_stats(): void
    {
        Carbon::setTestNow('2026-09-17 15:00:00');

        $home = $this->makePage('home', 'Home');
        $about = $this->makePage('quem-somos', 'Quem somos');

        $live = $this->makeSession([
            'started_at' => now()->subMinutes(10),
            'last_seen_at' => now()->subMinute(),
            'duration_seconds' => 540,
            'page_count' => 2,
            'origin' => 'www.google.com',
            'city' => 'Ribeirão Preto',
            'region' => 'São Paulo',
            'country' => 'BR',
            'latitude' => -21.1775,
            'longitude' => -47.8103,
        ]);

        $this->makeView($live, $home, '/', 800, now()->subMinutes(8));
        $this->makeView($live, $about, '/quem-somos', 600, now()->subMinutes(4));
        $this->makeView($live, $about, '/quem-somos', 500, now()->subMinutes(3));

        $old = $this->makeSession([
            'started_at' => now()->subDays(20),
            'last_seen_at' => now()->subDays(20)->addMinutes(3),
            'duration_seconds' => 180,
            'page_count' => 1,
            'origin' => 'direct',
            'city' => 'São Paulo',
            'country' => 'BR',
        ]);

        $this->makeView($old, $home, '/', 1200, now()->subDays(20));

        $this->actingAs($this->makeEditor())
            ->getJson('/gestao/api/dashboard?range=7d')
            ->assertOk()
            ->assertJsonPath('live.visitors', 1)
            ->assertJsonPath('load_time_avg_ms', 633)
            ->assertJsonPath('month_visits', 1)
            ->assertJsonPath('pages.0.path', '/quem-somos')
            ->assertJsonPath('pages.0.title', 'Quem somos')
            ->assertJsonPath('pages.0.views', 2)
            ->assertJsonPath('pages.1.path', '/')
            ->assertJsonPath('origins.0.host', 'www.google.com')
            ->assertJsonPath('locations.0.city', 'Ribeirão Preto')
            ->assertJsonPath('duration_avg_seconds', 540)
            ->assertJsonPath('depth_avg', 2)
            ->assertJsonPath('lighthouse.status', 'pending');

        $this->actingAs($this->makeEditor())
            ->getJson('/gestao/api/dashboard?range=month')
            ->assertOk()
            ->assertJsonPath('month_visits', 1)
            ->assertJsonPath('pages.0.path', '/quem-somos')
            ->assertJsonPath('pages.0.views', 2);
    }

    public function test_range_excludes_older_sessions(): void
    {
        Carbon::setTestNow('2026-09-17 15:00:00');

        $home = $this->makePage('home', 'Home');

        $recent = $this->makeSession([
            'started_at' => now()->subDays(2),
            'last_seen_at' => now()->subDays(2)->addMinutes(5),
            'page_count' => 1,
            'origin' => 'www.linkedin.com',
        ]);
        $this->makeView($recent, $home, '/', 400, now()->subDays(2));

        $older = $this->makeSession([
            'started_at' => now()->subDays(20),
            'last_seen_at' => now()->subDays(20)->addMinutes(2),
            'page_count' => 1,
            'origin' => 'direct',
        ]);
        $this->makeView($older, $home, '/', 400, now()->subDays(20));

        $this->actingAs($this->makeEditor())
            ->getJson('/gestao/api/dashboard?range=7d')
            ->assertOk()
            ->assertJsonPath('origins.0.host', 'www.linkedin.com')
            ->assertJsonCount(1, 'origins');

        $this->actingAs($this->makeEditor())
            ->getJson('/gestao/api/dashboard?range=30d')
            ->assertOk()
            ->assertJsonCount(2, 'origins');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSession(array $overrides = []): PageSession
    {
        return PageSession::query()->create(array_merge([
            'session_key' => fake()->uuid(),
            'visitor_key' => fake()->uuid(),
            'ip' => '201.0.0.1',
            'device' => 'desktop',
            'browser' => 'Chrome',
            'os' => 'macOS',
            'origin' => 'direct',
            'started_at' => now(),
            'last_seen_at' => now(),
            'duration_seconds' => 30,
            'page_count' => 1,
        ], $overrides));
    }

    private function makeView(PageSession $session, Page $page, string $path, int $loadTime, Carbon $at): PageView
    {
        return PageView::query()->create([
            'page_session_id' => $session->id,
            'page_id' => $page->id,
            'path' => $path,
            'load_time_ms' => $loadTime,
            'created_at' => $at,
        ]);
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
