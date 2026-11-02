<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        SiteSetting::query()->create([
            'name' => 'Quatro Ventos',
            'og_locale' => 'pt_BR',
            'twitter_card' => 'summary_large_image',
        ]);

        Page::query()->create([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'published',
            'seo_title' => 'Home',
            'content' => [],
            'published_at' => now(),
        ]);

        $this->get('/')->assertOk();
    }
}
