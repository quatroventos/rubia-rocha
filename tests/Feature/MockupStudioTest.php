<?php

namespace Tests\Feature;

use App\Services\Mockup\DeviceScreenshot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MockupStudioTest extends TestCase
{
    public function test_studio_returns_404_outside_local(): void
    {
        Http::preventStrayRequests();

        $this->get('/ferramentas/mockups')->assertNotFound();
        $this->post('/ferramentas/mockups/gerar')->assertNotFound();
        $this->post('/ferramentas/mockups/avancar')->assertNotFound();
        $this->post('/ferramentas/mockups/capturar')->assertNotFound();
    }

    public function test_local_studio_renders_photo_and_vertical_crop_sliders(): void
    {
        config(['mockup.studio' => true]);

        $this->get('/ferramentas/mockups')
            ->assertOk()
            ->assertSee('Mockup studio')
            ->assertSee('Gerar foto')
            ->assertSee('Capturar tela')
            ->assertSee('Studio Display')
            ->assertSee('id="crop-start"', false)
            ->assertSee('id="crop-end"', false)
            ->assertSee('Os sliders cortam só na vertical.')
            ->assertDontSee('Animar mockup')
            ->assertDontSee('Detectar tela');
    }

    public function test_animation_controls_render_when_the_runway_key_is_set(): void
    {
        config([
            'mockup.studio' => true,
            'services.runway.key' => 'test-key',
        ]);

        $this->get('/ferramentas/mockups')
            ->assertOk()
            ->assertSee('Animar mockup');
    }

    public function test_generate_returns_422_when_payload_is_empty(): void
    {
        config(['mockup.studio' => true]);

        $this->postJson('/ferramentas/mockups/gerar', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'device' => 'Escolha o dispositivo.',
                'angle' => 'Escolha o ângulo.',
                'scene' => 'Escolha o cenário.',
                'size' => 'Escolha o tamanho.',
                'quality' => 'Escolha a qualidade.',
                'references' => 'Escolha ao menos uma referência.',
            ]);
    }

    public function test_generate_returns_422_for_unknown_reference(): void
    {
        config(['mockup.studio' => true]);

        $this->postJson('/ferramentas/mockups/gerar', [
            ...$this->payload(),
            'references' => ['inexistente'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['references.0' => 'Referência inválida.']);
    }

    public function test_generate_returns_422_when_openai_key_is_missing(): void
    {
        config([
            'mockup.studio' => true,
            'services.meta.key' => '',
        ]);

        $this->postJson('/ferramentas/mockups/gerar', $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'META_API_KEY não está no .env.');
    }

    public function test_generate_returns_image_from_reference_edit(): void
    {
        config([
            'mockup.studio' => true,
            'services.meta.key' => 'test-key',
            'services.meta.base_url' => 'https://api.meta.ai/v1',
            'services.meta.image_model' => 'muse-image-1.0',
        ]);

        Http::fake([
            'https://api.meta.ai/v1/images/edits' => Http::response([
                'data' => [['b64_json' => base64_encode('png-bytes')]],
            ]),
        ]);

        $this->postJson('/ferramentas/mockups/gerar', $this->payload())
            ->assertOk()
            ->assertJsonPath('image', 'data:image/png;base64,'.base64_encode('png-bytes'));

        Http::assertSent(function ($request): bool {
            $body = $request->body();

            return $request->url() === 'https://api.meta.ai/v1/images/edits'
                && str_contains($body, '#FF00FF')
                && str_contains($body, 'dynamic island')
                && str_contains($body, 'tripfy.jpg')
                && str_contains($body, 'muse-image-1.0');
        });
    }

    public function test_studio_display_prompt_keeps_the_reference_hardware(): void
    {
        config([
            'mockup.studio' => true,
            'services.meta.key' => 'test-key',
            'services.meta.base_url' => 'https://api.meta.ai/v1',
            'services.meta.image_model' => 'muse-image-1.0',
        ]);

        Http::fake([
            'https://api.meta.ai/v1/images/edits' => Http::response([
                'data' => [['b64_json' => base64_encode('png-bytes')]],
            ]),
        ]);

        $this->postJson('/ferramentas/mockups/gerar', [
            ...$this->payload(),
            'device' => 'studio-display',
            'size' => '2048x1152',
            'references' => ['afcuritiba'],
        ])->assertOk();

        Http::assertSent(function ($request): bool {
            $body = $request->body();

            return str_contains($body, 'flat rectangular panel')
                && str_contains($body, 'Not a curved iMac arm')
                && str_contains($body, 'afcuritiba.jpg')
                && str_contains($body, '#FF00FF');
        });
    }

    public function test_advance_returns_the_next_photo_of_the_same_mockup(): void
    {
        config([
            'mockup.studio' => true,
            'services.meta.key' => 'test-key',
            'services.meta.base_url' => 'https://api.meta.ai/v1',
            'services.meta.image_model' => 'muse-image-1.0',
        ]);

        Http::fake([
            'https://api.meta.ai/v1/images/edits' => Http::response([
                'data' => [['b64_json' => base64_encode('next-frame')]],
            ]),
        ]);

        $this->post('/ferramentas/mockups/avancar', [
            'frame' => UploadedFile::fake()->image('frame.png', 32, 32),
            'motion' => 'open',
            'device' => 'macbook',
            'size' => '2048x1152',
            'quality' => 'high',
            'step' => 2,
            'steps' => 6,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('image', 'data:image/png;base64,'.base64_encode('next-frame'));

        Http::assertSent(function ($request): bool {
            $body = $request->body();

            return str_contains($request->url(), 'api.meta.ai')
                && str_contains($body, 'Open the laptop lid a little more')
                && str_contains($body, '#FF00FF')
                && str_contains($body, 'frame.png');
        });
    }

    public function test_capture_returns_422_when_the_url_is_missing(): void
    {
        config(['mockup.studio' => true]);

        $this->postJson('/ferramentas/mockups/capturar', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'url' => 'Informe a URL.',
                'device' => 'Escolha o dispositivo.',
            ]);
    }

    public function test_capture_rejects_a_non_http_url(): void
    {
        config(['mockup.studio' => true]);

        $this->postJson('/ferramentas/mockups/capturar', [
            'url' => 'file:///etc/passwd',
            'device' => 'iphone',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['url' => 'URL inválida.']);
    }

    public function test_capture_returns_422_when_chrome_is_missing(): void
    {
        config([
            'mockup.studio' => true,
            'services.mockup.chrome_binary' => '/tmp/chrome-inexistente',
        ]);

        $this->postJson('/ferramentas/mockups/capturar', [
            'url' => 'https://exemplo.test',
            'device' => 'iphone',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Não achei o Chrome para capturar a página.');
    }

    public function test_capture_returns_the_device_screenshot(): void
    {
        config(['mockup.studio' => true]);

        $this->mock(DeviceScreenshot::class, function ($mock): void {
            $mock->shouldReceive('capture')
                ->once()
                ->with('https://exemplo.test/app', 'macbook', true)
                ->andReturn('png-bytes');
        });

        $this->postJson('/ferramentas/mockups/capturar', [
            'url' => 'https://exemplo.test/app',
            'device' => 'macbook',
        ])->assertOk()
            ->assertJsonPath('image', 'data:image/png;base64,'.base64_encode('png-bytes'))
            ->assertJsonPath('width', 3024)
            ->assertJsonPath('height', 1964);
    }

    public function test_capture_can_skip_the_popup_cleanup(): void
    {
        config(['mockup.studio' => true]);

        $this->mock(DeviceScreenshot::class, function ($mock): void {
            $mock->shouldReceive('capture')
                ->once()
                ->with('https://exemplo.test/app', 'iphone', false)
                ->andReturn('png-bytes');
        });

        $this->postJson('/ferramentas/mockups/capturar', [
            'url' => 'https://exemplo.test/app',
            'device' => 'iphone',
            'clean' => false,
        ])->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'device' => 'iphone',
            'angle' => 'standing',
            'scene' => 'rail',
            'size' => '1152x2048',
            'quality' => 'high',
            'references' => ['tripfy'],
            'note' => '',
        ];
    }
}
