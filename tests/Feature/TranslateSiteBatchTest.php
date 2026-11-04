<?php

namespace Tests\Feature;

use App\Jobs\TranslateContentJob;
use App\Models\Page;
use App\Services\Translation\PageTranslator;
use App\Services\Translation\TextTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
