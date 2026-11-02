<?php

namespace Tests\Feature;

use App\Models\MediaOptimization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CaseMockupTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            if (is_file($path)) {
                File::delete($path);
            }
        }

        parent::tearDown();
    }

    public function test_guest_cannot_open_or_store_a_case_mockup(): void
    {
        $this->get('/ferramentas/mockups?case=1')->assertNotFound();

        $this->postJson('/gestao/api/cases/mockup')->assertUnauthorized();
    }

    public function test_editor_sees_the_cover_action_only_when_building_a_case(): void
    {
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->get('/ferramentas/mockups')
            ->assertOk()
            ->assertDontSee('Usar como capa');

        $this->actingAs($editor)
            ->get('/ferramentas/mockups?case=1&alt=Digio%3A+onboarding')
            ->assertOk()
            ->assertSee('Usar como capa')
            ->assertSee('gestao\\/api\\/cases\\/mockup', false)
            ->assertSee('Digio: onboarding', false);
    }

    public function test_editor_stores_an_optimized_webp_cover_with_alt(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD sem WebP.');
        }

        $png = $this->png();

        $response = $this->actingAs($this->makeEditor())
            ->post('/gestao/api/cases/mockup', [
                'image' => $png,
                'alt' => 'Digio: tela do onboarding no notebook',
            ]);

        $response->assertOk()
            ->assertJsonPath('alt', 'Digio: tela do onboarding no notebook');

        $path = $response->json('path');
        $this->assertIsString($path);
        $this->assertStringEndsWith('.webp', $path);

        $relative = ltrim((string) parse_url($path, PHP_URL_PATH), '/');
        $this->assertStringStartsWith('storage/optimized/', $relative);

        $absolute = storage_path('app/public/'.substr($relative, strlen('storage/')));
        $this->created[] = $absolute;
        $this->assertFileExists($absolute);
        $this->assertSame('RIFF', file_get_contents($absolute, false, null, 0, 4));

        $row = MediaOptimization::query()->where('status', MediaOptimization::STATUS_OPTIMIZED)->first();
        $this->assertNotNull($row);
        $this->assertModelExists($row);
        $this->created[] = storage_path('app/public/'.$row->source_path);
    }

    public function test_store_requires_the_image_and_the_alt(): void
    {
        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/cases/mockup', [
                'alt' => str_repeat('a', 256),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image', 'alt'])
            ->assertJsonPath('errors.image.0', 'Envie a imagem do mockup.')
            ->assertJsonPath('errors.alt.0', 'O alt passa de 255 caracteres.');
    }

    private function png(): UploadedFile
    {
        $image = imagecreatetruecolor(64, 40);
        $color = imagecolorallocate($image, 20, 40, 80);
        imagefilledrectangle($image, 0, 0, 63, 39, $color);
        $path = tempnam(sys_get_temp_dir(), 'mockup');
        imagepng($image, $path);
        imagedestroy($image);
        $this->created[] = $path;

        return new UploadedFile($path, 'mockup.png', 'image/png', null, true);
    }
}
