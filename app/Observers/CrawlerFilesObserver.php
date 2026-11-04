<?php

namespace App\Observers;

use App\Services\CrawlerFiles;
use Throwable;

class CrawlerFilesObserver
{
    public function saved(): void
    {
        $this->regenerate();
    }

    public function deleted(): void
    {
        $this->regenerate();
    }

    public function restored(): void
    {
        $this->regenerate();
    }

    private function regenerate(): void
    {
        try {
            app(CrawlerFiles::class)->write();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
