<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PageSlugRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function page(string $slug): Page
    {
        return Page::query()->create([
            'slug' => $slug,
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Ensaios',
            'content' => ['sections' => []],
        ]);
    }

    private function rename(Page $page, string $slug): TestResponse
    {
        return $this->actingAs($this->makeEditor())->putJson("/gestao/api/pages/{$page->id}", [
            'slug' => $slug,
            'seo_title' => $page->seo_title,
            'content' => ['sections' => [['id' => 'a', 'type' => 'site.prose', 'data' => ['body' => '<p>Texto</p>']]]],
        ]);
    }

    public function test_changing_slug_creates_permanent_redirect_and_reports_it(): void
    {
        $page = $this->page('ensaios');

        $this->rename($page, 'portfolio')
            ->assertOk()
            ->assertJsonPath('redirect.from', '/ensaios')
            ->assertJsonPath('redirect.to', '/portfolio');

        $this->get('/ensaios')->assertStatus(301)->assertRedirect(url('/portfolio'));
    }

    public function test_saving_without_slug_change_reports_no_redirect(): void
    {
        $page = $this->page('ensaios');

        $this->rename($page, 'ensaios')->assertOk()->assertJsonPath('redirect', null);
        $this->assertSame(0, Redirect::query()->count());
    }

    public function test_existing_redirects_follow_the_new_slug_without_chains(): void
    {
        $page = $this->page('ensaios');
        Redirect::factory()->create(['from_path' => '/sobre-2', 'to_url' => '/ensaios']);

        $this->rename($page, 'portfolio')->assertJsonPath('redirect.updated', 1);

        $this->assertDatabaseHas('redirects', ['from_path' => '/sobre-2', 'to_url' => '/portfolio']);
    }

    public function test_renaming_back_drops_the_redirect_that_now_points_to_a_live_page(): void
    {
        $page = $this->page('ensaios');

        $this->rename($page, 'portfolio');
        $this->rename($page->fresh(), 'ensaios');

        $this->assertSoftDeleted('redirects', ['from_path' => '/ensaios']);
        $this->assertDatabaseHas('redirects', ['from_path' => '/portfolio', 'to_url' => '/ensaios', 'deleted_at' => null]);
        $this->get('/ensaios')->assertOk();
    }
}
