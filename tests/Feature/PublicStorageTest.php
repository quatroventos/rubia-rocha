<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageTest extends TestCase
{
    public function test_returns_file_stored_on_the_public_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('assets/logo.txt', 'marca-quatroventos');

        $response = $this->get('/storage/assets/logo.txt');

        $response->assertOk();
        $this->assertSame('marca-quatroventos', $response->streamedContent());
    }

    public function test_returns_404_when_public_file_is_missing(): void
    {
        Storage::fake('public');

        $this->get('/storage/assets/inexistente.txt')->assertNotFound();
    }

    public function test_returns_404_for_path_outside_the_public_disk(): void
    {
        Storage::fake('public');

        $outside = dirname(Storage::disk('public')->path('.')).'/secret.txt';
        file_put_contents($outside, 'segredo');

        try {
            $response = $this->get('/storage/%2e%2e/secret.txt');

            $response->assertNotFound();
            $this->assertStringNotContainsString('segredo', $response->getContent());
        } finally {
            @unlink($outside);
        }
    }

    public function test_returns_php_source_without_executing_it(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('upload.php', '<?php echo "executado";');

        $response = $this->get('/storage/upload.php');

        $response->assertOk();
        $this->assertSame('<?php echo "executado";', $response->streamedContent());
    }
}
