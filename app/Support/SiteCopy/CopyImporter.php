<?php

namespace App\Support\SiteCopy;

use Illuminate\Support\Str;

/**
 * Converte os briefs de copy (.impeccable/briefs/copy/*.md) em páginas e seções site.* do CMS.
 *
 * Formato esperado: "## `/slug`", linhas Title/Description, blocos "### tipo — Rótulo"
 * com linhas "Chave: valor", itens "- " e etapas "1. ".
 */
class CopyImporter
{
    private const KEYS = [
        'H1', 'H2', 'Lead', 'Corpo', 'CTA primário', 'CTA secundário', 'CTA', 'Botão de cada plano',
        'Botão', 'Link secundário', 'Link', 'Selos', 'Linha rotativa', 'Logos', 'Depoimento em destaque',
        'Nota', 'Entrega', 'Stack',
    ];

    private const NOTE_KEYS = ['Nota', 'Entrega', 'Stack'];

    private const HUB_FILES = ['02-', '03-', '04-', '05-'];

    public const FRESHDESK_URL = 'https://quatroventos.freshdesk.com/support/home';

    public const LOGOS = [
        'Santa Plural' => 'santaplural',
        'Rodolfo Fontana' => 'rodolfofontana',
        'Liv Decora' => 'liv',
        'Inove IT' => 'inoveit',
        'Hemera DTVM' => 'hemera',
        'Garimpo Home' => 'garimpo',
        'Fernanda Saad' => 'fernandasaad',
        'Gisele Siqueira Dermatologista' => 'giselesiqueira',
        'Digio' => 'digio',
        'Data Driven Mkt' => 'ddm',
    ];

    /** @var array<string, array<string, mixed>> */
    private array $pages = [];

    /** @var list<array{quote: string, author: string, company: string}> */
    private array $quotes = [];

    /** @var list<string> */
    private array $logoNames = [];

    public function __construct(private string $whatsappUrl) {}

    /**
     * @param  list<string>  $files
     * @return array<string, array{path: string, slug: string, file: string, title: string, description: string, noindex: bool, sections: list<array{id: string, type: string, data: array<string, mixed>}>}>
     */
    public function import(array $files): array
    {
        sort($files);
        $this->pages = [];

        foreach ($files as $file) {
            $this->parseFile($file);
        }

        $this->collectShared();

        $out = [];
        foreach ($this->pages as $path => $page) {
            if (str_contains($path, '{')) {
                continue;
            }

            $sections = [];
            foreach ($page['blocks'] as $block) {
                foreach ($this->sectionsFor($block, $page) as $section) {
                    $sections[] = $section;
                }
            }

            $sections = $this->withCasesBlock($sections, $page);

            $hero = $this->firstBlock($page, 'hero');
            $h1 = $hero ? (string) ($this->keyed($hero)['H1'] ?? '') : '';

            $out[$path] = [
                'path' => $path,
                'slug' => $path === '/' ? 'home' : trim($path, '/'),
                'file' => basename($page['file']),
                'title' => $page['title'] !== '' ? $page['title'] : rtrim($h1, '.'),
                'description' => $page['description'],
                'noindex' => $page['noindex'],
                'sections' => array_map(fn (array $s) => [
                    'id' => (string) Str::ulid(),
                    'type' => $s['type'],
                    'data' => $s['data'],
                ], $sections),
            ];
        }

        return $out;
    }

    /**
     * Itens da página /servicos agrupados por hub, para montar os menus.
     *
     * @return list<array{label: string, href: string, children: list<array{label: string, href: string}>}>
     */
    public function serviceTree(): array
    {
        $page = $this->pages['/servicos'] ?? null;
        if ($page === null) {
            return [];
        }

        $tree = [];
        foreach ($page['blocks'] as $block) {
            if ($block['kind'] !== 'lista' || $block['label_href'] === null) {
                continue;
            }

            $children = [];
            foreach ($block['items'] as $raw) {
                $item = $this->listItem($raw);
                if ($item['href'] !== '') {
                    $children[] = ['label' => $item['title'], 'href' => $item['href']];
                }
            }

            $tree[] = ['label' => $block['label'], 'href' => $block['label_href'], 'children' => $children];
        }

        return $tree;
    }

