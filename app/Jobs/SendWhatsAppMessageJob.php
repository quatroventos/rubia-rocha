<?php

namespace App\Jobs;

use App\Services\WhatsApp\EvolutionClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(
        public string $number,
        public string $text,
    ) {}

    public function handle(): void
    {
        EvolutionClient::fromSettings()->sendText($this->number, $this->text);
    }
}
