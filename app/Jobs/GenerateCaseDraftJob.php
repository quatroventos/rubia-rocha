<?php

namespace App\Jobs;

use App\Services\Cases\CaseDraftWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class GenerateCaseDraftJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * @param  array{prompt: string, url?: string|null, prints?: bool, mockups?: bool, case: array<string, mixed>}  $input
     */
    public function __construct(public string $token, public array $input) {}

    public static function statusKey(string $token): string
    {
        return 'cms:case-draft:'.$token;
    }

    /**
     * @return array{state: string, user_id: int|null, message?: string, draft?: array<string, mixed>}|null
     */
    public static function status(string $token): ?array
    {
        $value = Cache::get(self::statusKey($token));

        return is_array($value) ? $value : null;
    }

    /**
     * @param  array{prompt: string, url?: string|null, prints?: bool, mockups?: bool, case: array<string, mixed>}  $input
     */
    public static function start(array $input, ?int $userId): string
    {
        $token = (string) Str::uuid();
        Cache::put(self::statusKey($token), ['state' => 'running', 'user_id' => $userId], now()->addHour());

        $job = new self($token, $input);
        $connection = config('services.openai.queue_connection');

        if ($connection === 'sync') {
            dispatch_sync($job);
        } else {
            dispatch($job)->onConnection(is_string($connection) && $connection !== '' ? $connection : null);
        }

        return $token;
    }

    public function handle(CaseDraftWriter $writer): void
    {
        $userId = self::status($this->token)['user_id'] ?? null;

        try {
            $draft = $writer->write($this->input);
            Cache::put(self::statusKey($this->token), ['state' => 'done', 'user_id' => $userId, 'draft' => $draft], now()->addHour());
        } catch (Throwable $e) {
            Cache::put(self::statusKey($this->token), ['state' => 'failed', 'user_id' => $userId, 'message' => mb_substr($e->getMessage(), 0, 300)], now()->addHour());

            report($e);
        }
    }

    /**
     * Só cobre o job morto por timeout: com retry_after menor que o timeout, a fila pode reentregar um job já concluído.
     */
    public function failed(?Throwable $exception): void
    {
        $status = self::status($this->token);

        if (($status['state'] ?? null) !== 'running') {
            return;
        }

        Cache::put(self::statusKey($this->token), [
            'state' => 'failed',
            'user_id' => $status['user_id'] ?? null,
            'message' => 'A geração passou do tempo limite. Tente com menos prints ou sem mockups.',
        ], now()->addHour());
    }
}
