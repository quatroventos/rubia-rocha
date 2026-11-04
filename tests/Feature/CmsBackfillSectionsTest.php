<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CmsBackfillSectionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Página como editada no CMS: título alterado, seção de fatos removida e lista sem o botão principal.
     */
    private function editedPage(): Page
    {
        $reference = json_decode(File::get(database_path('data/pages/quem-sou.json')), true);
        $sections = collect($reference['sections'])
            ->reject(fn (array $section) => $section['id'] === 'facts-3')
            ->map(function (array $section) {
                if ($section['id'] === 'about-2') {
                    $section['data']['title'] = 'Título editado no CMS';
                    unset($section['data']['primary_label']);
                }

                return $section;
            })
            ->values()
            ->all();

        return Page::query()->create([
            'slug' => 'quem-sou',
            'template' => 'custom',
            'status' => 'published',
            'seo_title' => 'Quem sou | Rubia Rocha Fotografia',
            'published_at' => now(),
            'content' => ['sections' => $sections],
        ]);
    }

    public function test_backfill_adds_missing_keys_and_sections_without_overwriting_content(): void
    {
        $page = $this->editedPage();

        $this->artisan('cms:backfill-sections', ['slug' => ['quem-sou']])->assertSuccessful();

        $sections = $page->fresh()->content['sections'];

        $this->assertSame(['trio-1', 'about-2', 'facts-3'], array_column($sections, 'id'));
        $this->assertSame('Título editado no CMS', $sections[1]['data']['title']);
        $this->assertArrayHasKey('primary_label', $sections[1]['data']);
        $this->assertSame(1, $page->revisions()->count());
    }

    public function test_dry_run_does_not_save(): void
    {
        $page = $this->editedPage();
        $before = $page->content;

        $this->artisan('cms:backfill-sections', ['slug' => ['quem-sou'], '--dry-run' => true])->assertSuccessful();

        $this->assertSame($before, $page->fresh()->content);
        $this->assertSame(0, $page->revisions()->count());
    }
}
