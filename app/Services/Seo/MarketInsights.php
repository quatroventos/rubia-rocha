<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Dados de mercado para a palavra-chave de uma página: demanda, dificuldade, intenção e variações
 * (DataForSEO via OpenSEO, só com consultas pagas liberadas), top 10 do Google (OpenSEO pago) e
 * buscas reais do Search Console. Só `fetch()` consulta; o resto lê o cache de 30 dias.
 *
 * @phpstan-type Metrics array{keyword: string, volume: int|null, difficulty: int|null, intent: string|null, intent_code: string|null, cpc: float|null}
 * @phpstan-type Report array{status: string, paid: bool, keyword: string, path: string|null, url: string|null, fetched_at: string, metrics: Metrics|null, related: list<Metrics>, serp: list<array{position: int, title: string, url: string, description: string}>, own_position: int|null, search_console: list<array{query: string, clicks: int, impressions: int, ctr: float|null, position: float|null}>|null, errors: array<string, string>}
 */
class MarketInsights
{
    use ReadsOpenSeoData;

    public const TTL_DAYS = 30;

    public const RELATED_LIMIT = 6;

    public const SERP_LIMIT = 10;

    public const QUERIES_LIMIT = 10;

    public function __construct(private OpenSeoClient $client) {}

    /**
     * OpenSEO configurado: Search Console sempre e, se liberado, DataForSEO.
     */
    public function configured(): bool
    {
        return $this->client->configured();
    }

    public function paidEnabled(): bool
    {
        return (bool) config('services.openseo.paid') && $this->client->configured();
    }

    /**
     * @return Report|null
     */
    public function cached(string $keyword, ?string $path): ?array
    {
        if (trim($keyword) === '' || ! $this->configured()) {
            return null;
        }

        $report = Cache::get($this->key($keyword, $path));

        return is_array($report) ? $report : null;
    }

