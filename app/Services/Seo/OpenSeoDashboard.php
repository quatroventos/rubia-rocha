<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Dados do OpenSEO para a tela de SEO do site, já resumidos para o painel.
 * Leituras gratuitas ficam 10 minutos em cache (o botão "Atualizar" ignora o cache);
 * consultas pagas só rodam por ação explícita e a visão do domínio fica 12 horas.
 */
class OpenSeoDashboard
{
    use ReadsOpenSeoData;

    public const READ_TTL_MINUTES = 10;

    public const DOMAIN_TTL_HOURS = 12;

    public const RANGES = ['last_7_days', 'last_28_days', 'last_3_months', 'last_6_months', 'last_12_months'];

    public const AI_DAYS = [7, 28, 90];

    /**
     * Problemas da auditoria do OpenSEO: título e como corrigir em português.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const ISSUES = [
        'blocked-page' => ['Página bloqueada para o robô', 'Libere a URL no robots.txt ou no firewall se ela deve aparecer no Google.'],
        'javascript-rendering-suspected' => ['Conteúdo depende de JavaScript', 'Entregue o texto principal no HTML do servidor ou rode a auditoria com renderização.'],
        'rate-limited-page' => ['Página limitou o robô', 'Ajuste o limite de requisições do servidor para robôs de busca.'],
        'crawl-rate-limited' => ['Rastreamento limitado', 'O site desacelerou o robô; revise o rate limit do servidor ou CDN.'],
        'server-error' => ['Erro de servidor (5xx)', 'Veja o log da aplicação para a URL e corrija a falha.'],
        'broken-internal-link' => ['Link interno quebrado', 'Aponte o link para a URL certa ou remova. Se a página mudou de endereço, linke direto o novo.'],
        'missing-title' => ['Sem título', 'Defina o título SEO da página.'],
        'broken-page' => ['Página com erro (4xx)', 'Restaure a página, redirecione (301) para a equivalente ou tire os links que levam a ela.'],
        'duplicate-title' => ['Título duplicado', 'Dê a cada página um título SEO único, com a palavra-chave dela.'],
        'duplicate-meta-description' => ['Descrição duplicada', 'Escreva uma descrição SEO própria para cada página.'],
        'duplicate-content' => ['Conteúdo duplicado', 'Diferencie o texto ou aponte o canonical para a versão principal.'],
        'missing-meta-description' => ['Sem descrição', 'Preencha a descrição SEO (até 155 caracteres) com a palavra-chave.'],
        'missing-h1' => ['Sem H1', 'Coloque um título principal (H1) no topo da página.'],
        'multiple-h1' => ['Mais de um H1', 'Deixe só um H1 por página; transforme os outros em H2.'],
        'redirect-chain' => ['Cadeia de redirecionamentos', 'Faça o primeiro redirecionamento ir direto ao destino final.'],
        'redirect-loop' => ['Loop de redirecionamento', 'Corrija as regras que redirecionam em círculo.'],
        'canonical-conflict' => ['Canonical em conflito', 'Use um único canonical, absoluto, apontando para a página indexável.'],
        'thin-content' => ['Conteúdo raso', 'Amplie o texto com informação útil para quem busca, ou marque como noindex.'],
        'images-missing-alt' => ['Imagens sem texto alternativo', 'Descreva cada imagem no campo alt.'],
        'orphan-page' => ['Página órfã', 'Inclua links para ela no menu, rodapé ou em páginas relacionadas.'],
        'no-outgoing-links' => ['Sem links de saída', 'Linke páginas relacionadas do site a partir dela.'],
        'title-too-long' => ['Título longo demais', 'Encurte o título SEO para até 60 caracteres.'],
        'title-too-short' => ['Título curto demais', 'Use um título SEO com a palavra-chave e um diferencial.'],
        'meta-description-too-long' => ['Descrição longa demais', 'Encurte a descrição SEO para até 155 caracteres.'],
        'meta-description-too-short' => ['Descrição curta demais', 'Escreva uma descrição SEO entre 120 e 155 caracteres.'],
        'heading-order-skip' => ['Hierarquia de títulos pulada', 'Siga a ordem H1, H2, H3 sem pular níveis.'],
        'slow-response' => ['Resposta lenta', 'Revise cache e consultas da página; o servidor demorou para responder.'],
        'noindex-page' => ['Página com noindex', 'Tire o noindex se a página deve aparecer no Google.'],
        'canonicalized-page' => ['Canonical para outra URL', 'Normal para variações. Confira se o canonical aponta para a versão certa.'],
        'deep-page' => ['Página muito profunda', 'Deixe a página a no máximo 3 cliques da home.'],
    ];

    public const PAID_OFF = 'Consultas pagas do OpenSEO (DataForSEO) estão desligadas em SEO > Integrações.';

    private const SEVERITY_ORDER = ['critical' => 0, 'warning' => 1, 'info' => 2];

    public function __construct(private OpenSeoClient $client) {}

    public function configured(): bool
    {
        return $this->client->configured();
    }

    /**
     * @return array{name: string|null, domain: string|null, location_code: int|null, language_code: string|null}
     */
    public function project(bool $fresh = false): array
    {
        $data = $this->remember('projects', [], $fresh, fn () => $this->client->call('list_projects'));
        $project = collect($this->rows($data, ['id']))->firstWhere('id', $this->client->projectId());

        if ($project === null) {
            throw new RuntimeException('Projeto do OpenSEO não encontrado. Confira o ID do projeto em SEO > Integrações.');
        }

        return [
            'name' => $project['name'] ?? null,
            'domain' => $project['domain'] ?? null,
            'location_code' => $this->integer($project['locationCode'] ?? null),
            'language_code' => $project['languageCode'] ?? null,
        ];
    }

