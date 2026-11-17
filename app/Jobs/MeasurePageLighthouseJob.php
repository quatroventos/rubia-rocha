<?php

namespace App\Jobs;

use App\Services\LighthouseService;
use App\Services\Seo\PageRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class MeasurePageLighthouseJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public string $path) {}

    public function handle(LighthouseService $lighthouse, PageRenderer $renderer): void
    {
        $page = $renderer->findPublished($this->path);

        if ($page !== null) {
            try {
                $renderer->publish($page);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $lighthouse->measurePage($this->path);
    }

    public function failed(?Throwable $exception): void
    {
        app(LighthouseService::class)->failPage($this->path, 'A medição passou do tempo limite. Tente de novo.');
    }
}
