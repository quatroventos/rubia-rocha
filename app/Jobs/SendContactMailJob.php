<?php

namespace App\Jobs;

use App\Mail\ContactMessageMail;
use App\Services\MailSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/**
 * E-mail de formulário enviado fora da requisição: o SMTP do CMS é aplicado no worker,
 * porque a configuração em runtime da requisição não chega até a fila.
 */
class SendContactMailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(
        public string $recipient,
        public ContactMessageMail $mail,
    ) {}

    public function handle(MailSettings $mailSettings): void
    {
        $mailSettings->apply();

        Mail::to($this->recipient)->send($this->mail);
    }
}
