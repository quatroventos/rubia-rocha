<?php

namespace App\Jobs;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Services\Seo\OgImageCapture;
use App\Services\Seo\OgImageGenerator;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Gera o card de OG de uma página ou case. Roda isolado (ao salvar) ou dentro do lote "Gerar todas".
 */
class GenerateOgImageJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    private const SAVE_DELAY_SECONDS = 5;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * @param  'page'|'case'  $kind
     */
    public function __construct(
        public string $kind,
        public int $id,
    ) {}

    /**
     * Regera o card depois de um save, se a OG atual for gerada (ou vazia) e o conteúdo exibido nela mudou.
     */
    public static function afterSave(Page|CaseStudy $model, bool $created): void
    {
        if (! config('cms.og_on_save') || $model->status !== 'published' || ! self::isAutomatic($model->og_image)) {
            return;
        }

        $watched = $model instanceof Page
            ? ['content', 'seo_title', 'slug', 'status']
            : ['title', 'client', 'cover_path', 'slug', 'status'];

        if (! $created && ! $model->wasChanged($watched)) {
            return;
        }

        static::dispatch($model instanceof Page ? 'page' : 'case', (int) $model->id)
            ->delay(now()->addSeconds(self::SAVE_DELAY_SECONDS));
    }

    public static function isAutomatic(?string $path): bool
    {
        return blank($path) || OgImageCapture::isGenerated($path);
    }

    public function handle(OgImageGenerator $generator): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        match ($this->kind) {
            'page' => ($page = Page::query()->find($this->id)) ? $generator->page($page) : null,
            'case' => ($case = CaseStudy::query()->find($this->id)) ? $generator->caseStudy($case) : null,
            default => null,
        };
    }
}
