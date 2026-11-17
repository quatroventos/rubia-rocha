<?php

namespace App\Jobs;

use App\Models\Page;
use App\Services\Seo\PageRenderer;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Atualiza o HTML público de uma página só, na hora do save.
 * O prerender completo do site continua agendado à parte para o restante das URLs.
 */
class PrerenderPageJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 120;

    public function __construct(public int $pageId) {}

    public function uniqueId(): string
    {
        return (string) $this->pageId;
    }

    public function handle(PageRenderer $renderer): void
    {
        $page = Page::query()->find($this->pageId);

        if ($page === null || $page->status !== 'published') {
            return;
        }

        $renderer->publish($page);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
