<?php

namespace Tests\Feature;

use App\Jobs\TranslateContentJob;
use App\Models\CaseStudy;
use App\Models\Page;
use App\Services\Translation\PageTranslator;
use App\Services\Translation\TextTranslator;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery\MockInterface;
use Tests\TestCase;

class TranslateSiteBatchTest extends TestCase
{
    use RefreshDatabase;

    private function page(array $overrides = []): Page
    {
        return Page::query()->create(array_merge([
            'slug' => 'agentes-de-ia',
            'template' => 'custom',
            'status' => 'published',
            'seo_title' => 'Agentes de IA',
            'published_at' => now(),
            'content' => ['sections' => []],
        ], $overrides));
    }

    public function test_queues_pending_items_for_every_locale_and_skips_up_to_date_translations(): void
    {
        Bus::fake();
        $this->mock(TextTranslator::class, fn (MockInterface $mock) => $mock->shouldReceive('configured')->andReturn(true));

        $translated = $this->page();
        $en = $this->page(['locale' => 'en', 'translation_of' => $translated->id, 'slug' => 'ai-agents']);
        $en->forceFill(['source_hash' => $en->sourceHash()])->save();
        $this->page(['slug' => 'rascunho', 'status' => 'draft']);
        CaseStudy::query()->create(['slug' => 'conexme', 'status' => 'published', 'client' => 'Conexme', 'title' => 'Plataforma', 'published_at' => now()]);

        $this->actingAs($this->makeAdmin())->postJson('/gestao/api/translations/all')->assertOk();

        Bus::assertBatched(function (PendingBatch $batch) use ($translated) {
            $keys = $batch->jobs->map(fn (TranslateContentJob $job) => $job->kind.':'.$job->id.':'.$job->locale)->sort()->values()->all();

            return $batch->jobs->every(fn (TranslateContentJob $job) => $job->publish)
                && $keys === collect(['case', 'menus', 'page'])
                    ->flatMap(fn (string $kind) => match ($kind) {
                        'case' => ['case:'.CaseStudy::query()->value('id').':en', 'case:'.CaseStudy::query()->value('id').':es'],
                        'menus' => ['menus::en', 'menus::es'],
                        'page' => ['page:'.$translated->id.':es'],
                    })->sort()->values()->all();
        });
    }

    public function test_force_requeues_up_to_date_translations(): void
    {
        Bus::fake();
        $this->mock(TextTranslator::class, fn (MockInterface $mock) => $mock->shouldReceive('configured')->andReturn(true));

        $page = $this->page();
        $en = $this->page(['locale' => 'en', 'translation_of' => $page->id, 'slug' => 'ai-agents']);
        $en->forceFill(['source_hash' => $en->sourceHash()])->save();

        $this->actingAs($this->makeAdmin())->postJson('/gestao/api/translations/all', ['force' => true])->assertOk();

        Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->contains(fn (TranslateContentJob $job) => $job->kind === 'page' && $job->locale === 'en'));
    }

    public function test_job_publishes_the_translation_when_requested(): void
    {
        $page = $this->page();
        $draft = $this->page(['locale' => 'en', 'translation_of' => $page->id, 'slug' => 'ai-agents', 'status' => 'draft', 'published_at' => null]);

        $this->mock(PageTranslator::class, fn (MockInterface $mock) => $mock->shouldReceive('translate')->once()->andReturn($draft));

        app()->call([new TranslateContentJob('page', $page->id, 'en', null, true), 'handle']);

        $this->assertSame('published', $draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->published_at);
    }

    public function test_requires_openai_key(): void
    {
        $this->mock(TextTranslator::class, fn (MockInterface $mock) => $mock->shouldReceive('configured')->andReturn(false));

        $this->actingAs($this->makeAdmin())->postJson('/gestao/api/translations/all')->assertStatus(422);
    }
}
