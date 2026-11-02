<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class CmsAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_authenticates(): void
    {
        $this->makeAdmin([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/gestao/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('status', 'two_factor_challenge');

        $this->assertGuest();

        $response = $this->postJson('/gestao/api/login/two-factor', [
            'code' => app(Google2FA::class)->getCurrentOtp('JBSWY3DPEHPK3PXP'),
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonPath('user.role', 'root');

        $this->assertAuthenticated();
        $this->getJson('/gestao/api/me')->assertOk()->assertJsonPath('user.email', 'admin@example.com');
    }

    public function test_invalid_login_is_rejected(): void
    {
        $this->makeAdmin();

        $this->postJson('/gestao/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_login_is_throttled(): void
    {
        $this->makeAdmin();

        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/gestao/api/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong',
            ]);
        }

        $this->postJson('/gestao/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ])->assertStatus(429);
    }

    public function test_throttle_counts_each_route_separately(): void
    {
        $this->makeAdmin();

        for ($i = 0; $i < 9; $i++) {
            $this->postJson('/gestao/api/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong',
            ]);
        }

        $this->assertNotSame(429, $this->postJson('/gestao/api/login/two-factor', ['code' => '000000'])->status());
    }
}
