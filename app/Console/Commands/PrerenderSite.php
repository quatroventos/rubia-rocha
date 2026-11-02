<?php

namespace App\Console\Commands;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Services\PublicContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class PrerenderSite extends Command
{
    protected $signature = 'site:prerender';

    protected $description = 'Gera o HTML público de cada URL publicada para o Blade servir sem React.';

    public function handle(PublicContent $content): int
    {
        $payloads = [];

        foreach (Page::query()->published()->orderBy('locale')->orderBy('slug')->get() as $page) {
            $payloads[] = [
                'page' => $content->page($page),
                'site' => $content->site($page->localeCode()),
            ];
        }

        foreach (CaseStudy::query()->published()->orderBy('locale')->orderBy('slug')->get() as $case) {
            $payloads[] = [
                'page' => $content->caseStudy($case),
                'site' => $content->site($case->localeCode()),
            ];
        }

        if ($payloads === []) {
            $this->error('Nenhuma URL publicada.');

            return self::FAILURE;
        }

        $file = storage_path('app/prerender-payloads.json');
        File::ensureDirectoryExists(dirname($file));
        File::put($file, json_encode($payloads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $process = new Process(['node', base_path('scripts/prerender.mjs'), $file], base_path(), null, null, 600);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error(trim($process->getErrorOutput()) ?: 'A geração do HTML falhou.');

            return self::FAILURE;
        }

        $this->pruneStale($payloads);

        return self::SUCCESS;
    }

    /**
     * Remove o HTML de URLs que deixaram de existir (slug renomeado, página despublicada ou apagada).
     * O nome do arquivo segue a mesma regra de scripts/prerender.mjs.
     *
     * @param  list<array{page: array<string, mixed>, site: mixed}>  $payloads
     */
    private function pruneStale(array $payloads): void
    {
        $root = storage_path('app/prerender');
        $expected = [];

        foreach ($payloads as $payload) {
            $path = trim((string) ($payload['page']['path'] ?? '/'), '/');
            $locale = (string) ($payload['page']['locale'] ?? '') ?: 'pt-BR';
            $expected[$root.'/'.$locale.'/'.($path === '' ? 'index.html' : $path.'.html')] = true;
        }

        $removed = 0;

        foreach (File::allFiles($root) as $file) {
            if ($file->getExtension() === 'html' && ! isset($expected[$file->getPathname()])) {
                File::delete($file->getPathname());
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->line($removed.' arquivo(s) antigo(s) removido(s)');
        }
    }
}
