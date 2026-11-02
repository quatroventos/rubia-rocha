<?php

namespace Tests\Feature;

use App\Services\LighthouseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CmsLighthouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_refresh_lighthouse(): void
    {
        $this->postJson('/gestao/api/dashboard/lighthouse')->assertStatus(401);
    }

    public function test_refresh_reads_pagespeed_for_mobile_and_desktop(): void
    {
        config(['cms.lighthouse_url' => 'https://quatroventos.com.br']);
        Cache::forget(LighthouseService::CACHE_KEY);

        Http::fake(function (Request $request) {
            $this->assertStringContainsString('pagespeedonline/v5/runPagespeed', $request->url());

            $mobile = str_contains($request->url(), 'strategy=mobile');

            return Http::response([
                'lighthouseResult' => [
                    'categories' => [
                        'performance' => ['score' => $mobile ? 0.72 : 0.94],
                        'accessibility' => ['score' => 0.91],
                        'best-practices' => ['score' => 0.83],
                        'seo' => ['score' => 1],
                    ],
                    'audits' => [
                        'first-contentful-paint' => ['numericValue' => 1100],
                        'largest-contentful-paint' => ['numericValue' => 2400],
                        'total-blocking-time' => ['numericValue' => 180],
                        'speed-index' => ['numericValue' => 3100],
                        'cumulative-layout-shift' => ['numericValue' => 0.012],
                    ],
                ],
            ]);
        });

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/dashboard/lighthouse')
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('mobile.performance', 72)
            ->assertJsonPath('desktop.performance', 94)
            ->assertJsonPath('mobile.seo', 100)
            ->assertJsonPath('desktop.metrics.lcp_ms', 2400)
            ->assertJsonPath('desktop.metrics.cls', 0.012);

        Http::assertSentCount(2);

        $this->actingAs($this->makeEditor())
            ->getJson('/gestao/api/dashboard')
            ->assertOk()
            ->assertJsonPath('lighthouse.status', 'ready')
            ->assertJsonPath('lighthouse.mobile.performance', 72);
    }
}
