<?php

namespace App\Services\Seo;

use App\Services\Mockup\HeadlessChrome;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Gera a OG image (1200x630) como card de marca: logo, foto principal da página,
 * título e URL.
 *
 * O payload (com o conteúdo ainda não salvo) fica num token de cache de vida curta; o Chrome do servidor abre a rota
 * interna /__og/{token} pelo endereço local da aplicação, sem depender de a página estar publicada.
 */
class OgImageCapture extends HeadlessChrome
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    /**
     * Caminho público das imagens geradas aqui. Fora dele, a OG foi enviada à mão.
     */
    public const PUBLIC_PREFIX = '/storage/media/og/';

    private const SCALE = 2;

    private const DEADLINE_SECONDS = 45;

    private const READY_SECONDS = 25;

    private const SETTLE_MS = 800;

    private const TOKEN_TTL_SECONDS = 300;

    private const HIDE_CSS = '*,*::before,*::after{animation-duration:0s!important;animation-delay:0s!important;transition:none!important;caret-color:transparent!important}';

    public static function tokenKey(string $token): string
    {
        return 'og-capture:'.$token;
    }

    public static function isGenerated(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, self::PUBLIC_PREFIX);
    }

    /**
     * Domínio exibido no rodapé do card (sem www).
     */
    public static function domain(): string
    {
        $configured = trim((string) config('cms.og_domain'));
        $host = $configured !== '' ? $configured : (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        return (string) preg_replace('/^www\./i', '', $host);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $sitePayload
     * @return string caminho público da imagem gerada (/storage/media/og/...)
     */
    public function capture(array $payload, array $sitePayload, string $name): string
    {
        $token = Str::random(40);
        Cache::put(self::tokenKey($token), [
            'payload' => $payload,
            'site' => $sitePayload,
            'og' => [
                'domain' => self::domain(),
                'origins' => array_values(array_unique(array_filter([rtrim((string) config('app.url'), '/'), rtrim(url('/'), '/')]))),
            ],
        ], self::TOKEN_TTL_SECONDS);

        try {
            $png = $this->withPage(
                self::WIDTH,
                self::HEIGHT,
                self::DEADLINE_SECONDS,
                fn (string $session): string => $this->shoot($session, rtrim((string) config('cms.capture_url'), '/').'/__og/'.$token),
            );
        } finally {
            Cache::forget(self::tokenKey($token));
        }

        return $this->store($png, $name);
    }

    /**
     * Apaga uma imagem gerada anteriormente; arquivos enviados à mão não são tocados.
     */
    public function discard(?string $path): void
    {
        if (self::isGenerated($path)) {
            Storage::disk('public')->delete(Str::after((string) $path, '/storage/'));
        }
    }

    private function shoot(string $session, string $url): string
    {
        $this->send('Page.enable', [], $session);
        $this->send('Emulation.setDeviceMetricsOverride', [
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'deviceScaleFactor' => self::SCALE,
            'mobile' => false,
        ], $session);
        $this->send('Emulation.setEmulatedMedia', [
            'features' => [['name' => 'prefers-reduced-motion', 'value' => 'reduce']],
        ], $session);

        $navigation = $this->send('Page.navigate', ['url' => $url], $session);

        if (! empty($navigation['errorText'])) {
            throw new RuntimeException('Não consegui abrir o card para gerar a OG image: '.$navigation['errorText'].'.');
        }

        $this->waitFor('Page.loadEventFired', min($this->deadline - 10, microtime(true) + 20));
        $this->send('Runtime.evaluate', [
            'expression' => 'document.head.insertAdjacentHTML("beforeend", '.json_encode('<style>'.$this->devFontsCss().self::HIDE_CSS.'</style>').');'
                .' Promise.all([document.fonts.load(\'650 80px "Host Grotesk"\'), document.fonts.load(\'500 22px "Host Grotesk"\'), document.fonts.load(\'400 18px "Martian Mono"\')])'
                .'.catch(() => null).then(() => document.fonts.ready)'
                .'.then(() => { document.documentElement.dataset.ogFonts = "1"; window.dispatchEvent(new Event("og:fonts")); return true; })',
            'awaitPromise' => true,
        ], $session);

        $until = min($this->deadline - 5, microtime(true) + self::READY_SECONDS);
        $ready = false;

        while (! $ready && microtime(true) < $until) {
            $this->pump(microtime(true) + 0.25);
            $check = $this->send('Runtime.evaluate', [
                'expression' => 'Boolean(document.querySelector(".og-card[data-ready]")) && [...document.images].every((image) => image.complete)',
                'returnByValue' => true,
            ], $session);
            $ready = ($check['result']['value'] ?? false) === true;
        }

        if (! $ready) {
            throw new RuntimeException('O card da OG image não renderizou a tempo.');
        }

        $this->pump(microtime(true) + self::SETTLE_MS / 1000);

        $shot = $this->send('Page.captureScreenshot', [
            'format' => 'png',
            'fromSurface' => true,
            'clip' => ['x' => 0, 'y' => 0, 'width' => self::WIDTH, 'height' => self::HEIGHT, 'scale' => 1],
        ], $session);
        $bytes = base64_decode((string) ($shot['data'] ?? ''), true);

        if (! is_string($bytes) || ! str_starts_with($bytes, "\x89PNG")) {
            throw new RuntimeException('O Chrome não devolveu a imagem.');
        }

        return $bytes;
    }

    /**
     * Com o Vite em dev, as fontes do CSS apontam para o endereço do Vite no host, fora do alcance do Chrome do container.
     * Nesse caso as @font-face do site entram embutidas na página de captura.
     */
    private function devFontsCss(): string
    {
        if (! is_file(public_path('hot'))) {
            return '';
        }

        preg_match_all('/@font-face\s*\{[^}]*font-family:\s*"([^"]+)"[^}]*url\("\.\.\/fonts\/([^"]+)"\)[^}]*font-weight:\s*([^;]+);/', (string) @file_get_contents(resource_path('css/site.css')), $faces, PREG_SET_ORDER);

        $css = '';

        foreach ($faces as [, $family, $file, $weight]) {
            $path = resource_path('fonts/'.basename($file));

            if (is_file($path)) {
                $css .= '@font-face{font-family:"'.$family.'";src:url(data:font/woff2;base64,'.base64_encode((string) file_get_contents($path)).') format("woff2");font-weight:'.trim($weight).';font-display:block}';
            }
        }

        return $css;
    }

    private function store(string $png, string $name): string
    {
        $source = imagecreatefromstring($png);

        if ($source === false) {
            throw new RuntimeException('Não consegui processar a imagem.');
        }

        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, imagesx($source), imagesy($source));

        ob_start();
        imagejpeg($canvas, null, 88);
        $jpeg = (string) ob_get_clean();

        $path = 'media/og/'.(Str::slug($name) ?: 'pagina').'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(4)).'.jpg';
        Storage::disk('public')->put($path, $jpeg);

        return '/storage/'.$path;
    }

    protected function missingChromeMessage(): string
    {
        return 'Não achei o Chrome no servidor para gerar a OG image.';
    }

    protected function timeoutMessage(): string
    {
        return 'O card da OG image demorou demais para carregar.';
    }
}