    /**
     * Consulta o OpenSEO e grava o relatório. Uma parte que falha não derruba as outras.
     *
     * @return Report
     */
    public function fetch(string $keyword, ?string $path): array
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            throw new RuntimeException('Defina a palavra-chave antes de buscar os dados.');
        }

        if (! $this->configured()) {
            throw new RuntimeException('OpenSEO não configurado. Preencha em SEO > Integrações.');
        }

        $paid = $this->paidEnabled();
        $url = $this->absoluteUrl($path);
        $errors = [];
        $metrics = null;
        $related = [];
        $serp = [];
        $queries = null;

        if ($paid) {
            try {
                $metrics = $this->metrics($keyword);
            } catch (Throwable $exception) {
                $errors['metrics'] = $this->message($exception);
            }

            try {
                $related = $this->related($keyword);
            } catch (Throwable $exception) {
                $errors['related'] = $this->message($exception);
            }
        }

        if ($paid) {
            try {
                $serp = $this->serp($keyword);
            } catch (Throwable $exception) {
                $errors['serp'] = $this->message($exception);
            }
        }

        if ($url !== null) {
            try {
                $queries = $this->searchConsole($url);
            } catch (Throwable $exception) {
                $errors['search_console'] = $this->message($exception);
            }
        }

        if ($metrics === null && $related === [] && $serp === [] && $queries === null) {
            throw new RuntimeException($errors !== [] ? reset($errors) : 'Nenhuma fonte devolveu dados. Com as consultas pagas desligadas, conecte o Search Console no OpenSEO.');
        }

        $report = [
            'status' => 'ready',
            'paid' => $paid,
            'keyword' => $keyword,
            'path' => $path,
            'url' => $url,
            'fetched_at' => now()->toIso8601String(),
            'metrics' => $metrics,
            'related' => $related,
            'serp' => $serp,
            'own_position' => $this->ownPosition($serp),
            'search_console' => $queries,
            'errors' => $errors,
        ];

        Cache::put($this->key($keyword, $path), $report, now()->addDays(self::TTL_DAYS));

        return $report;
    }

    /**
     * Bloco de contexto para os prompts de SEO. Vazio quando não há dados em cache.
     *
     * @param  Report|null  $report
     */
    public function promptContext(?array $report): string
    {
        if ($report === null) {
            return '';
        }

        $lines = ['Dados de busca do Google:'];
        $metrics = $report['metrics'] ?? null;

        if ($metrics !== null) {
            $lines[] = '- "'.$metrics['keyword'].'": '.implode('; ', array_filter([
                $metrics['volume'] !== null ? $metrics['volume'].' buscas por mês' : null,
                $metrics['difficulty'] !== null ? 'dificuldade '.$metrics['difficulty'].'/100' : null,
                $metrics['intent'] !== null ? 'intenção '.$metrics['intent'] : null,
            ])).'.';
        }

        if (($report['related'] ?? []) !== []) {
            $lines[] = '- Variações com demanda (use no máximo uma, só se couber naturalmente): '
                .implode(', ', array_map(fn (array $row) => $row['keyword'].($row['volume'] !== null ? ' ('.$row['volume'].'/mês)' : ''), $report['related'])).'.';
        }

        if (($report['search_console'] ?? []) !== []) {
            $lines[] = '- Buscas que já trazem esta página no Google (Search Console, 28 dias): '
                .implode(', ', array_map(fn (array $row) => $row['query'].' ('.$row['impressions'].' impressões'.($row['position'] !== null ? ', posição '.$row['position'] : '').')', array_slice($report['search_console'], 0, 6))).'.';
        }

        if (($report['serp'] ?? []) !== []) {
            $lines[] = '- Top '.count($report['serp']).' do Google para a palavra-chave (diferencie-se, não copie frases):';

            foreach ($report['serp'] as $row) {
                $lines[] = '  '.$row['position'].'. '.$row['title'].($row['description'] !== '' ? ' | '.mb_substr($row['description'], 0, 160) : '');
            }
        }

        return count($lines) > 1 ? implode("\n", $lines) : '';
    }

    /**
     * @return Metrics|null
     */
    private function metrics(string $keyword): ?array
    {
        $data = $this->read('get_keyword_metrics', [
            'keywords' => [$keyword],
            'includeMonthlyTrends' => false,
            ...$this->client->market(),
        ]);

        $rows = array_map(fn (array $row) => $this->keywordRow($row), $this->rows($data, ['keyword']));
        $match = collect($rows)->first(fn (array $row) => Keyword::normalize($row['keyword']) === Keyword::normalize($keyword));

        return $match ?? ($rows[0] ?? null);
    }

    /**
     * @return list<Metrics>
     */
    private function related(string $keyword): array
    {
        $data = $this->read('research_keywords', ['seeds' => [$keyword], 'resultLimit' => 150]);
        $target = Keyword::normalize($keyword);
        $words = array_filter(Keyword::words($keyword), fn (string $word) => mb_strlen($word) > 3);

        return collect($this->rows($data, ['keyword']))
            ->map(fn (array $row) => $this->keywordRow($row))
            ->filter(fn (array $row) => $row['keyword'] !== '' && Keyword::normalize($row['keyword']) !== $target)
            ->filter(fn (array $row) => $words === [] || array_intersect($words, Keyword::words($row['keyword'])) !== [])
            ->unique(fn (array $row) => Keyword::normalize($row['keyword']))
            ->sortByDesc(fn (array $row) => $row['volume'] ?? -1)
            ->take(self::RELATED_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @return list<array{position: int, title: string, url: string, description: string}>
     */
    private function serp(string $keyword): array
    {
        $data = $this->read('get_serp_results', ['queries' => [$keyword], 'depth' => self::SERP_LIMIT]);

        return collect($this->rows($data, ['url', 'title']))
            ->map(fn (array $row, int $index) => [
                'position' => (int) ($this->value($row, ['rank_group', 'rank', 'position', 'rank_absolute']) ?? $index + 1),
                'title' => trim((string) ($this->value($row, ['title']) ?? '')),
                'url' => trim((string) ($this->value($row, ['url', 'link']) ?? '')),
                'description' => trim((string) ($this->value($row, ['description', 'snippet']) ?? '')),
            ])
            ->filter(fn (array $row) => $row['title'] !== '' && $row['url'] !== '')
            ->sortBy('position')
            ->take(self::SERP_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @return list<array{query: string, clicks: int, impressions: int, ctr: float|null, position: float|null}>
     */
    private function searchConsole(string $url): array
    {
        $data = $this->read('get_search_console_performance', [
            'dimensions' => ['query'],
            'filters' => [['dimension' => 'page', 'operator' => 'equals', 'expression' => $url]],
            'dateRange' => 'last_28_days',
            'rowLimit' => 50,
        ]);

        return collect($this->rows($data, ['impressions']))
            ->map(fn (array $row) => [
                'query' => trim((string) ($this->value($row, ['query', 'keys.0']) ?? '')),
                'clicks' => (int) ($this->value($row, ['clicks']) ?? 0),
                'impressions' => (int) ($this->value($row, ['impressions']) ?? 0),
                'ctr' => $this->number($this->value($row, ['ctr'])),
                'position' => ($position = $this->number($this->value($row, ['position']))) !== null ? round($position, 1) : null,
            ])
            ->filter(fn (array $row) => $row['query'] !== '')
            ->sortByDesc('impressions')
            ->take(self::QUERIES_LIMIT)
            ->values()
            ->all();
    }

    /**
     * @param  list<array{position: int, title: string, url: string, description: string}>  $serp
     */
    private function ownPosition(array $serp): ?int
    {
        $host = $this->host((string) config('services.openseo.site_url'));

        if ($host === '') {
            return null;
        }

        foreach ($serp as $row) {
            if ($this->host($row['url']) === $host) {
                return $row['position'];
            }
        }

        return null;
    }

    private function host(string $url): string
    {
        return preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST))) ?? '';
    }

    private function absoluteUrl(?string $path): ?string
    {
        $base = (string) config('services.openseo.site_url');

        if ($path === null || $base === '') {
            return null;
        }

        return $base.'/'.ltrim($path, '/');
    }

    private function key(string $keyword, ?string $path): string
    {
        return 'openseo:market:'.sha1(Keyword::normalize($keyword).'|'.($path ?? '').'|'.json_encode($this->client->market()));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function read(string $tool, array $arguments): mixed
    {
        $data = $this->client->call($tool, $arguments);
        $failure = $this->failure($data);

        if ($failure !== null) {
            throw new RuntimeException($failure['message']);
        }

        return $data;
    }

    private function message(Throwable $exception): string
    {
        return $exception instanceof RuntimeException ? $this->translateError($exception->getMessage()) : 'Falha ao consultar os dados de busca.';
    }
}
