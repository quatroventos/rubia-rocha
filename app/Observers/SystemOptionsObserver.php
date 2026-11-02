<?php

namespace App\Observers;

use App\Services\SystemOptions;

class SystemOptionsObserver
{
    public function __construct(private SystemOptions $options) {}

    public function saved(): void
    {
        $this->options->flush();
    }

    public function deleted(): void
    {
        $this->options->flush();
    }
}
