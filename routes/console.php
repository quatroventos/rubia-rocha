<?php

use App\Services\BackupService;
use App\Services\LighthouseService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('lighthouse:refresh', function (LighthouseService $lighthouse) {
    $snapshot = $lighthouse->refresh();
    $this->info('Lighthouse: '.$snapshot['status']);
})->purpose('Atualiza a saúde Lighthouse do site público');

Artisan::command('backup:run', function (BackupService $backups) {
    $backup = $backups->run(null, 'schedule');

    if ($backup->status !== 'ok') {
        $this->error($backup->error ?: 'Backup falhou.');

        return 1;
    }

    $this->info('Backup criado: '.$backup->filename);
})->purpose('Gera o backup diário do banco e dos arquivos do site');

Schedule::command('lighthouse:refresh')->dailyAt('06:15');
Schedule::command('backup:run')->everyMinute()->when(function () {
    return now()->format('H:i') === sprintf('%02d:00', app(BackupService::class)->hour());
});
