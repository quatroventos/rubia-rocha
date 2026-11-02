<?php

namespace App\Services\Cases;

use App\Cms\SiteSectionDefinition;
use App\Models\SiteSetting;
use App\Services\Content\WritingGuide;
use App\Services\ImageOptimizationService;
use App\Services\Mockup\DeviceMockupComposer;
use App\Services\Mockup\DeviceSceneGenerator;
use App\Services\Mockup\DeviceScreenshot;
use App\Services\Translation\OpenAiClient;
use App\Support\Seo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Monta o rascunho de um case (textos do card, SEO, seções, prints e mockups) a partir de um briefing e do site publicado.
 */
class CaseDraftWriter
{
    private const PAGE_CHARS = 15000;

    /**
     * Tipo curto pedido à IA => tipo da seção no catálogo.
     *
     * @var array<string, string>
     */
    private const TYPES = [
        'prose' => 'site.prose',
        'list' => 'site.list',
        'cards' => 'site.cards',
        'steps' => 'site.steps',
        'checklist' => 'site.checklist',
        'results' => 'site.results',
        'quotes' => 'site.quotes',
        'faq' => 'site.faq',
    ];

    private const MAX_PRINTS = 4;

    private const MAX_LINKS = 40;

    /**
     * Aparelho pedido à IA => viewport do estúdio de mockup.
     *
     * @var array<string, string>
     */
    private const DEVICES = ['desktop' => 'macbook', 'mobile' => 'iphone'];

    /**
     * Foto do estúdio por aparelho: referências de estilo e ângulos alternados entre as prints.
     *
     * @var array<string, array{references: list<string>, angles: list<string>}>
     */
    private const SCENES = [
        'macbook' => ['references' => ['afbrasilia'], 'angles' => ['three-quarter-left', 'three-quarter-right', 'frontal']],
        'iphone' => ['references' => ['tripfy', 'conexme'], 'angles' => ['standing', 'three-quarter-right', 'three-quarter-left']],
    ];

    private const SETS = ['rail', 'perforated', 'plain', 'grate'];

    private const SCENE_SIZE = '2048x1152';

    private const SCENE_ATTEMPTS = 2;

    /**
     * Teto de tempo para gerar fotos; passou disso, as prints restantes entram sem mockup.
     */
    private const MOCKUP_BUDGET_SECONDS = 900;

    public function __construct(
        private OpenAiClient $client,
        private DeviceScreenshot $screenshots,
        private ImageOptimizationService $optimizer,
        private DeviceSceneGenerator $scenes,
        private DeviceMockupComposer $composer,
    ) {}

    public function configured(): bool
    {
        return $this->client->configured();
    }

