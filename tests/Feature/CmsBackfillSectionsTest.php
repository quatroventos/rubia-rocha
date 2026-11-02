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
     * Página como editada no CMS: título do hero alterado, FAQ removido e planos sem link_key.
     */
    private function editedPage(): Page
    {
        $reference = json_decode(File::get(database_path('data/pages/hospedagem-de-sites.json')), true);
        $sections = collect($reference['sections'])
            ->reject(fn (array $section) => $section['id'] === 'faq-1')
            ->map(function (array $section) {
                if ($section['id'] === 'hero-1') {
                    $section['data']['title'] = 'Título editado no CMS';
                }
                if ($section['id'] === 'pricing-1') {
                    foreach ($section['data']['categories'] as $index => $category) {
                        foreach ($category['plans'] as $planIndex => $plan) {
                            unset($section['data']['categories'][$index]['plans'][$planIndex]['link_key']);
                        }
                    }
                }

                return $section;
            })
            ->values()
            ->all();

        return Page::query()->create([
            'slug' => 'hospedagem-de-sites',
            'template' => 'custom',
            'status' => 'published',
            'seo_title' => 'Hospedagem de sites | Quatro Ventos',
            'published_at' => now(),
            'content' => ['sections' => $sections],
        ]);
    }

    public function test_backfill_adds_missing_keys_and_sections_without_overwriting_content(): void
    {
        $page = $this->editedPage();

        $this->artisan('cms:backfill-sections', ['slug' => ['hospedagem-de-sites']])->assertSuccessful();

        $sections = $page->fresh()->content['sections'];

        $this->assertSame(['hero-1', 'pricing-1', 'panel-1', 'examples-1', 'faq-1', 'panel-2'], array_column($sections, 'id'));
        $this->assertSame('Título editado no CMS', $sections[0]['data']['title']);
        $this->assertSame('sites-basic', $sections[1]['data']['categories'][0]['plans'][0]['link_key']);
        $this->assertSame(1, $page->revisions()->count());
    }

    public function test_dry_run_does_not_save(): void
    {
        $page = $this->editedPage();
        $before = $page->content;

        $this->artisan('cms:backfill-sections', ['slug' => ['hospedagem-de-sites'], '--dry-run' => true])->assertSuccessful();

        $this->assertSame($before, $page->fresh()->content);
        $this->assertSame(0, $page->revisions()->count());
    }

    public function test_row_with_different_identity_is_not_completed(): void
    {
        Page::query()->create([
            'slug' => 'hospedagem-de-sites',
            'template' => 'custom',
            'status' => 'published',
            'seo_title' => 'Hospedagem de sites | Quatro Ventos',
            'published_at' => now(),
            'content' => ['sections' => [
                ['id' => 'pricing-1', 'type' => 'site.pricing', 'data' => ['categories' => [
                    ['id' => 'sites', 'plans' => [['name' => 'Plano renomeado', 'href' => 'https://loja.example.com/x']]],
                ]]],
            ]],
        ]);

        $this->artisan('cms:backfill-sections', ['slug' => ['hospedagem-de-sites']])->assertSuccessful();

        $pricing = collect(Page::query()->first()->content['sections'])->firstWhere('id', 'pricing-1');

        $this->assertArrayNotHasKey('link_key', $pricing['data']['categories'][0]['plans'][0]);
    }
}
