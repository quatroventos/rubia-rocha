<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class CmsTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXP';

    private function otp(string $secret = self::SECRET): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    private function wrongOtp(string $secret = self::SECRET): string
    {
        return str_pad((string) (((int) $this->otp($secret) + 1) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function login(string $email): void
    {
        $this->postJson('/gestao/api/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertOk();
    }

    public function test_first_login_requires_setting_up_an_authenticator(): void
    {
        $this->seedRoles();
        $user = User::factory()->withoutTwoFactor()->create(['email' => 'novo@example.com']);
        $user->syncRoles(['user']);

        $response = $this->postJson('/gestao/api/login', [
            'email' => 'novo@example.com',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('status', 'two_factor_setup');

        $secret = $response->json('secret');
        $this->assertStringStartsWith('otpauth://totp/', $response->json('otpauth'));

        $this->postJson('/gestao/api/login/two-factor', ['code' => $this->wrongOtp($secret)])->assertStatus(422);
        $this->assertGuest();
        $this->assertNull($user->fresh()->app_authentication_secret);

        $this->postJson('/gestao/api/login/two-factor', ['code' => $this->otp($secret)])
            ->assertOk()
            ->assertJsonPath('status', 'authenticated')
            ->assertJsonCount(8, 'recovery_codes');

        $this->assertAuthenticatedAs($user);
        $this->assertSame($secret, $user->fresh()->app_authentication_secret);
        $this->assertDatabaseHas('cms_actions', ['user_id' => $user->id, 'action' => 'two_factor.enabled']);
    }

    public function test_wrong_code_keeps_user_logged_out(): void
    {
        $user = $this->makeAdmin(['email' => 'root@example.com']);
        $this->login('root@example.com');

        $this->postJson('/gestao/api/login/two-factor', ['code' => $this->wrongOtp()])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code' => 'Código inválido.']);

        $this->assertGuest();
        $this->assertDatabaseHas('cms_actions', ['user_id' => $user->id, 'action' => 'login.two_factor_failed']);
    }

    public function test_recovery_code_logs_in_only_once(): void
    {
        $twoFactor = app(TwoFactorService::class);
        $user = $this->makeAdmin([
            'email' => 'root@example.com',
            'app_authentication_recovery_codes' => $twoFactor->hashRecoveryCodes(['codigo1234', 'codigo5678']),
        ]);

        $this->login('root@example.com');
        $this->postJson('/gestao/api/login/two-factor', ['recovery_code' => 'codigo1234'])
            ->assertOk()
            ->assertJsonPath('user.recovery_codes_left', 1);
        $this->assertAuthenticatedAs($user);

        $this->postJson('/gestao/api/logout')->assertOk();

        $this->login('root@example.com');
        $this->postJson('/gestao/api/login/two-factor', ['recovery_code' => 'codigo1234'])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_too_many_wrong_codes_require_password_again(): void
    {
        $this->makeAdmin(['email' => 'root@example.com']);
        $this->login('root@example.com');

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/gestao/api/login/two-factor', ['code' => $this->wrongOtp()])
                ->assertJsonValidationErrors(['code' => 'Código inválido.']);
        }

        $this->postJson('/gestao/api/login/two-factor', ['code' => $this->wrongOtp()])
            ->assertJsonValidationErrors(['code' => 'Muitas tentativas. Entre novamente.']);

        $this->postJson('/gestao/api/login/two-factor', ['code' => $this->otp()])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_code_is_rejected_after_pending_login_expires(): void
    {
        $this->makeAdmin(['email' => 'root@example.com']);
        $this->login('root@example.com');

        $this->travel(11)->minutes();

        $this->postJson('/gestao/api/login/two-factor', ['code' => $this->otp()])
            ->assertJsonValidationErrors(['code' => 'Sessão de login expirada. Entre novamente.']);
        $this->assertGuest();
    }

    public function test_code_without_password_step_is_rejected(): void
    {
        $this->makeAdmin();

        $this->postJson('/gestao/api/login/two-factor', ['code' => $this->otp()])->assertStatus(422);
        $this->assertGuest();
    }

    public function test_session_of_user_without_two_factor_is_ended(): void
    {
        $this->seedRoles();
        $user = User::factory()->withoutTwoFactor()->create();
        $user->syncRoles(['root']);

        $this->actingAs($user)->getJson('/gestao/api/me')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_local_environment_logs_in_without_two_factor(): void
    {
        $this->app['env'] = 'local';
        $this->seedRoles();
        $user = User::factory()->withoutTwoFactor()->create(['email' => 'local@example.com']);
        $user->syncRoles(['root']);

        $this->withSession(['_token' => 'csrf-local'])->postJson('/gestao/api/login', [
            '_token' => 'csrf-local',
            'email' => 'local@example.com',
            'password' => 'password',
        ])->assertOk()->assertJsonPath('status', 'authenticated');

        $this->assertAuthenticatedAs($user);
        $this->getJson('/gestao/api/me')->assertOk();
    }

    public function test_root_can_impersonate_user_without_two_factor(): void
    {
        $root = $this->makeAdmin();
        $this->seedRoles();
        $target = User::factory()->withoutTwoFactor()->create();
        $target->syncRoles(['user']);

        $this->actingAs($root)->postJson('/gestao/api/users/'.$target->id.'/impersonate')->assertOk();

        $this->getJson('/gestao/api/me')->assertOk()->assertJsonPath('user.id', $target->id);
    }

    public function test_root_can_reset_another_users_two_factor(): void
    {
        $root = $this->makeAdmin();
        $target = $this->makeEditor(['email' => 'editor@example.com']);

        $this->actingAs($root)
            ->postJson('/gestao/api/users/'.$target->id.'/two-factor/reset')
            ->assertOk()
            ->assertJsonPath('data.two_factor', false);

        $this->assertNull($target->fresh()->app_authentication_secret);
        $this->assertDatabaseHas('cms_actions', ['user_id' => $root->id, 'action' => 'two_factor.reset']);
    }

    public function test_editor_cannot_reset_two_factor(): void
    {
        $editor = $this->makeEditor();
        $other = $this->makeEditor(['email' => 'other@example.com']);

        $this->actingAs($editor)
            ->postJson('/gestao/api/users/'.$other->id.'/two-factor/reset')
            ->assertForbidden();

        $this->assertNotNull($other->fresh()->app_authentication_secret);
    }

    public function test_root_cannot_reset_own_two_factor(): void
    {
        $root = $this->makeAdmin();

        $this->actingAs($root)
            ->postJson('/gestao/api/users/'.$root->id.'/two-factor/reset')
            ->assertForbidden();
    }

    public function test_regenerating_recovery_codes_requires_current_password(): void
    {
        $twoFactor = app(TwoFactorService::class);
        $user = $this->makeEditor([
            'app_authentication_recovery_codes' => $twoFactor->hashRecoveryCodes(['antigo12345']),
        ]);

        $this->actingAs($user)
            ->postJson('/gestao/api/profile/recovery-codes', ['password' => 'errada'])
            ->assertJsonValidationErrors('password');

        $this->actingAs($user)
            ->postJson('/gestao/api/profile/recovery-codes', ['password' => 'password'])
            ->assertOk()
            ->assertJsonCount(8, 'recovery_codes');

        $this->assertFalse($twoFactor->consumeRecoveryCode($user->fresh(), 'antigo12345'));
    }

    public function test_reset_command_clears_two_factor(): void
    {
        $user = $this->makeAdmin(['email' => 'root@example.com']);

        $this->artisan('cms:reset-2fa', ['email' => 'root@example.com'])->assertSuccessful();

        $this->assertNull($user->fresh()->app_authentication_secret);
    }

    public function test_reset_command_fails_for_unknown_email(): void
    {
        $this->artisan('cms:reset-2fa', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
