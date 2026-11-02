<?php

namespace App\Services\Mockup;

use RuntimeException;

/**
 * Encaixa a print na tela magenta da foto do aparelho com o mesmo compositor do estúdio (public/tools/mockup-compose.js).
 */
class DeviceMockupComposer extends HeadlessChrome
{
    private const DEADLINE_SECONDS = 60;

    /**
     * Devolve o PNG composto, ou null quando a foto não tem tela magenta detectável.
     *
     * @param  array<string, int|float>  $finishing
     */
    public function compose(string $scenePng, string $printPng, array $finishing = []): ?string
    {
        $script = (string) file_get_contents(public_path('tools/mockup-compose.js'));

        return $this->withPage(1280, 800, self::DEADLINE_SECONDS, function (string $session) use ($script, $scenePng, $printPng, $finishing): ?string {
            $this->send('Runtime.evaluate', ['expression' => $script], $session);

            $expression = sprintf(
                'MockupCompose.render(%s, %s, %s)',
                json_encode('data:image/png;base64,'.base64_encode($scenePng)),
                json_encode('data:'.$this->mime($printPng).';base64,'.base64_encode($printPng)),
                json_encode((object) $finishing),
            );

            $result = $this->send('Runtime.evaluate', [
                'expression' => $expression,
                'awaitPromise' => true,
                'returnByValue' => true,
            ], $session);

            if (isset($result['exceptionDetails'])) {
                throw new RuntimeException('O compositor falhou: '.($result['exceptionDetails']['exception']['description'] ?? $result['exceptionDetails']['text'] ?? 'erro').'.');
            }

            $value = $result['result']['value'] ?? null;

            if ($value === null) {
                return null;
            }

            $bytes = is_string($value) && str_starts_with($value, 'data:image/png;base64,')
                ? base64_decode(substr($value, 22), true)
                : false;

            if (! is_string($bytes) || ! str_starts_with($bytes, "\x89PNG")) {
                throw new RuntimeException('O compositor não devolveu PNG.');
            }

            return $bytes;
        });
    }

    private function mime(string $binary): string
    {
        return match (true) {
            str_starts_with($binary, "\xFF\xD8") => 'image/jpeg',
            str_starts_with($binary, 'RIFF') => 'image/webp',
            default => 'image/png',
        };
    }

    protected function timeoutMessage(): string
    {
        return 'O compositor demorou demais.';
    }
}
