<?php

namespace App\Observers;

use App\Jobs\GenerateOgImageJob;
use App\Models\CaseStudy;
use App\Models\Page;

class OgImageObserver
{
    public function created(Page|CaseStudy $model): void
    {
        GenerateOgImageJob::afterSave($model, true);
    }

    public function updated(Page|CaseStudy $model): void
    {
        GenerateOgImageJob::afterSave($model, false);
    }
}