    /**
     * Projetos da conta, para escolher na tela de integrações. Só exige a URL do MCP.
     *
     * @return list<array{id: string, name: string|null, domain: string|null}>
     */
    public function projects(): array
    {
        $data = $this->client->call('list_projects', [], scoped: false);

        if ($failure = $this->failure($data)) {
            throw new RuntimeException($failure['message']);
        }

        return collect($this->rows($data, ['id']))
            ->map(fn (array $row) => ['id' => (string) $row['id'], 'name' => $row['name'] ?? null, 'domain' => $row['domain'] ?? null])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function searchConsole(string $range, bool $fresh = false): array
    {
        $base = ['dateRange' => in_array($range, self::RANGES, true) ? $range : 'last_28_days'];
        $daily = $this->remember('gsc-date', $base, $fresh, fn () => $this->client->call('get_search_console_performance', [...$base, 'dimensions' => ['date'], 'rowLimit' => 1000]));

        if ($failure = $this->failure($daily)) {
            return $this->unavailable($failure);
        }

        $queries = $this->remember('gsc-query', $base, $fresh, fn () => $this->client->call('get_search_console_performance', [...$base, 'dimensions' => ['query'], 'rowLimit' => 10]));
        $pages = $this->remember('gsc-page', $base, $fresh, fn () => $this->client->call('get_search_console_performance', [...$base, 'dimensions' => ['page'], 'rowLimit' => 10]));
        $striking = $this->remember('gsc-striking', $base, $fresh, fn () => $this->client->call('get_search_console_performance', [
            ...$base,
            'dimensions' => ['query', 'page'],
            'minPosition' => 4,
            'maxPosition' => 20,
            'minImpressions' => 10,
            'rowLimit' => 1000,
        ]));

        $series = collect($this->rows($daily, ['impressions']))
            ->map(fn (array $row) => ['date' => (string) $this->value($row, ['date', 'keys.0']), ...$this->performance($row)])
            ->sortBy('date')
            ->values();
        $clicks = (int) $series->sum('clicks');
        $impressions = (int) $series->sum('impressions');
        $weighted = $series->sum(fn (array $row) => ($row['position'] ?? 0) * $row['impressions']);

        return [
            'state' => 'ready',
            'range' => $base['dateRange'],
            'link' => data_get($daily, 'meta.url'),
            'totals' => [
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : null,
                'position' => $impressions > 0 ? round($weighted / $impressions, 1) : null,
            ],
            'series' => $series->all(),
            'queries' => $this->dimensionRows($queries, 'query'),
            'pages' => $this->dimensionRows($pages, 'page'),
            'striking' => collect($this->rows($striking, ['impressions']))
                ->map(fn (array $row) => [
                    'query' => (string) $this->value($row, ['query', 'keys.0']),
                    'page' => (string) ($this->value($row, ['page', 'keys.1']) ?? ''),
                    ...$this->performance($row),
                ])
                ->sortByDesc('impressions')
                ->take(15)
                ->values()
                ->all(),
        ];
    }

    /**
     * Páginas em posição 4-20 cruzadas com o GA4, pontuadas pelo OpenSEO.
     *
     * @return array<string, mixed>
     */
    public function opportunities(bool $fresh = false): array
    {
        $data = $this->remember('opportunities', [], $fresh, fn () => $this->client->call('get_search_opportunities', ['limit' => 20]));

        if ($failure = $this->failure($data)) {
            return $this->unavailable($failure);
        }

        $rows = $this->rows($data, ['page']) ?: $this->rows($data, ['url']);

        return [
            'state' => 'ready',
            'link' => data_get($data, 'meta.url'),
            'rows' => collect($rows)->map(fn (array $row) => [
                'url' => (string) $this->value($row, ['page', 'url', 'landingPage']),
                'score' => $this->number($this->value($row, ['score', 'opportunityScore', 'priority'])),
                'position' => $this->roundOrNull($this->value($row, ['position', 'avgPosition', 'averagePosition', 'searchConsole.position'])),
                'impressions' => $this->integer($this->value($row, ['impressions', 'searchConsole.impressions'])),
                'clicks' => $this->integer($this->value($row, ['clicks', 'searchConsole.clicks'])),
                'sessions' => $this->integer($this->value($row, ['sessions', 'organicSessions', 'analytics.sessions', 'ga4.sessions'])),
                'conversions' => $this->number($this->value($row, ['keyEvents', 'conversions', 'analytics.keyEvents', 'ga4.keyEvents'])),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function audit(bool $fresh = false): array
    {
        $status = $this->remember('audit-status', [], $fresh, fn () => $this->client->call('get_audit_status'));

        if ($failure = $this->failure($status)) {
            return $this->unavailable($failure);
        }

        $current = is_array($status['status'] ?? null) ? $status['status'] : null;

        if ($current === null) {
            return ['state' => 'none', 'link' => data_get($status, 'meta.url'), 'site_url' => $this->siteUrl()];
        }

        $state = (string) ($current['status'] ?? '');
        $issues = $state === 'completed'
            ? $this->remember('audit-issues', ['id' => $current['id'] ?? null], $fresh, fn () => $this->client->call('get_audit_issues', ['auditId' => $current['id'] ?? null, 'limit' => 1000]))
            : [];

        $grouped = collect((array) ($issues['issues'] ?? []))->groupBy('issueType');
        $summary = collect((array) ($issues['summary'] ?? []))
            ->map(fn (array $row) => [
                'type' => (string) $row['issueType'],
                'title' => self::ISSUES[$row['issueType']][0] ?? (string) ($row['title'] ?? $row['issueType']),
                'fix' => self::ISSUES[$row['issueType']][1] ?? null,
                'severity' => (string) ($row['severity'] ?? 'info'),
                'count' => (int) ($row['count'] ?? 0),
                'pages' => $grouped->get($row['issueType'], collect())
                    ->map(fn (array $issue) => ['url' => (string) ($issue['url'] ?? ''), 'detail' => $this->issueDetail((array) ($issue['details'] ?? []))])
                    ->take(25)
                    ->values()
                    ->all(),
            ])
            ->sortBy([fn (array $a, array $b) => (self::SEVERITY_ORDER[$a['severity']] ?? 3) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 3), fn (array $a, array $b) => $b['count'] <=> $a['count']])
            ->values();

        return [
            'state' => $state === 'completed' ? 'ready' : ($state === 'failed' ? 'failed' : 'running'),
            'link' => data_get($status, 'meta.url'),
            'site_url' => $this->siteUrl(),
            'audit' => [
                'id' => $current['id'] ?? null,
                'url' => $current['startUrl'] ?? null,
                'phase' => $current['currentPhase'] ?? null,
                'pages_crawled' => (int) ($current['pagesCrawled'] ?? 0),
                'pages_total' => (int) ($current['pagesTotal'] ?? 0),
                'lighthouse_total' => (int) ($current['lighthouseTotal'] ?? 0),
                'lighthouse_done' => (int) ($current['lighthouseCompleted'] ?? 0),
                'started_at' => $current['startedAt'] ?? null,
                'completed_at' => $current['completedAt'] ?? null,
                'error' => $current['errorCode'] ?? null,
            ],
            'counts' => [
                'critical' => (int) $summary->where('severity', 'critical')->sum('count'),
                'warning' => (int) $summary->where('severity', 'warning')->sum('count'),
                'info' => (int) $summary->where('severity', 'info')->sum('count'),
            ],
            'issues' => $summary->all(),
        ];
    }

    /**
     * Inicia o rastreamento do site no OpenSEO. Roda em segundo plano lá.
     */
    public function runAudit(int $maxPages, bool $lighthouse): void
    {
        $this->write('run_site_audit', [
            'url' => $this->auditUrl().'/',
            'maxPages' => max(10, min(500, $maxPages)),
            'runLighthouse' => $lighthouse,
        ]);

        $this->forget('audit-status', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function keywords(bool $fresh = false): array
    {
        $data = $this->remember('keywords', [], $fresh, fn () => $this->client->call('list_saved_keywords', ['limit' => 250]));

        if ($failure = $this->failure($data)) {
            return $this->unavailable($failure);
        }

        return [
            'state' => 'ready',
            'link' => data_get($data, 'meta.url'),
            'rows' => collect((array) ($data['rows'] ?? []))
                ->map(fn (array $row) => ['id' => (string) ($row['id'] ?? ''), ...$this->keywordRow($row), 'tags' => array_values(array_map(fn ($tag) => is_array($tag) ? (string) ($tag['name'] ?? '') : (string) $tag, (array) ($row['tags'] ?? [])))])
                ->sortByDesc(fn (array $row) => $row['volume'] ?? -1)
                ->values()
                ->all(),
        ];
    }

    /**
     * Pesquisa de palavras-chave a partir de um termo. Gasta créditos do DataForSEO.
     *
     * @return list<array<string, mixed>>
     */
    public function research(string $seed): array
    {
        $this->assertPaid();

        $data = $this->write('research_keywords', ['seeds' => [['seed' => $seed]], 'resultLimit' => 150]);

        return collect($this->rows($data, ['keyword']))
            ->map(fn (array $row) => $this->keywordRow($row))
            ->filter(fn (array $row) => $row['keyword'] !== '')
            ->unique(fn (array $row) => Keyword::normalize($row['keyword']))
            ->sortByDesc(fn (array $row) => $row['volume'] ?? -1)
            ->take(60)
            ->values()
            ->all();
    }

    /**
     * @param  list<array{keyword: string, volume?: int|null, difficulty?: int|null, cpc?: float|null, intent_code?: string|null}>  $rows
     */
    public function saveKeywords(array $rows): void
    {
        $this->write('save_keywords', [
            'keywords' => array_column($rows, 'keyword'),
            'metrics' => array_map(fn (array $row) => array_filter([
                'keyword' => $row['keyword'],
                'searchVolume' => $row['volume'] ?? null,
                'keywordDifficulty' => $row['difficulty'] ?? null,
                'cpc' => $row['cpc'] ?? null,
                'intent' => $row['intent_code'] ?? null,
            ], fn ($value) => $value !== null), $rows),
        ]);

        $this->forget('keywords', []);
    }

    /**
     * @param  list<string>  $ids
     */
    public function removeKeywords(array $ids): void
    {
        $this->write('remove_saved_keywords', ['savedKeywordIds' => $ids]);
        $this->forget('keywords', []);
    }

    /**
     * Situação de indexação no Google (via Search Console) de até 10 URLs.
     *
     * @param  list<string>  $urls
     * @return array<string, mixed>
     */
    public function inspect(array $urls): array
    {
        $data = $this->client->call('inspect_urls', ['urls' => array_values($urls), 'languageCode' => 'pt-BR']);

        if ($failure = $this->failure($data)) {
            return $this->unavailable($failure);
        }

        $rows = $this->rows($data, ['url']) ?: $this->rows($data, ['inspectionUrl']);

        return [
            'state' => 'ready',
            'link' => data_get($data, 'meta.url'),
            'rows' => collect($rows)->map(function (array $row) {
                $verdict = $this->value($row, ['verdict', 'indexStatus.verdict', 'indexStatusResult.verdict', 'inspectionResult.indexStatusResult.verdict', 'result.indexStatusResult.verdict']);

                return [
                    'url' => (string) $this->value($row, ['url', 'inspectionUrl']),
                    'indexed' => is_string($verdict) ? strtoupper($verdict) === 'PASS' : null,
                    'verdict' => $verdict,
                    'coverage' => $this->value($row, ['coverageState', 'coverage', 'indexStatus.coverageState', 'indexStatusResult.coverageState', 'inspectionResult.indexStatusResult.coverageState', 'result.indexStatusResult.coverageState']),
                    'last_crawl' => $this->value($row, ['lastCrawlTime', 'indexStatus.lastCrawlTime', 'indexStatusResult.lastCrawlTime', 'inspectionResult.indexStatusResult.lastCrawlTime', 'result.indexStatusResult.lastCrawlTime']),
                    'google_canonical' => $this->value($row, ['googleCanonical', 'indexStatus.googleCanonical', 'indexStatusResult.googleCanonical', 'inspectionResult.indexStatusResult.googleCanonical', 'result.indexStatusResult.googleCanonical']),
                    'user_canonical' => $this->value($row, ['userCanonical', 'indexStatus.userCanonical', 'indexStatusResult.userCanonical', 'inspectionResult.indexStatusResult.userCanonical', 'result.indexStatusResult.userCanonical']),
                    'error' => is_string($row['error'] ?? null) ? $row['error'] : data_get($row, 'error.message'),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function cachedDomain(): ?array
    {
        $report = Cache::get($this->domainKey());

        return is_array($report) ? $report : null;
    }

    /**
     * Visão orgânica do domínio. Gasta créditos do DataForSEO.
     *
     * @return array<string, mixed>
     */
    public function fetchDomain(): array
    {
        $this->assertPaid();

        $domain = $this->domain();
        $data = $this->write('get_domain_overview', ['domain' => $domain, 'scope' => 'subdomains']);
        $topKeywords = $this->rows($data, ['keyword']);

        $report = [
            'state' => 'ready',
            'domain' => $domain,
            'fetched_at' => now()->toIso8601String(),
            'link' => data_get($data, 'meta.url'),
            'metrics' => [
                'traffic' => $this->integer($this->findValue($data, ['organicTraffic', 'organic_traffic', 'estimatedTraffic', 'etv', 'traffic'])),
                'keywords' => $this->integer($this->findValue($data, ['organicKeywords', 'organic_keywords', 'keywordCount', 'keywords_count', 'rankedKeywords'])),
                'backlinks' => $this->integer($this->findValue($data, ['backlinks', 'backlinksCount', 'totalBacklinks'])),
                'referring_domains' => $this->integer($this->findValue($data, ['referringDomains', 'referring_domains', 'refDomains'])),
            ],
            'top_keywords' => collect($topKeywords)
                ->map(fn (array $row) => [
                    ...$this->keywordRow($row),
                    'position' => $this->integer($this->value($row, ['position', 'rank', 'rank_group'])),
                    'url' => $this->value($row, ['url', 'rankingUrl']),
                ])
                ->take(15)
                ->values()
                ->all(),
        ];

        Cache::put($this->domainKey(), $report, now()->addHours(self::DOMAIN_TTL_HOURS));

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    public function ai(int $days, bool $fresh = false): array
    {
        $days = in_array($days, self::AI_DAYS, true) ? $days : 7;
        $tracker = $this->remember('ai-tracker', [], $fresh, fn () => $this->client->call('get_ai_visibility_tracker'));

        if ($failure = $this->failure($tracker)) {
            return $this->unavailable($failure);
        }

        $setup = (array) ($tracker['data'] ?? $tracker);

        if (empty($setup['configured'])) {
            return ['state' => 'none', 'link' => data_get($tracker, 'meta.url')];
        }

        $trend = $this->remember('ai-trend', ['days' => $days], $fresh, fn () => $this->client->call('get_ai_visibility_trend', ['days' => $days]));

        if ($failure = $this->failure($trend)) {
            return $this->unavailable($failure);
        }

        $data = (array) ($trend['data'] ?? $trend);
        $prompts = collect((array) ($setup['prompts'] ?? []))->filter(fn (array $prompt) => empty($prompt['archived']));

        return [
            'state' => 'ready',
            'days' => $days,
            'link' => data_get($trend, 'meta.url') ?? data_get($tracker, 'meta.url'),
            'tracker' => [
                'enabled' => (bool) data_get($setup, 'tracker.enabled', false),
                'schedule' => data_get($setup, 'tracker.scheduleInterval'),
                'next_check_at' => data_get($setup, 'tracker.nextCheckAt'),
                'topics' => array_values((array) ($setup['topics'] ?? [])),
                'prompts' => $prompts->count(),
                'paused' => $prompts->where('paused', true)->count(),
            ],
            'comparison' => $data['comparison'] ?? null,
            'mentions' => $this->rate((array) ($data['mentions'] ?? [])),
            'citations' => $this->rate((array) ($data['citations'] ?? [])),
            'coverage' => [
                'expected' => (int) data_get($data, 'current.coverage.expected', 0),
                'answered' => (int) data_get($data, 'current.coverage.answered', 0),
                'failed' => (int) data_get($data, 'current.coverage.failed', 0),
            ],
            'engines' => collect((array) ($data['engines'] ?? []))->map(fn (array $row) => [
                'engine' => (string) ($row['engine'] ?? ''),
                'mentions' => $this->rate((array) ($row['mentions'] ?? [])),
                'citations' => $this->rate((array) ($row['citations'] ?? [])),
            ])->values()->all(),
            'runs' => collect((array) ($data['runs'] ?? []))->map(fn (array $run) => [
                'created_at' => $run['createdAt'] ?? null,
                'mention_rate' => $this->number($run['mentionRate'] ?? null),
                'citation_rate' => $this->number($run['citationRate'] ?? null),
                'expected' => (int) data_get($run, 'coverage.expected', 0),
                'answered' => (int) data_get($run, 'coverage.answered', 0),
                'failed' => (int) data_get($run, 'coverage.failed', 0),
            ])->values()->all(),
        ];
    }

    public function siteUrl(): string
    {
        return rtrim((string) config('services.openseo.site_url'), '/');
    }

    /**
     * Endereço rastreado pela auditoria. Antes da publicação aponta para um túnel (ngrok) do ambiente
     * local, enquanto pesquisa de domínio e palavras-chave continuam no domínio de produção.
     */
    public function auditUrl(): string
    {
        return rtrim((string) (config('services.openseo.audit_url') ?: $this->siteUrl()), '/');
    }

    public function domain(): string
    {
        return (string) (preg_replace('/^www\./', '', (string) parse_url($this->siteUrl(), PHP_URL_HOST)) ?: '');
    }

    /**
     * @param  array{message: string, action_url: string|null}  $failure
     * @return array{state: string, message: string, action_url: string|null}
     */
    private function unavailable(array $failure): array
    {
        return ['state' => 'unavailable', 'message' => $failure['message'], 'action_url' => $failure['action_url']];
    }

    private function assertPaid(): void
    {
        if (! config('services.openseo.paid')) {
            throw new RuntimeException(self::PAID_OFF);
        }
    }

    /**
     * Chamada que altera algo ou gasta créditos: nunca vem do cache e falha vira exceção.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function write(string $tool, array $arguments): mixed
    {
        try {
            $data = $this->client->call($tool, $arguments);
        } catch (RuntimeException $exception) {
            throw new RuntimeException($this->translateError($exception->getMessage()), 0, $exception);
        }

        if ($failure = $this->failure($data)) {
            throw new RuntimeException($failure['message']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function remember(string $name, array $arguments, bool $fresh, callable $callback): mixed
    {
        $key = $this->cacheKey($name, $arguments);

        if (! $fresh && Cache::has($key)) {
            return Cache::get($key);
        }

        $data = $callback();

        if ($this->failure($data) === null) {
            Cache::put($key, $data, now()->addMinutes(self::READ_TTL_MINUTES));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function forget(string $name, array $arguments): void
    {
        Cache::forget($this->cacheKey($name, $arguments));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function cacheKey(string $name, array $arguments): string
    {
        return 'openseo:dash:'.$this->client->projectId().':'.$name.':'.sha1((string) json_encode($arguments));
    }

    private function domainKey(): string
    {
        return 'openseo:domain:'.$this->client->projectId().':'.$this->domain();
    }

    /**
     * @return array{clicks: int, impressions: int, ctr: float|null, position: float|null}
     */
    private function performance(array $row): array
    {
        return [
            'clicks' => (int) ($this->value($row, ['clicks']) ?? 0),
            'impressions' => (int) ($this->value($row, ['impressions']) ?? 0),
            'ctr' => $this->number($this->value($row, ['ctr'])),
            'position' => $this->roundOrNull($this->value($row, ['position'])),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dimensionRows(mixed $data, string $dimension): array
    {
        return collect($this->rows($data, ['impressions']))
            ->map(fn (array $row) => [$dimension => (string) $this->value($row, [$dimension, 'keys.0']), ...$this->performance($row)])
            ->filter(fn (array $row) => $row[$dimension] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $rate
     * @return array{current: float|null, previous: float|null, change: float|null}
     */
    private function rate(array $rate): array
    {
        return [
            'current' => $this->number($rate['current'] ?? null),
            'previous' => $this->number($rate['previous'] ?? null),
            'change' => $this->number($rate['change'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function issueDetail(array $details): ?string
    {
        if (isset($details['targetUrl'])) {
            return 'Aponta para '.$details['targetUrl'].(isset($details['targetStatus']) ? ' ('.$details['targetStatus'].')' : '');
        }

        $known = match (true) {
            isset($details['otherUrls']) && is_array($details['otherUrls']) => 'Igual a '.implode(', ', array_slice($details['otherUrls'], 0, 2)),
            isset($details['statusCode']) => 'Status '.$details['statusCode'],
            isset($details['imagesMissingAlt']) => $details['imagesMissingAlt'].' de '.($details['imagesTotal'] ?? '?').' imagens sem alt',
            isset($details['wordCount']) => $details['wordCount'].' palavras',
            isset($details['h1Count']) => $details['h1Count'].' títulos H1',
            isset($details['canonicalUrl']) => 'Canonical: '.$details['canonicalUrl'],
            isset($details['length']) => $details['length'].' caracteres',
            default => null,
        };

        if ($known !== null) {
            return $known;
        }

        $parts = collect($details)
            ->filter(fn ($value) => is_scalar($value) && $value !== '')
            ->map(fn ($value, $key) => $key.': '.(is_bool($value) ? ($value ? 'sim' : 'não') : mb_substr((string) $value, 0, 120)))
            ->take(3);

        return $parts->isEmpty() ? null : $parts->implode(' · ');
    }

    /**
     * Procura a primeira chave com valor numérico em qualquer nível da resposta.
     *
     * @param  list<string>  $keys
     */
    private function findValue(mixed $data, array $keys): mixed
    {
        if (! is_array($data)) {
            return null;
        }

        foreach ($keys as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return $data[$key];
            }
        }

        foreach ($data as $value) {
            if (is_array($value) && ($found = $this->findValue($value, $keys)) !== null) {
                return $found;
            }
        }

        return null;
    }

    private function roundOrNull(mixed $value): ?float
    {
        $number = $this->number($value);

        return $number !== null ? round($number, 1) : null;
    }
}
