<?php

namespace Tests\Feature;

use App\Jobs\GenerateOgImageJob;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\Seo\OgImageCapture;
use App\Services\Seo\OgImageGenerator;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class OgImageGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSetting::query()->create(['name' => 'Rubia Rocha Fotografia']);
    }

    private function page(array $overrides = []): Page
    {
        return Page::query()->create(array_merge([
            'slug' => 'agentes-de-ia',
            'template' => 'custom',
            'status' => 'published',
            'seo_title' => 'Agentes de IA',
            'published_at' => now(),
            'content' => ['sections' => [['id' => 'intro-1', 'type' => 'site.intro', 'data' => ['title' => 'Fotografia de família']]]],
        ], $overrides));
    }

    public function test_generator_saves_new_card_and_keeps_previous_file_shared_by_a_translation(): void
    {
        $shared = '/storage/media/og/agentes-de-ia-pt-br-old.jpg';
        $page = $this->page(['og_image' => $shared]);
        $translation = $this->page(['locale' => 'en', 'translation_of' => $page->id, 'og_image' => $shared]);

        $this->mock(OgImageCapture::class, function (MockInterface $mock) {
            $mock->shouldReceive('capture')->once()->andReturn('/storage/media/og/agentes-de-ia-en-new.jpg');
            $mock->shouldNotReceive('discard');
        });

        $path = app(OgImageGenerator::class)->page($translation);

        $this->assertSame('/storage/media/og/agentes-de-ia-en-new.jpg', $path);
        $this->assertSame($path, $translation->fresh()->og_image);
        $this->assertSame($shared, $page->fresh()->og_image);
    }

    public function test_generator_discards_previous_card_when_unused(): void
    {
        $page = $this->page(['og_image' => '/storage/media/og/agentes-de-ia-pt-br-old.jpg']);

        $this->mock(OgImageCapture::class, function (MockInterface $mock) {
            $mock->shouldReceive('capture')->once()->andReturn('/storage/media/og/agentes-de-ia-pt-br-new.jpg');
            $mock->shouldReceive('discard')->once()->with('/storage/media/og/agentes-de-ia-pt-br-old.jpg');
        });

        app(OgImageGenerator::class)->page($page);

        $this->assertSame('/storage/media/og/agentes-de-ia-pt-br-new.jpg', $page->fresh()->og_image);
    }

    public function test_saving_regenerates_only_automatic_cards_when_shown_content_changes(): void
    {
        config(['cms.og_on_save' => true]);
        Queue::fake();

        $auto = $this->page(['og_image' => '/storage/media/og/agentes-de-ia-pt-br-1.jpg']);
        $manual = $this->page(['slug' => 'rag', 'og_image' => '/storage/media/manual.jpg']);
        Queue::assertPushed(GenerateOgImageJob::class, 1);

        $auto->update(['robots' => 'noindex']);
        $manual->update(['seo_title' => 'Outro']);
        Queue::assertPushed(GenerateOgImageJob::class, 1);

        $auto->update(['seo_title' => 'Agentes de IA para empresas']);
        Queue::assertPushed(GenerateOgImageJob::class, fn (GenerateOgImageJob $job) => $job->kind === 'page' && $job->id === $auto->id);
        Queue::assertPushed(GenerateOgImageJob::class, 2);
    }

    public function test_admin_queues_all_published_pages(): void
    {
        Bus::fake();
        $this->page();
        $this->page(['slug' => 'quem-sou']);
        $this->page(['slug' => 'rascunho', 'status' => 'draft']);

        $this->actingAs($this->makeAdmin())->postJson('/gestao/api/og-image/all')->assertOk();

        Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 2
            && $batch->jobs->every(fn ($job) => $job instanceof GenerateOgImageJob));
    }
}
