<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\ImageOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MediaOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private string $assetsDir;

    private string $storageDir;

    /**
     * The service scans storage/app/public, so it runs against a throwaway
     * storage root instead of the real uploads.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->storageDir = sys_get_temp_dir().'/media-optimization-'.uniqid();
        $this->app->useStoragePath($this->storageDir);

        $this->assetsDir = storage_path('app/public/assets');
        File::ensureDirectoryExists($this->assetsDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageDir);

        parent::tearDown();
    }

    public function test_guest_cannot_list_media_optimizations(): void
    {
        $this->getJson('/gestao/api/media-optimizations')->assertStatus(401);
    }

    public function test_editor_can_optimize_revert_and_media_helper_serves_webp(): void
    {
        $this->makeSettings();
        $this->makeJpeg('hero.jpg', 1200, 800);

        $this->actingAs($this->makeEditor())
            ->getJson('/gestao/api/media-optimizations')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.items.0.path', 'assets/hero.jpg')
            ->assertJsonPath('data.items.0.status', 'pending');

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/media-optimizations/optimize', [
                'paths' => ['assets/hero.jpg'],
                'quality' => 90,
                'max_width' => 800,
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 1)
            ->assertJsonPath('data.items.0.status', 'optimized')
            ->assertJsonPath('data.items.0.width', 800);

        $this->assertFileExists(storage_path('app/public/assets/hero.jpg'));
        $this->assertFileExists(storage_path('app/public/optimized/assets/hero.webp'));

        $originalSize = filesize(storage_path('app/public/assets/hero.jpg'));
        $optimizedSize = filesize(storage_path('app/public/optimized/assets/hero.webp'));
        $this->assertNotFalse($originalSize);
        $this->assertNotFalse($optimizedSize);
        $this->assertLessThan($originalSize, $optimizedSize);

        $url = media('/storage/assets/hero.jpg');
        $this->assertStringContainsString('/storage/optimized/assets/hero.webp', $url);

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/media-optimizations/revert', [
                'paths' => ['assets/hero.jpg'],
            ])
            ->assertOk()
            ->assertJsonPath('data.reverted', 1)
            ->assertJsonPath('data.items.0.status', 'pending');

        $this->assertFileDoesNotExist(storage_path('app/public/optimized/assets/hero.webp'));
        $this->assertStringContainsString('/storage/assets/hero.jpg', media('/storage/assets/hero.jpg'));
    }

    public function test_admin_can_update_optimization_settings(): void
    {
        $this->makeSettings();

        $this->actingAs($this->makeAdmin())
            ->putJson('/gestao/api/media-optimizations/settings', [
                'quality' => 85,
                'max_width' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.settings.quality', 85)
            ->assertJsonPath('data.settings.max_width', null);

        $this->assertSame(85, app(ImageOptimizationService::class)->settings()['quality']);
        $this->assertNull(app(ImageOptimizationService::class)->settings()['max_width']);
    }

    public function test_optimize_without_webp_records_errors_and_audit_for_root(): void
    {
        if (function_exists('imagewebp')) {
            $this->markTestSkipped('Este PHP tem WebP; o cenário de falha cobre ambientes Docker sem a extensão.');
        }

        $this->makeSettings();
        $this->makeJpeg('no-webp.jpg', 200, 120);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->postJson('/gestao/api/media-optimizations/optimize', [
                'paths' => ['assets/no-webp.jpg'],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 0)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.capabilities.webp', false)
            ->assertJsonPath('data.items.0.status', 'error');

        $this->assertDatabaseHas('cms_actions', [
            'action' => 'media.optimize.error',
            'user_id' => $admin->id,
        ]);
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

    private function makeJpeg(string $name, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 220, 90, 70);
        imagefilledrectangle($image, 0, 0, $width, $height, $color);
        imagejpeg($image, $this->assetsDir.'/'.$name, 95);
        imagedestroy($image);
    }
}
