<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class MailSettings
{
    public function apply(?SiteSetting $settings = null): void
    {
        try {
            if (! Schema::hasTable('site_settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $settings ??= SiteSetting::query()->first();

        if ($settings === null || ! $settings->smtp_enabled || blank($settings->smtp_host)) {
            if ($settings !== null && ! $settings->smtp_enabled) {
                Config::set('mail.default', env('MAIL_MAILER', 'log'));
                try {
                    Mail::purge('smtp');
                } catch (\Throwable) {
                    //
                }
            }

            return;
        }

        $encryption = $settings->smtp_encryption;
        $port = (int) ($settings->smtp_port ?: ($encryption === 'ssl' ? 465 : 587));
        $scheme = match ($encryption) {
            'ssl' => 'smtps',
            'tls' => 'smtp',
            default => null,
        };

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'scheme' => $scheme,
            'url' => null,
            'host' => $settings->smtp_host,
            'port' => $port,
            'username' => $settings->smtp_username,
            'password' => $settings->smtp_password,
            'timeout' => null,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: null,
        ]);

        $fromAddress = $settings->smtp_from_address ?: $settings->email;
        $fromName = $settings->smtp_from_name ?: $settings->name;

        if (is_string($fromAddress) && filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            Config::set('mail.from.address', $fromAddress);
            Config::set('mail.from.name', $fromName ?: (string) config('app.name'));
        }

        try {
            Mail::purge('smtp');
        } catch (\Throwable) {
            //
        }
    }
}
