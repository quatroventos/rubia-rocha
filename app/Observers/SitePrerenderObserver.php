<?php

namespace App\Observers;

use App\Jobs\PrerenderSiteJob;

class SitePrerenderObserver
{
    public function saved(): void
    {
        PrerenderSiteJob::schedule();
    }

    public function deleted(): void
    {
        PrerenderSiteJob::schedule();
    }

    public function restored(): void
    {
        PrerenderSiteJob::schedule();
    }
}
