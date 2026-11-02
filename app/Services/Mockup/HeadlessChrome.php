<?php

namespace App\Services\Mockup;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Chrome headless falando DevTools Protocol pelo pipe (fd 3/4), sem dependência externa.
 */
abstract class HeadlessChrome
{
    /** @var resource|null */
    private $writer = null;

    /** @var resource|null */
    private $reader = null;

    private string $buffer = '';

    private int $nextId = 0;

    protected float $deadline = 0;

    /** @var list<array<string, mixed>> */
    private array $events = [];

    /**
     * Abre um Chrome descartável, entrega a sessão da aba para $work e fecha tudo ao final.
     *
     * @template T
     *
     * @param  callable(string): T  $work
     * @return T
     */
    protected function withPage(int $width, int $height, int $seconds, callable $work): mixed
    {
        $binary = $this->chromeBinary();
        $directory = storage_path('app/mockup-captures');
        File::ensureDirectoryExists($directory);
        $profile = $directory.'/'.Str::uuid()->toString();
        File::ensureDirectoryExists($profile);

        $process = null;
        $this->deadline = microtime(true) + $seconds;
        $this->buffer = '';
        $this->events = [];

        try {
            $process = $this->launch($binary, $profile, $width, $height);

            $targets = $this->send('Target.getTargets');
            $page = collect($targets['targetInfos'] ?? [])->firstWhere('type', 'page');
            $targetId = $page['targetId'] ?? $this->send('Target.createTarget', ['url' => 'about:blank'])['targetId'];
            $session = $this->send('Target.attachToTarget', ['targetId' => $targetId, 'flatten' => true])['sessionId'];

            return $work($session);
        } finally {
            $this->shutdown($process);
            File::deleteDirectory($profile);
        }
    }

    /**
     * @return resource
     */
    private function launch(string $binary, string $profile, int $width, int $height)
    {
        $command = [
            $binary,
            '--headless=new',
            '--remote-debugging-pipe',
            '--disable-gpu',
            '--hide-scrollbars',
            '--mute-audio',
            '--no-first-run',
            '--no-default-browser-check',
            '--disable-extensions',
            '--disable-background-networking',
            '--disable-dev-shm-usage',
            '--disable-crash-reporter',
            '--disable-breakpad',
            '--disable-site-isolation-trials',
            '--disable-features=IsolateOrigins,site-per-process',
            '--crash-dumps-dir='.$profile,
            ...$this->sandboxFlags(),
            '--user-data-dir='.$profile,
            '--window-size='.$width.','.$height,
            'about:blank',
        ];

        $environment = array_merge(getenv(), [
            'HOME' => $profile,
            'XDG_CONFIG_HOME' => $profile,
            'XDG_CACHE_HOME' => $profile,
        ]);

        $process = proc_open($command, [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
            3 => ['pipe', 'r'],
            4 => ['pipe', 'w'],
        ], $pipes, null, $environment);

        if (! is_resource($process)) {
            throw new RuntimeException('Não consegui abrir o Chrome.');
        }

        $this->writer = $pipes[3];
        $this->reader = $pipes[4];
        stream_set_blocking($this->reader, false);

        return $process;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function send(string $method, array $params = [], ?string $session = null): array
    {
        $id = ++$this->nextId;
        $message = ['id' => $id, 'method' => $method, 'params' => (object) $params];

        if ($session !== null) {
            $message['sessionId'] = $session;
        }

        if (@fwrite($this->writer, json_encode($message, JSON_UNESCAPED_SLASHES)."\0") === false) {
            throw new RuntimeException('O Chrome fechou antes de terminar.');
        }

        while (true) {
            foreach ($this->read($this->deadline) as $payload) {
                if (($payload['id'] ?? null) !== $id) {
                    if (isset($payload['method'])) {
                        $this->events[] = $payload;
                    }

                    continue;
                }

                if (isset($payload['error'])) {
                    throw new RuntimeException('O Chrome recusou '.$method.': '.($payload['error']['message'] ?? 'erro').'.');
                }

                return $payload['result'] ?? [];
            }
        }
    }

    protected function waitFor(string $event, float $until): bool
    {
        while (true) {
            foreach ($this->events as $index => $payload) {
                if ($payload['method'] === $event) {
                    array_splice($this->events, $index, 1);

                    return true;
                }
            }

            if (microtime(true) >= $until) {
                return false;
            }

            foreach ($this->read($until, false) as $payload) {
                if (isset($payload['method'])) {
                    $this->events[] = $payload;
                }
            }
        }
    }

    protected function pump(float $until): void
    {
        while (microtime(true) < $until) {
            foreach ($this->read($until, false) as $payload) {
                if (isset($payload['method'])) {
                    $this->events[] = $payload;
                }
            }
        }

        $this->events = array_slice($this->events, -200);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function read(float $until, bool $strict = true): array
    {
        $remaining = $until - microtime(true);

        if ($remaining <= 0) {
            if ($strict) {
                throw new RuntimeException($this->timeoutMessage());
            }

            return [];
        }

        $read = [$this->reader];
        $write = null;
        $except = null;
        $wait = min($remaining, 0.25);

        if (@stream_select($read, $write, $except, (int) $wait, (int) (($wait - (int) $wait) * 1_000_000)) > 0) {
            $chunk = fread($this->reader, 1 << 20);

            if ($chunk === '' && feof($this->reader)) {
                throw new RuntimeException('O Chrome fechou antes de terminar.');
            }

            $this->buffer .= (string) $chunk;
        }

        $messages = [];

        while (($end = strpos($this->buffer, "\0")) !== false) {
            $decoded = json_decode(substr($this->buffer, 0, $end), true);
            $this->buffer = substr($this->buffer, $end + 1);

            if (is_array($decoded)) {
                $messages[] = $decoded;
            }
        }

        return $messages;
    }

    protected function missingChromeMessage(): string
    {
        return 'Não achei o Chrome no servidor.';
    }

    protected function timeoutMessage(): string
    {
        return 'O Chrome demorou demais para responder.';
    }

    /**
     * @param  resource|null  $process
     */
    private function shutdown($process): void
    {
        if (is_resource($this->writer)) {
            @fwrite($this->writer, json_encode(['id' => ++$this->nextId, 'method' => 'Browser.close'])."\0");
            fclose($this->writer);
        }

        if (is_resource($this->reader)) {
            fclose($this->reader);
        }

        $this->writer = null;
        $this->reader = null;

        if (! is_resource($process)) {
            return;
        }

        $until = microtime(true) + 3;

        while (proc_get_status($process)['running'] && microtime(true) < $until) {
            usleep(50_000);
        }

        if (proc_get_status($process)['running']) {
            proc_terminate($process, 9);
        }

        proc_close($process);
    }

    /**
     * No container o sandbox do Chromium falha: como root ele recusa, e como www-data faltam user namespaces.
     *
     * @return list<string>
     */
    private function sandboxFlags(): array
    {
        $isRoot = function_exists('posix_geteuid') && posix_geteuid() === 0;

        return $isRoot || is_file('/.dockerenv') ? ['--no-sandbox'] : [];
    }

    private function chromeBinary(): string
    {
        $configured = config('services.mockup.chrome_binary');

        if (is_string($configured) && $configured !== '') {
            if (! is_executable($configured)) {
                throw new RuntimeException($this->missingChromeMessage());
            }

            return $configured;
        }

        $candidates = [
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
        ];

        foreach ($candidates as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        throw new RuntimeException($this->missingChromeMessage());
    }
}