    private function parseFile(string $file): void
    {
        $lines = preg_split('/\R/u', (string) file_get_contents($file)) ?: [];
        $path = null;
        $block = null;

        foreach ($lines as $raw) {
            $line = trim($raw);

            if (preg_match('/^## `([^`]+)`(.*)$/u', $line, $m)) {
                $this->closeBlock($path, $block);
                $path = $m[1];
                $this->pages[$path] = [
                    'path' => $path,
                    'file' => $file,
                    'title' => '',
                    'description' => '',
                    'noindex' => str_contains($m[2], 'noindex'),
                    'blocks' => [],
                ];

                continue;
            }

            if ($path === null || $line === '---' || $line === '') {
                continue;
            }

            if (preg_match('/^### (\S+)(?:\s+—\s+(.+))?$/u', $line, $m)) {
                $this->closeBlock($path, $block);
                $label = trim($m[2] ?? '');
                $labelHref = null;
                if (preg_match('/^(.*?)\s*→\s*`([^`]+)`$/u', $label, $lm)) {
                    $label = trim($lm[1]);
                    $labelHref = $lm[2];
                }
                $block = ['kind' => $m[1], 'label' => $label, 'label_href' => $labelHref, 'keyed' => [], 'items' => [], 'steps' => [], 'plain' => []];

                continue;
            }

            if ($block === null) {
                if (preg_match('/^(Title|Description):\s*(.+)$/u', $line, $m)) {
                    $this->pages[$path][strtolower($m[1])] = trim($m[2]);
                }

                continue;
            }

            if (preg_match('/^- (.+)$/u', $line, $m)) {
                $block['items'][] = trim($m[1]);
            } elseif (preg_match('/^\d+\.\s+(.+)$/u', $line, $m)) {
                $block['steps'][] = trim($m[1]);
            } elseif (($pair = $this->keyLine($line)) !== null) {
                $block['keyed'][$pair[0]] = $pair[1];
            } else {
                $block['plain'][] = $line;
            }
        }

        $this->closeBlock($path, $block);
    }

