<?php

namespace App\Observers;

use App\Jobs\PrerenderPageJob;
use App\Jobs\PrerenderSiteJob;
use App\Models\Page;
use Illuminate\Database\Eloquent\Model;

class SitePrerenderObserver
{
    public function saved(Model $model): void
    {
        $this->queuePage($model);
        PrerenderSiteJob::schedule();
    }

    public function deleted(Model $model): void
    {
        PrerenderSiteJob::schedule();
    }

    public function restored(Model $model): void
    {
        $this->queuePage($model);
        PrerenderSiteJob::schedule();
    }

    private function queuePage(Model $model): void
    {
        if (! $model instanceof Page || $model->status !== 'published') {
            return;
        }

        if (! config('cms.prerender_on_save')) {
            return;
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests() && ! app()->runningConsoleCommand('queue:work', 'queue:listen')) {
            return;
        }

        PrerenderPageJob::dispatch($model->id);
    }
}
