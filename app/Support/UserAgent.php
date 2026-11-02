<?php

namespace App\Support;

class UserAgent
{
    /**
     * @return array{device: string, browser: string, os: string}
     */
    public static function parse(?string $ua): array
    {
        $ua = $ua ?? '';

        return [
            'device' => self::device($ua),
            'browser' => self::browser($ua),
            'os' => self::os($ua),
        ];
    }

    public static function isBot(?string $ua): bool
    {
        if ($ua === null || trim($ua) === '') {
            return true;
        }

        return (bool) preg_match(
            '/bot|crawl|spider|slurp|facebookexternalhit|preview|lighthouse|headless|pingdom|gtmetrix|curl|wget|python-requests|httpclient/i',
            $ua
        );
    }

    private static function device(string $ua): string
    {
        if (preg_match('/iPad|Tablet|PlayBook|Nexus 7/i', $ua)) {
            return 'tablet';
        }

        if (preg_match('/Mobile|Android|iPhone|iPod|webOS|BlackBerry/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    private static function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $ua) => 'Opera',
            str_contains($ua, 'Chrome/') && ! str_contains($ua, 'Chromium') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome') => 'Safari',
            default => 'Outro',
        };
    }

    private static function os(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/Mac OS X|Macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'iOS',
            (bool) preg_match('/Linux/i', $ua) => 'Linux',
            default => 'Outro',
        };
    }
}
