<?php

namespace App\Jobs;

use App\Models\Page;
use App\Services\Translation\PageTranslator;
use App\Services\Translation\SiteTranslator;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TranslateContentJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  'page'|'menus'  $kind
     */
    public function __construct(
        public string $kind,
        public ?int $id,
        public string $locale,
        public ?int $userId = null,
        public bool $publish = false,
    ) {}

    public static function statusKey(string $kind, ?int $id, string $locale): string
    {
        return 'cms:translate:'.$kind.':'.($id ?? 0).':'.$locale;
    }

    /**
     * @return array{state: string, message?: string, at?: string}|null
     */
    public static function status(string $kind, ?int $id, string $locale): ?array
    {
        $value = Cache::get(self::statusKey($kind, $id, $locale));

        return is_array($value) ? $value : null;
    }

    public static function queue(string $kind, ?int $id, string $locale, ?int $userId = null): void
    {
        Cache::put(self::statusKey($kind, $id, $locale), ['state' => 'running', 'at' => now()->toIso8601String()], now()->addHour());

        $job = new self($kind, $id, $locale, $userId);
        $connection = config('services.openai.queue_connection');

        if ($connection === 'sync') {
            dispatch_sync($job);

            return;
        }

        dispatch($job)->onConnection(is_string($connection) && $connection !== '' ? $connection : null);
    }

    public function handle(PageTranslator $pages, SiteTranslator $site): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $key = self::statusKey($this->kind, $this->id, $this->locale);

        try {
            $row = match ($this->kind) {
                'page' => $pages->translate(Page::query()->findOrFail($this->id), $this->locale, $this->userId),
                'menus' => [$site->menus($this->locale), $site->settings($this->locale)],
                default => null,
            };

            if ($this->publish && $row instanceof Page && $row->status !== 'published') {
                $row->forceFill(['status' => 'published', 'published_at' => $row->published_at ?? now()])->save();
            }

            Cache::put($key, ['state' => 'done', 'at' => now()->toIso8601String()], now()->addHour());
        } catch (Throwable $e) {
            Cache::put($key, ['state' => 'failed', 'message' => mb_substr($e->getMessage(), 0, 300), 'at' => now()->toIso8601String()], now()->addHour());

            if ($this->batchId !== null) {
                throw $e;
            }

            report($e);
        }
    }
}
