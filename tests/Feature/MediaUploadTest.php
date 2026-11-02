<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_svg_upload_is_sanitized_before_storage(): void
    {
        Storage::fake('public');

        $svg = <<<'SVG'
<?xml version="1.0"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10" onload="alert(1)">
  <style>@import url(https://evil.test/x.css); .a{fill:url(#g);background:url(https://evil.test/p.png)}</style>
  <script>alert(2)</script>
  <foreignObject><div>x</div></foreignObject>
  <a xlink:href="javascript:alert(3)"><path d="M0 0h10v10z"/></a>
  <use xlink:href="https://evil.test/s.svg#a"/>
  <use xlink:href="#shape"/>
  <path class="a" d="M0 0h5v5z" onclick="alert(4)" fill="#fff"/>
</svg>
SVG;

        $response = $this->actingAs($this->makeEditor())
            ->post('/gestao/api/media', [
                'kind' => 'image',
                'file' => UploadedFile::fake()->createWithContent('logo.svg', $svg),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $path = str_replace('/storage/', '', $response->json('path'));
        $stored = Storage::disk('public')->get($path);

        $this->assertStringEndsWith('.svg', $path);
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('onload', $stored);
        $this->assertStringNotContainsString('onclick', $stored);
        $this->assertStringNotContainsString('foreignObject', $stored);
        $this->assertStringNotContainsString('javascript', $stored);
        $this->assertStringNotContainsString('evil.test', $stored);
        $this->assertStringContainsString('xlink:href="#shape"', $stored);
        $this->assertStringContainsString('url(#g)', $stored);
        $this->assertStringContainsString('d="M0 0h5v5z"', $stored);
    }

    public function test_svg_with_entities_is_rejected(): void
    {
        Storage::fake('public');

        $svg = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>';

        $this->actingAs($this->makeEditor())
            ->post('/gestao/api/media', [
                'kind' => 'image',
                'file' => UploadedFile::fake()->createWithContent('logo.svg', $svg),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
