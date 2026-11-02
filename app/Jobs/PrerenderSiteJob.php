<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Regenera o HTML público depois de alterações no CMS.
 * O lock único agrupa saves seguidos num job só; ele é liberado quando o job começa,
 * para que um save feito durante a geração agende outra rodada.
 */
class PrerenderSiteJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    private const DELAY_SECONDS = 5;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public static function schedule(): void
    {
        if (! config('cms.prerender_on_save')) {
            return;
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests() && ! app()->runningConsoleCommand('queue:work', 'queue:listen')) {
            return;
        }

        static::dispatch()->delay(now()->addSeconds(self::DELAY_SECONDS));
    }

    public function handle(): void
    {
        $exitCode = Artisan::call('site:prerender');

        if ($exitCode !== 0) {
            throw new RuntimeException('site:prerender falhou: '.trim(Artisan::output()));
        }
    }
}
