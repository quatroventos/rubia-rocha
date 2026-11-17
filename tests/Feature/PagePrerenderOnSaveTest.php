<?php

namespace Tests\Feature;

use App\Jobs\MeasurePageLighthouseJob;
use App\Jobs\PrerenderPageJob;
use App\Jobs\PrerenderSiteJob;
use App\Models\Page;
use App\Services\LighthouseService;
use App\Services\Seo\PageRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class PagePrerenderOnSaveTest extends TestCase
{
    use RefreshDatabase;

    private function page(): Page
    {
        return Page::query()->create([
            'slug' => 'ensaios-gestante',
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Ensaio gestante',
            'content' => ['sections' => [
                ['id' => 's0', 'type' => 'site.prose', 'data' => ['title' => 'Gestante', 'body' => '<p>Texto.</p>']],
            ]],
        ]);
    }

    public function test_saving_a_published_page_queues_a_single_page_prerender(): void
    {
        config(['cms.prerender_on_save' => true]);
        Queue::fake();
        $page = $this->page();

        $this->actingAs($this->makeEditor())
            ->putJson('/gestao/api/pages/'.$page->slug, [
                'status' => 'published',
                'seo_title' => 'Ensaio gestante Curitiba',
                'seo_description' => 'Descrição',
                'content' => $page->content,
            ])
            ->assertOk();

        Queue::assertPushed(PrerenderPageJob::class, fn (PrerenderPageJob $job) => $job->pageId === $page->id);
        Queue::assertPushed(PrerenderSiteJob::class);
    }

    public function test_lighthouse_job_refreshes_the_page_html_before_measuring(): void
    {
        config(['cms.lighthouse_url' => 'https://example.test']);
        $page = $this->page();

        $renderer = Mockery::mock(PageRenderer::class);
        $renderer->shouldReceive('findPublished')->once()->with('/ensaios-gestante')->andReturn($page);
        $renderer->shouldReceive('publish')->once()->with(Mockery::on(fn (Page $model) => $model->is($page)));
        $this->app->instance(PageRenderer::class, $renderer);

        Http::fake([
            'www.googleapis.com/*' => Http::response(['lighthouseResult' => [
                'categories' => [
                    'performance' => ['score' => 0.9, 'auditRefs' => []],
                    'accessibility' => ['score' => 0.9, 'auditRefs' => []],
                    'best-practices' => ['score' => 0.9, 'auditRefs' => []],
                    'seo' => ['score' => 1, 'auditRefs' => []],
                ],
                'audits' => [],
            ]]),
        ]);

        (new MeasurePageLighthouseJob('/ensaios-gestante'))->handle(
            app(LighthouseService::class),
            $renderer,
        );

        $this->assertSame('ready', app(LighthouseService::class)->pageReport('/ensaios-gestante')['status']);
    }

    public function test_renderer_resolves_published_page_and_prerender_path(): void
    {
        $page = $this->page();
        $renderer = app(PageRenderer::class);

        $this->assertSame($page->id, $renderer->findPublished('/ensaios-gestante')?->id);
        $this->assertSame(
            storage_path('app/prerender/pt-BR/ensaios-gestante.html'),
            $renderer->fileFor($page),
        );

        $path = $renderer->fileFor($page);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '<main>ok</main>');
        $this->assertFileExists($path);
    }
}
