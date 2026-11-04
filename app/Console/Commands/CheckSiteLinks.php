<?php

namespace App\Console\Commands;

use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Baixa o HTML público de cada página e case publicado (todos os idiomas) pelo endereço interno da aplicação e testa
 * os links, imagens e scripts encontrados. Bloqueios típicos de redes sociais (403, 429, 999) entram como aviso.
 */
class CheckSiteLinks extends Command
{
    protected $signature = 'site:check-links
        {--internal : Só links internos}
        {--base= : Endereço interno da aplicação (padrão: OG_CAPTURE_URL)}
        {--timeout=12 : Tempo limite por link externo, em segundos}';

    protected $description = 'Verifica links quebrados no site público.';

    private const SOFT_STATUSES = [401, 403, 405, 429, 999];

    private const CONCURRENCY = 8;

    public function handle(): int
    {
        $base = rtrim((string) ($this->option('base') ?: config('cms.capture_url')), '/');
        $origins = array_values(array_unique(array_filter([
            $base,
            rtrim((string) config('app.url'), '/'),
            'https://www.rubiarochafotografia.com.br',
            'https://rubiarochafotografia.com.br',
        ])));

        $sources = Page::query()->published()->get()->map(fn (Page $page) => $page->publicPath())->all();

        /** @var array<string, list<string>> $internal */
        $internal = [];
        /** @var array<string, list<string>> $external */
        $external = [];
        $broken = [];

        $this->info('Lendo '.count($sources).' páginas em '.$base);

        foreach ($sources as $path) {
            $response = $this->fetch($base.$path);

            if ($response === null || ! $response->successful()) {
                $broken[] = [$path, '(a própria página)', $response?->status() ?? 'erro'];

                continue;
            }

            foreach ($this->links($response->body()) as $link) {
                $local = $this->localPath($link, $origins);

                if ($local !== null) {
                    $internal[$local][] = $path;
                } elseif (preg_match('#^https?://#i', $link) === 1) {
                    $external[$link][] = $path;
                }
            }
        }

        $this->info('Testando '.count($internal).' links internos');

        foreach (array_keys($internal) as $link) {
            $response = $this->fetch($base.$link);

            if ($response === null || $response->status() >= 400) {
                foreach (array_unique($internal[$link]) as $source) {
                    $broken[] = [$source, $link, $response?->status() ?? 'erro'];
                }
            }
        }

        $warnings = [];

        if (! $this->option('internal')) {
            $this->info('Testando '.count($external).' links externos');

            foreach (array_chunk(array_keys($external), self::CONCURRENCY) as $chunk) {
                $responses = Http::pool(fn (Pool $pool) => array_map(
                    fn (string $url) => $pool->as($url)
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; QuatroVentosLinkCheck/1.0)'])
                        ->timeout((int) $this->option('timeout'))
                        ->withOptions(['allow_redirects' => ['max' => 5]])
                        ->get($url),
                    $chunk,
                ));

                foreach ($chunk as $url) {
                    $response = $responses[$url] ?? null;
                    $status = $response instanceof Response ? $response->status() : 'erro';

                    if ($status !== 'erro' && $status < 400) {
                        continue;
                    }

                    foreach (array_unique($external[$url]) as $source) {
                        if (in_array($status, self::SOFT_STATUSES, true)) {
                            $warnings[] = [$source, $url, $status];
                        } else {
                            $broken[] = [$source, $url, $status];
                        }
                    }
                }
            }
        }

        if ($warnings !== []) {
            $this->warn('Avisos (o site bloqueia robôs; confira à mão):');
            $this->table(['Link', 'Status', 'Páginas'], $this->grouped($warnings));
        }

        if ($broken === []) {
            $this->info('Nenhum link quebrado.');

            return self::SUCCESS;
        }

        $rows = $this->grouped($broken);
        $this->error(count($rows).' link(s) quebrado(s):');
        $this->table(['Link', 'Status', 'Páginas'], $rows);

        return self::FAILURE;
    }

    private function fetch(string $url): ?Response
    {
        try {
            return Http::timeout(20)->withOptions(['allow_redirects' => ['max' => 5]])->get($url);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function links(string $html): array
    {
        preg_match_all('/<(?:a|link)\b[^>]*\bhref\s*=\s*"([^"]*)"/i', $html, $hrefs);
        preg_match_all('/<(?:img|script|source|video)\b[^>]*\bsrc\s*=\s*"([^"]*)"/i', $html, $srcs);

        $links = [];

        foreach ([...$hrefs[1], ...$srcs[1]] as $link) {
            $link = html_entity_decode(trim($link), ENT_QUOTES | ENT_HTML5);

            if ($link === '' || preg_match('#^(\#|mailto:|tel:|javascript:|data:|whatsapp:)#i', $link) === 1 || $this->isDevServer($link)) {
                continue;
            }

            $links[] = $link;
        }

        return array_values(array_unique($links));
    }

    /**
     * Assets do Vite em modo dev não existem em produção.
     */
    private function isDevServer(string $link): bool
    {
        $hot = is_file(public_path('hot')) ? rtrim(trim((string) file_get_contents(public_path('hot'))), '/') : '';

        return ($hot !== '' && str_starts_with($link, $hot.'/')) || preg_match('#^https?://[^/]+:5173/#', $link) === 1;
    }

    /**
     * @param  list<array{0: string, 1: string, 2: int|string}>  $rows
     * @return list<array{0: string, 1: int|string, 2: string}>
     */
    private function grouped(array $rows): array
    {
        $groups = [];

        foreach ($rows as [$source, $link, $status]) {
            $groups[$link]['status'] = $status;
            $groups[$link]['pages'][] = $source;
        }

        return array_map(function (string $link) use ($groups): array {
            $pages = array_values(array_unique($groups[$link]['pages']));
            $shown = implode(', ', array_slice($pages, 0, 3)).(count($pages) > 3 ? ' +'.(count($pages) - 3) : '');

            return [$link, $groups[$link]['status'], $shown];
        }, array_keys($groups));
    }

    /**
     * @param  list<string>  $origins
     */
    private function localPath(string $link, array $origins): ?string
    {
        foreach ($origins as $origin) {
            if (stripos($link, $origin.'/') === 0 || strcasecmp($link, $origin) === 0) {
                $link = substr($link, strlen($origin)) ?: '/';

                break;
            }
        }

        if (! str_starts_with($link, '/') || str_starts_with($link, '//')) {
            return null;
        }

        return strtok($link, '#') ?: '/';
    }
}
