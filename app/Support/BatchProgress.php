<?php

namespace App\Support;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * Lote de jobs do CMS com progresso consultável pelo painel. Guarda só o id do último lote de cada tipo.
 */
class BatchProgress
{
    /**
     * @param  list<object>  $jobs
     */
    public static function start(string $key, string $name, array $jobs, ?string $connection = null): ?Batch
    {
        if ($jobs === []) {
            Cache::forget(self::cacheKey($key));

            return null;
        }

        $pending = Bus::batch($jobs)->name($name)->allowFailures();
        if (is_string($connection) && $connection !== '') {
            $pending->onConnection($connection);
        }

        $batch = $pending->dispatch();
        Cache::put(self::cacheKey($key), $batch->id, now()->addDays(2));

        return $batch;
    }

    public static function current(string $key): ?Batch
    {
        $id = Cache::get(self::cacheKey($key));

        return is_string($id) ? Bus::findBatch($id) : null;
    }

    public static function running(string $key): bool
    {
        $batch = self::current($key);

        return $batch !== null && ! $batch->finished() && ! $batch->cancelled();
    }

    public static function cancel(string $key): void
    {
        self::current($key)?->cancel();
    }

    /**
     * @return array{state: string, total: int, processed: int, failed: int, progress: int, started_at: ?string, finished_at: ?string}
     */
    public static function status(string $key): array
    {
        $batch = self::current($key);

        if ($batch === null) {
            return ['state' => 'idle', 'total' => 0, 'processed' => 0, 'failed' => 0, 'progress' => 0, 'started_at' => null, 'finished_at' => null];
        }

        $state = match (true) {
            $batch->cancelled() => 'cancelled',
            $batch->finished() => 'done',
            default => 'running',
        };

        return [
            'state' => $state,
            'total' => $batch->totalJobs,
            'processed' => $batch->processedJobs(),
            'failed' => $batch->failedJobs,
            'progress' => $batch->progress(),
            'started_at' => $batch->createdAt->toIso8601String(),
            'finished_at' => $batch->finishedAt?->toIso8601String(),
        ];
    }

    private static function cacheKey(string $key): string
    {
        return 'cms:batch:'.$key;
    }
}
