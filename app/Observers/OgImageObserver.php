<?php

namespace App\Observers;

use App\Jobs\GenerateOgImageJob;
use App\Models\Page;

class OgImageObserver
{
    public function created(Page $model): void
    {
        GenerateOgImageJob::afterSave($model, true);
    }

    public function updated(Page $model): void
    {
        GenerateOgImageJob::afterSave($model, false);
    }
}