    /**
     * @param  array{prompt: string, url?: string|null, prints?: bool, mockups?: bool, case: array<string, mixed>, options?: array{sectors?: array<string, string>, services?: array<string, string>}}  $input
     * @return array{client: string, sector: string, services: list<string>, year: int|null, title: string, summary: string, tags: list<string>, seo_title: string, seo_description: string, sections: list<array{id: string, type: string, data: array<string, mixed>}>, cover: array{path: string, alt: string}|null, source_read: bool, prints_failed: int, mockups: int, mockups_failed: int, mockups_unavailable: bool}
     */
    public function write(array $input): array
    {
        $url = filled($input['url'] ?? null) ? (string) $input['url'] : null;
        $page = $url !== null ? $this->readPage($url) : ['text' => '', 'links' => []];
        $wantsPrints = ($input['prints'] ?? false) && $page['text'] !== '';
        $wantsMockups = $wantsPrints && ($input['mockups'] ?? false);
        $mockupsAvailable = filled(config('services.meta.key'));

        $sectors = $input['options']['sectors'] ?? [];
        $services = $input['options']['services'] ?? [];
        $briefing = trim($input['prompt']);

        $user = "Dados do case já cadastrados:\n".json_encode((object) $input['case'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ."\n\nSetores disponíveis (sector, use a chave):\n".$this->optionList($sectors)
            ."\n\nServiços da Quatro Ventos disponíveis (services, use as chaves):\n".$this->optionList($services)
            .($url !== null ? "\n\nURL do projeto: ".$url : '')
            ."\n\nBriefing de quem desenvolveu o projeto:\n".($briefing !== '' ? $briefing : '(não informado; baseie-se no site publicado e nos dados cadastrados)')
            .($page['text'] !== '' ? "\n\nTexto do site publicado do projeto (".$url."):\n".$page['text'] : '')
            .($wantsPrints
                ? "\n\nPáginas do site disponíveis para prints (escolha só destas):\n".implode("\n", array_map(
                    fn (array $link) => '- '.$link['url'].($link['label'] !== '' ? ' ('.$link['label'].')' : ''),
                    [['url' => $url, 'label' => 'página inicial'], ...$page['links']]
                ))
                : "\n\nPrints: não gere prints (lista prints vazia).");

        $draft = $this->client->json($this->systemPrompt(), $user, 'case_draft', $this->schema());
        $sections = $this->sections(is_array($draft['sections'] ?? null) ? $draft['sections'] : []);
        $result = ['gallery' => null, 'failed' => 0, 'mockups' => 0, 'mockups_failed' => 0, 'cover' => null];

        if ($wantsPrints) {
            $allowed = array_merge([$url], array_column($page['links'], 'url'));
            $result = $this->capturePrints(is_array($draft['prints'] ?? null) ? $draft['prints'] : [], $allowed, $wantsMockups && $mockupsAvailable);
            $gallery = $result['gallery'];

            if ($gallery !== null) {
                $lastProse = array_search('site.prose', array_reverse(array_column($sections, 'type'), true), true);
                array_splice($sections, $lastProse === false ? 0 : $lastProse + 1, 0, [$gallery]);
            }
        }

        $year = (int) preg_replace('/\D/', '', (string) ($draft['year'] ?? ''));

        return [
            'client' => Str::limit(trim((string) ($draft['client'] ?? '')), 255, ''),
            'sector' => array_key_exists((string) ($draft['sector'] ?? ''), $sectors) ? (string) $draft['sector'] : '',
            'services' => array_values(array_unique(array_filter(
                array_map('strval', is_array($draft['services'] ?? null) ? $draft['services'] : []),
                fn (string $slug) => array_key_exists($slug, $services),
            ))),
            'year' => $year >= 1990 && $year <= (int) date('Y') + 1 ? $year : null,
            'title' => Str::limit(trim((string) ($draft['title'] ?? '')), 255, ''),
            'summary' => Str::limit(trim((string) ($draft['summary'] ?? '')), 300, ''),
            'tags' => array_values(array_slice(array_filter(array_map(
                fn ($tag) => Str::limit(trim((string) $tag), 60, ''),
                is_array($draft['tags'] ?? null) ? $draft['tags'] : []
            )), 0, 4)),
            'seo_title' => Seo::fit((string) ($draft['seo_title'] ?? ''), Seo::titleBudget(SiteSetting::query()->first())),
            'seo_description' => Seo::fit((string) ($draft['seo_description'] ?? ''), Seo::DESCRIPTION_MAX),
            'sections' => $sections,
            'cover' => $result['cover'],
            'source_read' => $page['text'] !== '',
            'prints_failed' => $result['failed'],
            'mockups' => $result['mockups'],
            'mockups_failed' => $result['mockups_failed'],
            'mockups_unavailable' => $wantsMockups && ! $mockupsAvailable,
        ];
    }

    /**
     * Captura as páginas escolhidas pela IA com o Chrome do estúdio de mockup e, se pedido, encaixa cada print numa foto
     * de aparelho gerada pelo estúdio. Falha no mockup mantém a print pura; tudo sai em WebP otimizado.
     *
     * @param  array<int, mixed>  $prints
     * @param  list<string>  $allowed
     * @return array{gallery: array{id: string, type: string, data: array<string, mixed>}|null, failed: int, mockups: int, mockups_failed: int, cover: array{path: string, alt: string}|null}
     */
    private function capturePrints(array $prints, array $allowed, bool $mockups): array
    {
        $items = [];
        $failed = 0;
        $made = 0;
        $mockupsFailed = 0;
        $cover = null;
        $seen = [];
        $budget = microtime(true) + self::MOCKUP_BUDGET_SECONDS;

        foreach ($prints as $print) {
            if (count($items) + $failed >= self::MAX_PRINTS || ! is_array($print)) {
                break;
            }

            $url = (string) ($print['url'] ?? '');
            $device = self::DEVICES[$print['device'] ?? ''] ?? null;
            if ($device === null || ! in_array($url, $allowed, true) || isset($seen[$url.$device])) {
                continue;
            }
            $seen[$url.$device] = true;

            try {
                $png = $this->screenshots->capture($url, $device);
            } catch (Throwable $e) {
                $failed++;
                report($e);

                continue;
            }

            $mockup = null;
            if ($mockups) {
                $mockup = microtime(true) < $budget ? $this->mockup($png, $device, count($items)) : null;
                if ($mockup === null) {
                    $mockupsFailed++;
                } else {
                    $made++;
                }
            }

            $path = $this->store($mockup ?? $png);
            if ($path === null) {
                $failed++;

                continue;
            }

            $alt = trim((string) ($print['alt'] ?? ''));
            $items[] = [
                'image' => $path,
                'caption' => trim((string) ($print['caption'] ?? '')),
                'alt' => $alt,
            ];

            if ($mockup !== null && ($cover === null || ($device === 'macbook' && $cover['device'] !== 'macbook'))) {
                $cover = ['path' => $path, 'alt' => $alt, 'device' => $device];
            }
        }

        return [
            'gallery' => $items === [] ? null : ['id' => strtolower((string) Str::ulid()), 'type' => 'site.gallery', 'data' => ['title' => $mockups ? 'Telas' : 'Prints', 'items' => $items]],
            'failed' => $failed,
            'mockups' => $made,
            'mockups_failed' => $mockupsFailed,
            'cover' => $cover === null ? null : ['path' => $cover['path'], 'alt' => $cover['alt']],
        ];
    }

    /**
     * Gera a foto do aparelho com tela magenta e encaixa a print. Nova foto quando a tela não é detectada.
     */
    private function mockup(string $png, string $device, int $index): ?string
    {
        $scene = self::SCENES[$device];

        for ($attempt = 0; $attempt < self::SCENE_ATTEMPTS; $attempt++) {
            try {
                $photo = $this->scenes->generate([
                    'device' => $device,
                    'angle' => $scene['angles'][($index + $attempt) % count($scene['angles'])],
                    'scene' => self::SETS[($index + $attempt) % count(self::SETS)],
                    'size' => self::SCENE_SIZE,
                    'quality' => 'medium',
                    'references' => $scene['references'],
                ]);

                $composed = $this->composer->compose($photo, $png);
                if ($composed !== null) {
                    return $composed;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return null;
    }

    private function store(string $png): ?string
    {
        try {
            $stored = 'media/cases/'.Str::uuid().'.png';
            Storage::disk('public')->put($stored, $png);
            $this->optimizer->optimize([$stored]);
            $public = $this->optimizer->publicUrlFor($stored);
            $path = is_string($public) && $public !== '' ? (string) parse_url($public, PHP_URL_PATH) : '';

            return $path !== '' ? $path : '/storage/'.$stored;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  array<int, mixed>  $raw
     * @return list<array{id: string, type: string, data: array<string, mixed>}>
     */
    private function sections(array $raw): array
    {
        $sections = [];

        foreach ($raw as $section) {
            if (! is_array($section) || ! isset(self::TYPES[$section['type'] ?? ''])) {
                continue;
            }

            $kind = (string) $section['type'];
            $items = array_values(array_filter(is_array($section['items'] ?? null) ? $section['items'] : [], 'is_array'));
            $title = trim((string) ($section['title'] ?? ''));
            $intro = $this->html((string) ($section['intro'] ?? ''));

            $data = match ($kind) {
                'prose' => ['title' => $title, 'body' => $this->html((string) ($section['body'] ?? ''))],
                'list', 'cards', 'steps' => [
                    'title' => $title,
                    'intro' => $intro,
                    'items' => array_map(fn (array $item) => [
                        'title' => trim((string) ($item['title'] ?? '')),
                        'body' => $this->html((string) ($item['text'] ?? '')),
                    ], $items),
                ],
                'checklist' => [
                    'title' => $title,
                    'intro' => $intro,
                    'items' => array_values(array_filter(array_map(fn (array $item) => trim((string) ($item['title'] ?? '')), $items))),
                ],
                'results' => [
                    'title' => $title,
                    'items' => array_map(fn (array $item) => [
                        'value' => trim((string) ($item['value'] ?? '')),
                        'label' => trim((string) ($item['text'] ?? '')),
                    ], $items),
                ],
                'quotes' => [
                    'title' => $title,
                    'items' => array_map(fn (array $item) => [
                        'quote' => $this->html((string) ($item['text'] ?? '')),
                        'author' => trim((string) ($item['author'] ?? '')),
                        'company' => trim((string) ($item['company'] ?? '')),
                    ], $items),
                ],
                'faq' => [
                    'title' => $title,
                    'items' => array_map(fn (array $item) => [
                        'question' => trim((string) ($item['title'] ?? '')),
                        'answer' => $this->html((string) ($item['text'] ?? '')),
                    ], $items),
                ],
            };

            if ($kind === 'list' && preg_match('/^stack\b/i', $title)) {
                $data['layout'] = 'stack';
                $data['items'] = array_map(function (array $mapped, array $item): array {
                    $layer = (string) ($item['layer'] ?? '');

                    return isset(SiteSectionDefinition::STACK_LAYERS[$layer]) ? [...$mapped, 'layer' => $layer] : $mapped;
                }, $data['items'], $items);
            }

            $sections[] = ['id' => strtolower((string) Str::ulid()), 'type' => self::TYPES[$kind], 'data' => $data];
        }

        return $sections;
    }

    /**
     * Texto puro da IA em HTML do editor: blocos separados por linha em branco; linhas com "- " viram lista.
     */
    private function html(string $text): string
    {
        $text = trim(str_replace("\r\n", "\n", $text));
        if ($text === '') {
            return '';
        }

        $html = '';
        foreach (preg_split('/\n\s*\n/', $text) ?: [] as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn (string $line) => $line !== ''));
            if ($lines === []) {
                continue;
            }

            $isList = array_filter($lines, fn (string $line) => ! str_starts_with($line, '- ')) === [];
            $html .= $isList
                ? '<ul>'.implode('', array_map(fn (string $line) => '<li>'.e(substr($line, 2)).'</li>', $lines)).'</ul>'
                : '<p>'.implode('<br>', array_map(fn (string $line) => e($line), $lines)).'</p>';
        }

        return $html;
    }

    /**
     * Texto visível e links internos do site do projeto. Só http(s) e hosts públicos; falha de leitura não impede o rascunho.
     *
     * @return array{text: string, links: list<array{url: string, label: string}>}
     */
    private function readPage(string $url): array
    {
        $none = ['text' => '', 'links' => []];
        $host = (string) parse_url($url, PHP_URL_HOST);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($host === '' || ! in_array($scheme, ['http', 'https'], true) || ! $this->isPublicHost($host)) {
            return $none;
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; QuatroVentosCaseBot/1.0)'])
                ->get($url);
        } catch (Throwable) {
            return $none;
        }

        if (! $response->successful()) {
            return $none;
        }

        $html = (string) $response->body();

        return ['text' => $this->visibleText($html), 'links' => $this->internalLinks($html, $url)];
    }

    /**
     * @return list<array{url: string, label: string}>
     */
    private function internalLinks(string $html, string $base): array
    {
        $scheme = (string) parse_url($base, PHP_URL_SCHEME);
        $host = (string) parse_url($base, PHP_URL_HOST);
        $root = $scheme.'://'.$host;
        $links = [];

        preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);

        foreach ($matches as [, $href, $label]) {
            $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $href = (string) preg_replace('/#.*$/', '', $href);

            if ($href === '' || preg_match('/^(mailto|tel|javascript|data):/i', $href) || preg_match('/\.(pdf|jpe?g|png|gif|webp|svg|zip|docx?|xlsx?|mp4)(\?|$)/i', $href)) {
                continue;
            }

            $absolute = match (true) {
                str_starts_with($href, '//') => $scheme.':'.$href,
                str_starts_with($href, '/') => $root.$href,
                (bool) preg_match('/^https?:\/\//i', $href) => $href,
                default => null,
            };

            if ($absolute === null || strcasecmp((string) parse_url($absolute, PHP_URL_HOST), $host) !== 0 || rtrim($absolute, '/') === rtrim($base, '/')) {
                continue;
            }

            $links[$absolute] ??= Str::limit(trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 60, '');

            if (count($links) >= self::MAX_LINKS) {
                break;
            }
        }

        return array_map(fn (string $url, string $label) => ['url' => $url, 'label' => $label], array_keys($links), $links);
    }

    private function visibleText(string $html): string
    {
        preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $title);
        preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/i', $html, $description);

        $body = preg_replace('/<(script|style|noscript|svg|template|iframe)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? '';
        $body = preg_replace('/<\/(p|div|section|article|li|h[1-6]|br|tr)>/i', "$0\n", $body) ?? '';
        $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace(["/[ \t]+/", "/\n\s*\n+/"], [' ', "\n"], $text));

        $head = trim(html_entity_decode(($title[1] ?? '').' | '.($description[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'), ' |');

        return mb_substr(($head !== '' ? $head."\n\n" : '').$text, 0, self::PAGE_CHARS);
    }

    private function isPublicHost(string $host): bool
    {
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $options
     */
    private function optionList(array $options): string
    {
        if ($options === []) {
            return '(nenhum)';
        }

        return implode("\n", array_map(fn (string $key, string $label) => '- '.$key.': '.$label, array_keys($options), $options));
    }

    private function systemPrompt(): string
    {
        return strtr($this->basePrompt(), [
            '{seo_title_max}' => (string) Seo::titleBudget(SiteSetting::query()->first()),
            '{seo_description_min}' => (string) Seo::DESCRIPTION_MIN,
            '{seo_description_max}' => (string) Seo::DESCRIPTION_MAX,
        ])."\n\n".WritingGuide::rules();
    }

    private function basePrompt(): string
    {
        return <<<'PROMPT'
Você é redator técnico da Quatro Ventos, empresa brasileira de software, IA e automação. Escreva o rascunho da página de um case de portfólio em português do Brasil.

Voz: declarativa, institucional e técnica. IA é engenharia aplicada à operação, não hype. Frases afirmativas e de tamanho variado, com "a Quatro Ventos" ou a primeira pessoa do plural como sujeito. Sem exclamação, sem travessão, sem coloquialismo ("a gente", "conta pra gente"), sem clichê ("muda o jogo", "sem compromisso", "cuidamos de tudo", "solução completa", "inovador") e sem complemento genérico ("para empresas", "da sua empresa").

Briefing: pode vir como roteiro de perguntas ("Rótulo: resposta"). Ignore rótulos sem resposta e marcadores vazios; o rótulo nunca é fato.

Fatos: use só o que está no briefing, nos dados cadastrados e no texto do site publicado. Não invente número, métrica, prazo, resultado, depoimento, certificação ou parceria. Não cite nomes de terceiros (bancos, fornecedores, parceiros, plataformas ou sistemas do cliente) que não estejam no briefing; se o briefing pedir para omitir um nome, omita também o que vier do site. O site publicado é do cliente: use para entender o negócio e o produto, não copie o texto de marketing dele.

Campos:
- client: nome do cliente ou do produto como aparece no site ou no briefing. Se já estiver nos dados cadastrados, repita.
- sector: a chave de um dos setores disponíveis que melhor descreve o cliente, ou string vazia se nenhum servir.
- services: chaves dos serviços disponíveis que este projeto representa (1 a 3), só da lista.
- year: ano de entrega com 4 dígitos, só se estiver no briefing, nos dados cadastrados ou no site; senão string vazia.
- title: o processo ou produto entregue, em uma frase de até 80 caracteres, sem o nome da Quatro Ventos.
- summary: até 280 caracteres, para o card da listagem.
- tags: 2 a 4 etiquetas curtas (até 3 palavras cada), como setor, tipo de produto ou ano.
- seo_title: até {seo_title_max} caracteres, sem o nome da Quatro Ventos (o site acrescenta). seo_description: entre {seo_description_min} e {seo_description_max} caracteres.
- sections: de 3 a 7 seções, na ordem de leitura.

Seções disponíveis (campo type):
- prose: título curto e texto corrido em body. Use para Desafio e Solução.
- checklist: lista do que foi entregue; cada item é uma frase curta em items[].title.
- steps: etapas de como funciona ou como foi feito; items[].title e items[].text.
- list: itens curtos em items[].title ou itens com items[].text. Para a lista "Stack": só tecnologias e serviços confirmados no briefing ou no site; items[].title é o nome, items[].text o papel no projeto em até 8 palavras, e items[].layer a camada: interface (o que o usuário vê), aplicacao (regras do negócio, APIs, filas), dados (bancos, cache, busca), infra (hospedagem, containers, servidores), integracoes (sistemas de terceiros). Na dúvida sobre a camada, deixe o item fora. Fora da Stack, layer fica vazio.
- cards: 3 a 6 recursos ou módulos com items[].title e items[].text.
- results: só com resultado fornecido no briefing; items[].value é o número ou marco curto, items[].text explica.
- quotes: só se o briefing trouxer um depoimento literal; items[].text, items[].author, items[].company.
- faq: perguntas frequentes reais sobre o projeto; items[].title é a pergunta, items[].text a resposta.

Estrutura sugerida: prose "Desafio", prose "Solução", checklist "O que entregamos", e quando houver base: steps, list "Stack", results "Resultados".

Prints: quando houver lista de páginas para prints, escolha de 2 a 4 em prints, só URLs dessa lista. Prefira as telas que mostram o que foi construído (início, produto, funcionalidade, painel) e evite política de privacidade, login, contato e blog. Misture desktop e mobile; a página inicial costuma entrar nos dois. caption: legenda curta do que a tela mostra, até 80 caracteres. alt: descrição objetiva da tela para leitor de tela. Sem lista de páginas, prints fica vazio.

Formato do texto: texto puro, sem HTML e sem markdown. Separe parágrafos com uma linha em branco. Lista dentro de um texto: uma linha por item, começando com "- ". Títulos de seção com até 4 palavras. Campos que não se aplicam ficam como string vazia ou lista vazia.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['client', 'sector', 'services', 'year', 'title', 'summary', 'tags', 'seo_title', 'seo_description', 'sections', 'prints'],
            'properties' => [
                'client' => $string,
                'sector' => $string,
                'services' => ['type' => 'array', 'items' => $string],
                'year' => $string,
                'prints' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['url', 'device', 'caption', 'alt'],
                        'properties' => [
                            'url' => $string,
                            'device' => ['type' => 'string', 'enum' => array_keys(self::DEVICES)],
                            'caption' => $string,
                            'alt' => $string,
                        ],
                    ],
                ],
                'title' => $string,
                'summary' => $string,
                'tags' => ['type' => 'array', 'items' => $string],
                'seo_title' => $string,
                'seo_description' => $string,
                'sections' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['type', 'title', 'intro', 'body', 'items'],
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => array_keys(self::TYPES)],
                            'title' => $string,
                            'intro' => $string,
                            'body' => $string,
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'required' => ['title', 'text', 'value', 'author', 'company', 'layer'],
                                    'properties' => [
                                        'title' => $string,
                                        'text' => $string,
                                        'value' => $string,
                                        'author' => $string,
                                        'company' => $string,
                                        'layer' => ['type' => 'string', 'enum' => ['', ...array_keys(SiteSectionDefinition::STACK_LAYERS)]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