    /**
     * @param  array<string, mixed>|null  $block
     */
    private function closeBlock(?string $path, ?array &$block): void
    {
        if ($path !== null && $block !== null) {
            $this->pages[$path]['blocks'][] = $block;
        }

        $block = null;
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function keyLine(string $line): ?array
    {
        foreach (self::KEYS as $key) {
            if (str_starts_with($line, $key.':')) {
                return [$key, trim(substr($line, strlen($key) + 1))];
            }
        }

        return null;
    }

    private function collectShared(): void
    {
        $this->quotes = [];
        $this->logoNames = [];

        foreach ($this->pages as $page) {
            foreach ($page['blocks'] as $block) {
                if ($block['kind'] !== 'prova') {
                    continue;
                }

                foreach ($block['items'] as $raw) {
                    $quote = $this->quoteItem($raw);
                    if ($quote !== null) {
                        $this->quotes[] = $quote;
                    }
                }

                if (isset($block['keyed']['Logos']) && $this->logoNames === []) {
                    $this->logoNames = array_values(array_filter(array_map(
                        fn (string $name) => trim($name, " .\t"),
                        explode(',', $block['keyed']['Logos'])
                    )));
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $page
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function sectionsFor(array $block, array $page, int $depth = 0): array
    {
        $ref = $block['kind'] === 'prova' ? null : $this->referencedBlock($block, $page);
        if ($ref !== null && $depth < 3) {
            $merged = $ref;
            if ($block['label'] !== '' && ! isset($ref['keyed']['H2'])) {
                $merged['label'] = $block['label'];
            }

            return $this->sectionsFor($merged, $page, $depth + 1);
        }

        $k = $block['keyed'];

        return match ($block['kind']) {
            'hero' => [$this->hero($block)],
            'texto' => [[
                'type' => 'site.prose',
                'data' => [
                    'title' => $k['H2'] ?? $block['label'],
                    'body' => $this->rich($k['Corpo'] ?? ''),
                ],
            ]],
            'lista' => [$this->listSection($block)],
            'etapas' => [[
                'type' => 'site.steps',
                'data' => [
                    'title' => $k['H2'] ?? '',
                    'intro' => $this->notes($block),
                    'items' => array_map(fn (string $raw) => $this->step($raw), $block['steps']),
                ],
            ]],
            'planos' => [$this->plans($block)],
            'faq' => [[
                'type' => 'site.faq',
                'data' => [
                    'title' => $k['H2'] ?? 'Perguntas frequentes',
                    'items' => array_values(array_filter(array_map(fn (string $raw) => $this->faqItem($raw), $block['items']))),
                ],
            ]],
            'cta' => [$this->cta($block)],
            'contato' => [[
                'type' => 'site.contact',
                'data' => ['title' => $k['H2'] ?? '', 'intro' => ''],
            ]],
            'cases' => $page['path'] === '/cases' && $block['label'] === 'Grade'
                ? [['type' => 'site.cases-list', 'data' => ['title' => '', 'intro' => '', 'filter' => true]]]
                : [],
            'prova' => $this->proof($block),
            default => [],
        };
    }

    /**
     * Blocos descritos como "Mesmo bloco da home", "Mesmo método de sites" etc.
     *
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>|null
     */
    private function referencedBlock(array $block, array $page): ?array
    {
        $plain = $block['plain'];
        if (count($plain) !== 1 || $block['items'] !== [] || $block['steps'] !== [] || $block['keyed'] !== []) {
            return null;
        }

        $text = mb_strtolower($plain[0]);
        if (! str_starts_with($text, 'mesm')) {
            return null;
        }

        $source = match (true) {
            str_contains($text, 'hub web') => '/desenvolvimento-web',
            str_contains($text, 'do hub') => $this->firstPathOfFile($page['file']),
            str_contains($text, 'de sites') => '/desenvolvimento-de-sites',
            str_contains($text, 'manutenção') => '/manutencao-de-sites',
            str_contains($text, 'home') => '/',
            default => null,
        };

        if ($source === null || ! isset($this->pages[$source])) {
            return null;
        }

        $named = preg_match('/"([^"]+)"/u', $plain[0], $m) ? $m[1] : null;
        $candidates = array_values(array_filter(
            $this->pages[$source]['blocks'],
            fn (array $b) => $b['kind'] === $block['kind'] && $this->referencedBlockIsConcrete($b)
        ));

        foreach ($candidates as $candidate) {
            if ($named !== null && ($candidate['keyed']['H2'] ?? '') === $named) {
                return $candidate;
            }
        }

        foreach ($candidates as $candidate) {
            if ($block['label'] !== '' && $candidate['label'] === $block['label']) {
                return $candidate;
            }
        }

        return $candidates[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function referencedBlockIsConcrete(array $block): bool
    {
        return $block['items'] !== [] || $block['steps'] !== [] || isset($block['keyed']['H2']);
    }

    private function firstPathOfFile(string $file): ?string
    {
        foreach ($this->pages as $path => $page) {
            if ($page['file'] === $file) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array{type: string, data: array<string, mixed>}
     */
    private function hero(array $block): array
    {
        $k = $block['keyed'];
        $title = (string) ($k['H1'] ?? '');
        $rotating = [];

        if (isset($k['Linha rotativa'])) {
            $rotating = array_values(array_filter(array_map('trim', explode('·', $k['Linha rotativa']))));
            $first = $rotating[0] ?? '';
            if ($first !== '' && str_ends_with($title, $first)) {
                $title = rtrim(mb_substr($title, 0, mb_strlen($title) - mb_strlen($first)));
            }
        }

        [$primaryLabel, $primaryHref] = $this->link($k['CTA primário'] ?? $k['CTA'] ?? $k['Botão'] ?? '');
        [$secondaryLabel, $secondaryHref] = $this->link($k['CTA secundário'] ?? $k['Link'] ?? '');

        return [
            'type' => 'site.hero',
            'data' => [
                'title' => $title,
                'rotating' => $rotating,
                'subtitle' => $this->rich($k['Lead'] ?? ''),
                'badges' => isset($k['Selos']) ? array_values(array_filter(array_map('trim', explode('·', $k['Selos'])))) : [],
                'primary_label' => $primaryLabel,
                'primary_href' => $primaryHref,
                'secondary_label' => $secondaryLabel,
                'secondary_href' => $secondaryHref,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array{type: string, data: array<string, mixed>}
     */
    private function listSection(array $block): array
    {
        $items = array_map(fn (string $raw) => $this->listItem($raw), $block['items']);

        if ($items === []) {
            foreach ($block['plain'] as $line) {
                if (substr_count($line, ',') >= 2) {
                    foreach (explode(',', rtrim($line, '.')) as $part) {
                        $items[] = ['title' => trim($part), 'body' => '', 'href' => ''];
                    }
                }
            }
        }

        return [
            'type' => 'site.list',
            'data' => [
                'title' => $block['keyed']['H2'] ?? $block['label'],
                'href' => $block['label_href'] ?? '',
                'intro' => $this->notes($block),
                'items' => $items,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array{type: string, data: array<string, mixed>}
     */
    private function plans(array $block): array
    {
        [$cta, $href] = $this->link($block['keyed']['Botão de cada plano'] ?? '');
        $items = [];

        foreach ($block['items'] as $raw) {
            if (! preg_match('/^\*\*(.+?)\s+—\s+(R\$\s*[\d.,]+)(\/[^.*]+)?\.?\*\*\s*(.*)$/u', $raw, $m)) {
                continue;
            }

            $items[] = [
                'name' => trim($m[1]),
                'price' => trim($m[2]),
                'period' => trim($m[3] ?? ''),
                'body' => $this->rich($m[4]),
                'cta' => $cta,
                'href' => $href,
            ];
        }

        return [
            'type' => 'site.plans',
            'data' => ['title' => $block['keyed']['H2'] ?? '', 'intro' => '', 'items' => $items],
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array{type: string, data: array<string, mixed>}
     */
    private function cta(array $block): array
    {
        $k = $block['keyed'];
        [$label, $href] = $this->link($k['Botão'] ?? '');
        [$secondaryLabel, $secondaryHref] = $this->link($k['Link secundário'] ?? $k['Link'] ?? '');

        return [
            'type' => 'site.cta',
            'data' => [
                'title' => $k['H2'] ?? '',
                'body' => $this->rich($k['Corpo'] ?? ''),
                'label' => $label,
                'href' => $href,
                'secondary_label' => $secondaryLabel,
                'secondary_href' => $secondaryHref,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function proof(array $block): array
    {
        $own = array_values(array_filter(array_map(fn (string $raw) => $this->quoteItem($raw), $block['items'])));
        $text = implode(' ', array_merge($block['plain'], array_values($block['keyed'])));
        $lower = mb_strtolower($text);
        $sections = [];

        if (str_contains($lower, 'portfólio') || str_contains($lower, 'publicados no portfólio')) {
            $sections[] = ['type' => 'site.cases', 'data' => ['title' => '', 'items' => [], 'layout' => 'par']];
        }

        $quotes = $own;
        if ($quotes === []) {
            if (str_contains($lower, 'depoimentos publicados')) {
                $quotes = str_contains($lower, 'seleção') ? array_slice($this->quotes, 0, 3) : $this->quotes;
            } else {
                foreach ($this->quotes as $quote) {
                    if (str_contains($text, $quote['author'])) {
                        $quotes[] = $quote;
                    }
                }
            }
        }

        $wantsLogos = isset($block['keyed']['Logos']) || str_contains($lower, 'logos') || str_contains($lower, 'mesma lista da home');

        if ($wantsLogos) {
            $sections[] = [
                'type' => 'site.logos',
                'data' => [
                    'title' => $block['keyed']['H2'] ?? 'Clientes',
                    'items' => array_map(fn (string $name) => [
                        'name' => $name,
                        'image' => isset(self::LOGOS[$name]) ? '/assets/clientes/'.self::LOGOS[$name].'.png' : '',
                        'image_alt' => $name,
                    ], $this->logoNames),
                ],
            ];
        }

        if ($quotes !== []) {
            $sections[] = [
                'type' => 'site.quotes',
                'data' => [
                    'title' => $own !== [] ? ($block['keyed']['H2'] ?? '') : '',
                    'items' => array_map(fn (array $q) => [
                        'quote' => $this->rich($q['quote']),
                        'author' => $q['author'],
                        'company' => $q['company'],
                    ], $quotes),
                ],
            ];
        }

        return $sections;
    }

    /**
     * Hubs e páginas de serviço recebem um bloco de cases antes da FAQ/CTA (vazio enquanto não houver case vinculado).
     *
     * @param  list<array{type: string, data: array<string, mixed>}>  $sections
     * @param  array<string, mixed>  $page
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function withCasesBlock(array $sections, array $page): array
    {
        $isHub = false;
        foreach (self::HUB_FILES as $prefix) {
            if (str_starts_with(basename($page['file']), $prefix)) {
                $isHub = true;
            }
        }

        if (! $isHub) {
            return $sections;
        }

        foreach ($sections as $section) {
            if ($section['type'] === 'site.cases') {
                return $sections;
            }
        }

        $at = count($sections);
        foreach ($sections as $i => $section) {
            if (in_array($section['type'], ['site.faq', 'site.cta'], true)) {
                $at = $i;
                break;
            }
        }

        array_splice($sections, $at, 0, [['type' => 'site.cases', 'data' => ['title' => '', 'items' => [], 'layout' => 'par']]]);

        return $sections;
    }

    /**
     * @return array{title: string, body: string, href: string}
     */
    private function listItem(string $raw): array
    {
        $href = '';
        if (preg_match('/^(.*?)\s*→\s*`([^`]+)`\s*$/u', $raw, $m)) {
            $raw = $m[1];
            $href = str_starts_with($m[2], 'https://wa.me/') ? $this->whatsappUrl : $m[2];
        }

        if (preg_match('/^\*\*(.+?)\*\*\s*(.*)$/u', $raw, $m)) {
            return ['title' => rtrim(trim($m[1]), '.:'), 'body' => $this->rich($m[2]), 'href' => $href];
        }

        return ['title' => trim($raw), 'body' => '', 'href' => $href];
    }

    /**
     * @return array{title: string, body: string}
     */
    private function step(string $raw): array
    {
        if (preg_match('/^\*\*(.+?)\*\*\s*(.*)$/u', $raw, $m)) {
            return ['title' => rtrim(trim($m[1]), '.'), 'body' => $this->rich($m[2])];
        }

        return ['title' => $raw, 'body' => ''];
    }

    /**
     * @return array{question: string, answer: string}|null
     */
    private function faqItem(string $raw): ?array
    {
        if (! preg_match('/^\*\*(.+?)\*\*\s*(.+)$/u', $raw, $m)) {
            return null;
        }

        return ['question' => trim($m[1]), 'answer' => $this->rich($m[2])];
    }

    /**
     * @return array{quote: string, author: string, company: string}|null
     */
    private function quoteItem(string $raw): ?array
    {
        if (! preg_match('/^"(.+)"\s*—\s*([^,]+),\s*(.+)$/u', $raw, $m)) {
            return null;
        }

        return ['quote' => trim($m[1]), 'author' => trim($m[2]), 'company' => trim($m[3])];
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function notes(array $block): string
    {
        $notes = [];
        foreach (self::NOTE_KEYS as $key) {
            if (isset($block['keyed'][$key])) {
                $notes[] = ($key === 'Nota' ? '' : $key.': ').$block['keyed'][$key];
            }
        }

        return $notes === [] ? '' : $this->rich(implode("\n", array_map(fn (string $n) => Str::ucfirst($n), $notes)));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function link(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['', ''];
        }

        if (preg_match('/^(.*?)\s*→\s*(.+)$/u', $value, $m)) {
            return [trim($m[1]), $this->target(trim($m[2]))];
        }

        return [$value, str_contains($value, 'WhatsApp') ? $this->whatsappUrl : ''];
    }

    private function target(string $target): string
    {
        if (preg_match('/^`([^`]+)`$/u', $target, $m)) {
            return $m[1];
        }

        return match (mb_strtolower($target)) {
            'whatsapp' => $this->whatsappUrl,
            'freshdesk' => self::FRESHDESK_URL,
            default => $target,
        };
    }

    private function rich(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $paragraphs = array_filter(array_map('trim', preg_split('/\n+/u', $text) ?: []));

        return implode('', array_map(fn (string $p) => '<p>'.$this->linkify(e($p)).'</p>', $paragraphs));
    }

    private function linkify(string $escaped): string
    {
        $escaped = preg_replace_callback(
            '/\s*→\s*`([^`]+)`\s*$/u',
            fn (array $m) => ' <a href="'.$m[1].'">'.e($this->pageLabel($m[1])).'</a>',
            $escaped
        ) ?? $escaped;

        return preg_replace_callback(
            '/`(\/[^`]*)`/u',
            fn (array $m) => '<a href="'.$m[1].'">'.e($this->pageLabel($m[1])).'</a>',
            $escaped
        ) ?? $escaped;
    }

    private function pageLabel(string $path): string
    {
        $page = $this->pages[$path] ?? null;
        $hero = $page ? $this->firstBlock($page, 'hero') : null;
        $h1 = $hero ? (string) ($hero['keyed']['H1'] ?? '') : '';

        return $h1 !== '' ? rtrim($h1, '.') : $path;
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>|null
     */
    private function firstBlock(array $page, string $kind): ?array
    {
        foreach ($page['blocks'] as $block) {
            if ($block['kind'] === $kind) {
                return $block;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, string>
     */
    private function keyed(array $block): array
    {
        return $block['keyed'];
    }
}
