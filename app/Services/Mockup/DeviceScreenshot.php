<?php

namespace App\Services\Mockup;

use RuntimeException;

class DeviceScreenshot extends HeadlessChrome
{
    private const DEADLINE_SECONDS = 40;

    private const LOAD_TIMEOUT_SECONDS = 20;

    private const SETTLE_MS = 2500;

    public function capture(string $url, string $device, bool $clean = true): string
    {
        $viewport = MockupCatalog::viewports()[$device] ?? null;

        if ($viewport === null) {
            throw new RuntimeException('Dispositivo inválido.');
        }

        return $this->withPage(
            $viewport['width'],
            $viewport['height'],
            self::DEADLINE_SECONDS,
            fn (string $session): string => $this->shoot($session, $url, $viewport, $clean),
        );
    }

    /**
     * @param  array{width: int, height: int, scale: int, user_agent: string}  $viewport
     */
    private function shoot(string $session, string $url, array $viewport, bool $clean): string
    {
        $mobile = str_contains($viewport['user_agent'], 'Mobile');

        $this->send('Page.enable', [], $session);
        $this->send('Emulation.setDeviceMetricsOverride', [
            'width' => $viewport['width'],
            'height' => $viewport['height'],
            'deviceScaleFactor' => $viewport['scale'],
            'mobile' => $mobile,
        ], $session);
        $this->send('Emulation.setTouchEmulationEnabled', ['enabled' => $mobile], $session);
        $this->send('Emulation.setUserAgentOverride', ['userAgent' => $viewport['user_agent']], $session);

        if ($clean) {
            $this->send('Page.addScriptToEvaluateOnNewDocument', [
                'source' => (string) file_get_contents(resource_path('mockup/capture-cleaner.js')),
            ], $session);
        }

        $navigation = $this->send('Page.navigate', ['url' => $url], $session);

        if (! empty($navigation['errorText'])) {
            throw new RuntimeException('Não consegui abrir a página: '.$navigation['errorText'].'.');
        }

        $this->waitFor('Page.loadEventFired', min($this->deadline - 8, microtime(true) + self::LOAD_TIMEOUT_SECONDS));
        $this->pump(microtime(true) + self::SETTLE_MS / 1000);

        if ($clean) {
            $this->send('Runtime.evaluate', ['expression' => 'window.__mockupSweep && window.__mockupSweep()'], $session);
            $this->pump(microtime(true) + 0.6);
        }

        $shot = $this->send('Page.captureScreenshot', ['format' => 'png', 'fromSurface' => true], $session);
        $bytes = base64_decode((string) ($shot['data'] ?? ''), true);

        if (! is_string($bytes) || ! str_starts_with($bytes, "\x89PNG")) {
            throw new RuntimeException('O Chrome não devolveu a print.');
        }

        return $bytes;
    }

    protected function missingChromeMessage(): string
    {
        return 'Não achei o Chrome para capturar a página.';
    }

    protected function timeoutMessage(): string
    {
        return 'A página demorou demais para carregar.';
    }
}
