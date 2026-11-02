<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_smtp_settings_without_exposing_password(): void
    {
        $this->makeSettings();

        $this->actingAs($this->makeAdmin())
            ->putJson('/gestao/api/site', [
                'name' => 'Quatro Ventos',
                'smtp_enabled' => true,
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 587,
                'smtp_username' => 'user@example.com',
                'smtp_password' => 'secret-pass',
                'smtp_encryption' => 'tls',
                'smtp_from_address' => 'noreply@example.com',
                'smtp_from_name' => 'Quatro Ventos Site',
            ])
            ->assertOk()
            ->assertJsonMissingPath('data.smtp_password')
            ->assertJsonPath('data.smtp_password_set', true)
            ->assertJsonPath('data.smtp_host', 'smtp.example.com')
            ->assertJsonPath('data.smtp_enabled', true);

        $this->assertSame('secret-pass', SiteSetting::query()->first()->smtp_password);
    }

    public function test_smtp_settings_apply_to_mail_config(): void
    {
        $settings = $this->makeSettings([
            'smtp_enabled' => true,
            'smtp_host' => 'mail.example.com',
            'smtp_port' => 465,
            'smtp_username' => 'mailer',
            'smtp_password' => 'pw',
            'smtp_encryption' => 'ssl',
            'smtp_from_address' => 'from@example.com',
            'smtp_from_name' => 'From Name',
        ]);

        app(MailSettings::class)->apply($settings);

        $this->assertSame('smtp', Config::get('mail.default'));
        $this->assertSame('mail.example.com', Config::get('mail.mailers.smtp.host'));
        $this->assertSame(465, Config::get('mail.mailers.smtp.port'));
        $this->assertSame('smtps', Config::get('mail.mailers.smtp.scheme'));
        $this->assertSame('from@example.com', Config::get('mail.from.address'));
        $this->assertSame('From Name', Config::get('mail.from.name'));
    }

    public function test_blank_password_keeps_previous_value(): void
    {
        $this->makeSettings([
            'smtp_enabled' => true,
            'smtp_host' => 'smtp.example.com',
            'smtp_password' => 'keep-me',
        ]);

        $this->actingAs($this->makeAdmin())
            ->putJson('/gestao/api/site', [
                'name' => 'Quatro Ventos',
                'smtp_enabled' => true,
                'smtp_host' => 'smtp.example.com',
                'smtp_password' => '',
            ])
            ->assertOk();

        $this->assertSame('keep-me', SiteSetting::query()->first()->smtp_password);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSettings(array $overrides = []): SiteSetting
    {
        return SiteSetting::query()->create(array_merge([
            'name' => 'Quatro Ventos',
            'og_locale' => 'pt_BR',
            'twitter_card' => 'summary_large_image',
            'email' => 'adv@quatroventos.com.br',
        ], $overrides));
    }
}
