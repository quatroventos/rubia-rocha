<?php

namespace App\Services\Seo;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Support\Seo;

/**
 * Nota SEO on-page por palavra-chave, calculada sobre o conteúdo ainda não salvo do editor.
 * Cada verificação traz peso, estado e, quando dá para resolver reescrevendo texto, o alvo da correção por IA.
 *
 * @phpstan-type Check array{id: string, label: string, status: 'good'|'ok'|'bad', message: string, weight: int, fix: array<string, mixed>|null}
 */
class SeoAnalyzer
{
    public const DENSITY_MIN = 0.5;

    public const DENSITY_MAX = 2.5;

    public const WORDS_GOOD = 300;

    public const WORDS_OK = 150;

    public const VOLUME_GOOD = 50;

    public const VOLUME_OK = 10;

    public const DIFFICULTY_GOOD = 40;

    public const DIFFICULTY_OK = 70;

    public function __construct(private MarketInsights $market) {}

    /**
     * @param  array{kind: string, id?: int|null, keyword?: string|null, seo_title?: string|null, seo_description?: string|null, slug?: string|null, home?: bool, title?: string|null, summary?: string|null, client?: string|null, cover_alt?: string|null, og_image_alt?: string|null, sections: list<array{type: string, data: array<string, mixed>}>}  $input
     * @return array{score: int|null, keyword: string, words: int, rendered: bool, checks: list<Check>}
     */
    public function analyze(array $input, ?PageDocument $document = null): array
    {
        $keyword = trim((string) ($input['keyword'] ?? ''));
        $document ??= PageDocument::make($input['kind'], $input['sections'], $input);
        $words = $document->wordCount();
        $rendered = $document->structure !== null;

        if ($keyword === '') {
            return [
                'score' => null,
                'keyword' => '',
                'words' => $words,
                'rendered' => $rendered,
                'checks' => [$this->check('keyword', 'Palavra-chave', 'bad', 'Defina a palavra-chave principal para calcular a nota.', 0)],
            ];
        }

        $site = SiteSetting::query()->first();
        $title = Seo::composeTitle($this->effectiveTitle($input), $site);
        $description = $this->effectiveDescription($input, $site);
        $checks = [];

        $checks[] = Keyword::contains($title, $keyword)
            ? $this->check('title_keyword', 'Palavra-chave no título SEO', 'good', 'O título SEO usa a palavra-chave.', 3)
            : $this->check('title_keyword', 'Palavra-chave no título SEO', Keyword::partial($title, $keyword) ? 'ok' : 'bad', 'Inclua "'.$keyword.'" no título SEO.', 3, ['type' => 'seo_title']);

        $position = Keyword::position($title, $keyword);
        if ($position !== null) {
            $checks[] = $position <= 0.2
                ? $this->check('title_start', 'Palavra-chave no começo do título', 'good', 'A palavra-chave abre o título.', 1)
                : $this->check('title_start', 'Palavra-chave no começo do título', 'ok', 'Mova a palavra-chave para o começo do título.', 1, ['type' => 'seo_title']);
        }

        $titleLength = mb_strlen($title);
        $checks[] = $titleLength <= Seo::TITLE_MAX && $titleLength >= 30
            ? $this->check('title_length', 'Tamanho do título', 'good', $titleLength.' de '.Seo::TITLE_MAX.' caracteres.', 1)
            : $this->check('title_length', 'Tamanho do título', $titleLength > Seo::TITLE_MAX ? 'bad' : 'ok', $titleLength > Seo::TITLE_MAX ? 'O Google corta títulos acima de '.Seo::TITLE_MAX.' caracteres ('.$titleLength.').' : 'Título curto ('.$titleLength.'). Use até '.Seo::TITLE_MAX.' caracteres.', 1, ['type' => 'seo_title']);

        if ($description === '') {
            $checks[] = $this->check('description_keyword', 'Palavra-chave na description', 'bad', 'A página está sem description.', 3, ['type' => 'seo_description']);
        } else {
            $checks[] = Keyword::contains($description, $keyword)
                ? $this->check('description_keyword', 'Palavra-chave na description', 'good', 'A description usa a palavra-chave.', 3)
                : $this->check('description_keyword', 'Palavra-chave na description', Keyword::partial($description, $keyword) ? 'ok' : 'bad', 'Inclua "'.$keyword.'" na description.', 3, ['type' => 'seo_description']);

            $length = mb_strlen($description);
            $checks[] = $length >= Seo::DESCRIPTION_MIN && $length <= Seo::DESCRIPTION_MAX
                ? $this->check('description_length', 'Tamanho da description', 'good', $length.' caracteres, dentro da faixa.', 1)
                : $this->check('description_length', 'Tamanho da description', 'ok', $length.' caracteres. O ideal fica entre '.Seo::DESCRIPTION_MIN.' e '.Seo::DESCRIPTION_MAX.'.', 1, ['type' => 'seo_description']);
        }

        if (empty($input['home'])) {
            $slug = str_replace('-', ' ', (string) ($input['slug'] ?? ''));
            $checks[] = Keyword::contains($slug, $keyword)
                ? $this->check('slug_keyword', 'Palavra-chave na URL', 'good', 'O slug usa a palavra-chave.', 2)
                : $this->check('slug_keyword', 'Palavra-chave na URL', Keyword::partial($slug, $keyword) ? 'ok' : 'bad', 'O slug não usa a palavra-chave. Trocar a URL de página publicada pede redirecionamento.', 2);
        }

        if ($document->h1 !== null && $document->h1['text'] !== '') {
            $checks[] = Keyword::contains($document->h1['text'], $keyword)
                ? $this->check('h1_keyword', 'Palavra-chave no H1', 'good', 'O título principal usa a palavra-chave.', 3)
                : $this->check('h1_keyword', 'Palavra-chave no H1', Keyword::partial($document->h1['text'], $keyword) ? 'ok' : 'bad', 'O título principal ('.$document->h1['label'].') não usa "'.$keyword.'".', 3, $document->h1['target']);
        } else {
            $checks[] = $this->check('h1_keyword', 'Palavra-chave no H1', 'bad', 'A página não tem título principal.', 3);
        }

        if ($document->intro !== null) {
            $intro = implode(' ', array_slice(Keyword::words($document->intro['text']), 0, 120));
            $checks[] = Keyword::contains($intro, $keyword)
                ? $this->check('intro_keyword', 'Palavra-chave na introdução', 'good', 'A introdução usa a palavra-chave.', 2)
                : $this->check('intro_keyword', 'Palavra-chave na introdução', Keyword::partial($intro, $keyword) ? 'ok' : 'bad', 'Use "'.$keyword.'" no primeiro parágrafo ('.$document->intro['label'].').', 2, $document->intro['target']);
        }

        if ($document->headings !== []) {
            $withKeyword = array_filter($document->headings, fn (array $heading) => Keyword::contains($heading['text'], $keyword) || Keyword::partial($heading['text'], $keyword));
            $checks[] = $withKeyword !== []
                ? $this->check('subheading_keyword', 'Palavra-chave nos subtítulos', 'good', count($withKeyword).' de '.count($document->headings).' subtítulos tratam do tema.', 2)
                : $this->check('subheading_keyword', 'Palavra-chave nos subtítulos', 'bad', 'Nenhum subtítulo (H2) usa a palavra-chave ou variação.', 2, $this->firstTarget($document->headings));
        }

        $occurrences = Keyword::count($document->fullText(), $keyword);
        $density = $words > 0 ? round($occurrences * count(Keyword::words($keyword)) / $words * 100, 1) : 0.0;
        $longest = $this->longestBlock($document);

        if ($density >= self::DENSITY_MIN && $density <= self::DENSITY_MAX) {
            $checks[] = $this->check('density', 'Densidade da palavra-chave', 'good', $this->occurrences($occurrences).', '.$this->percent($density).' do texto.', 2);
        } elseif ($density > self::DENSITY_MAX) {
            $checks[] = $this->check('density', 'Densidade da palavra-chave', 'bad', $this->occurrences($occurrences).' ('.$this->percent($density).'). Repetição demais soa forçada.', 2, $this->densityTarget($document, $keyword));
        } else {
            $checks[] = $this->check('density', 'Densidade da palavra-chave', $occurrences > 0 ? 'ok' : 'bad', $this->occurrences($occurrences).' ('.$this->percent($density).'). O ideal fica entre '.$this->percent(self::DENSITY_MIN).' e '.$this->percent(self::DENSITY_MAX).'.', 2, $longest['target'] ?? null);
        }

        $checks[] = $words >= self::WORDS_GOOD
            ? $this->check('content_length', 'Quantidade de texto', 'good', $words.' palavras.', 1)
            : $this->check('content_length', 'Quantidade de texto', $words >= self::WORDS_OK ? 'ok' : 'bad', $words.' palavras. Páginas com menos de '.self::WORDS_GOOD.' tendem a ranquear pior.', 1);

        if ($document->alts !== []) {
            $checks[] = array_filter($document->alts, fn (array $alt) => Keyword::contains($alt['text'], $keyword) || Keyword::partial($alt['text'], $keyword)) !== []
                ? $this->check('image_alt', 'Palavra-chave no alt das imagens', 'good', 'Pelo menos uma imagem descreve o tema.', 1)
                : $this->check('image_alt', 'Palavra-chave no alt das imagens', 'ok', 'Nenhum alt usa a palavra-chave. Use quando descrever a imagem com honestidade.', 1, $this->firstTarget($document->alts));
        }

        if ($document->structure !== null) {
            array_push($checks, ...$this->structureChecks($document->structure));
        }

        $duplicates = $this->duplicates($input['kind'], (int) ($input['id'] ?? 0), $keyword);
        $checks[] = $duplicates === []
            ? $this->check('unique_keyword', 'Palavra-chave exclusiva', 'good', 'Nenhuma outra página usa esta palavra-chave.', 1)
            : $this->check('unique_keyword', 'Palavra-chave exclusiva', 'bad', 'Também usada em: '.implode(', ', $duplicates).'. Páginas competem entre si.', 1);

        $report = $this->market->cached($keyword, $input['path'] ?? null);

        if ($report !== null) {
            array_push($checks, ...$this->marketChecks($report, $input['kind'], $title));
        }

        return [
            'score' => $this->score($checks),
            'keyword' => $keyword,
            'words' => $words,
            'rendered' => $rendered,
            'checks' => $checks,
        ];
    }

    /**
     * Checks que só o HTML renderizado responde: quantos H1 existem, saltos de nível e imagens sem alt.
     *
     * @param  array{h1_count: int, skips: list<string>, images: int, missing_alts: int}  $structure
     * @return list<Check>
     */
    private function structureChecks(array $structure): array
    {
        $checks = [];
        $h1 = $structure['h1_count'];
        $skips = $structure['skips'];

        $checks[] = match (true) {
            $h1 === 0 => $this->check('heading_structure', 'Hierarquia dos títulos', 'bad', 'A página renderizada não tem H1.', 2),
            $h1 > 1 => $this->check('heading_structure', 'Hierarquia dos títulos', 'bad', 'A página renderizada tem '.$h1.' H1. Deixe um só e passe os outros para H2.', 2),
            $skips !== [] => $this->check('heading_structure', 'Hierarquia dos títulos', 'ok', 'Salto de nível: '.implode('; ', array_slice($skips, 0, 3)).'.', 2),
            default => $this->check('heading_structure', 'Hierarquia dos títulos', 'good', 'Um H1 e subtítulos em sequência, sem saltos.', 2),
        };

        if ($structure['images'] > 0) {
            $checks[] = $structure['missing_alts'] === 0
                ? $this->check('image_alt_missing', 'Imagens com alt', 'good', 'Todas as '.$structure['images'].' imagens têm alt.', 1)
                : $this->check('image_alt_missing', 'Imagens com alt', 'bad', $structure['missing_alts'].' de '.$structure['images'].' imagens sem alt.', 1);
        }

        return $checks;
    }

    /**
     * Checks com os dados do OpenSEO já em cache: demanda, dificuldade, intenção e o que o Search Console mostra.
     *
     * @param  array<string, mixed>  $report
     * @return list<Check>
     */
    private function marketChecks(array $report, string $kind, string $title): array
    {
        $checks = [];
        $metrics = $report['metrics'] ?? null;
        $alternatives = implode(', ', array_map(
            fn (array $row) => '"'.$row['keyword'].'"'.($row['volume'] !== null ? ' ('.$row['volume'].'/mês)' : ''),
            array_slice(array_filter($report['related'] ?? [], fn (array $row) => ($row['volume'] ?? 0) >= self::VOLUME_GOOD), 0, 3),
        ));

        if (is_array($metrics) && $metrics['volume'] !== null) {
            $volume = (int) $metrics['volume'];
            $checks[] = $volume >= self::VOLUME_GOOD
                ? $this->check('keyword_demand', 'Demanda da palavra-chave', 'good', $volume.' buscas por mês no Google.', 2)
                : $this->check('keyword_demand', 'Demanda da palavra-chave', $volume >= self::VOLUME_OK ? 'ok' : 'bad', $volume.' buscas por mês.'.($alternatives !== '' ? ' Alternativas com mais busca: '.$alternatives.'.' : ' Considere um termo mais buscado.'), 2);
        }

        if (is_array($metrics) && $metrics['difficulty'] !== null) {
            $difficulty = (int) $metrics['difficulty'];
            $checks[] = $difficulty <= self::DIFFICULTY_GOOD
                ? $this->check('keyword_difficulty', 'Dificuldade da palavra-chave', 'good', 'Dificuldade '.$difficulty.'/100. Dá para competir.', 1)
                : $this->check('keyword_difficulty', 'Dificuldade da palavra-chave', $difficulty <= self::DIFFICULTY_OK ? 'ok' : 'bad', 'Dificuldade '.$difficulty.'/100. O top 10 é disputado; um termo mais específico tende a ranquear antes.', 1);
        }

        if (is_array($metrics) && $metrics['intent'] !== null) {
            $commercial = in_array($metrics['intent'], ['comercial', 'transacional'], true);
            $checks[] = $kind === 'case' || $commercial
                ? $this->check('keyword_intent', 'Intenção da busca', 'good', 'Busca '.$metrics['intent'].', compatível com a página.', 1)
                : $this->check('keyword_intent', 'Intenção da busca', 'ok', 'Busca '.$metrics['intent'].': quem pesquisa quer aprender, e a página vende um serviço. Um termo comercial converte melhor.', 1);
        }

        $queries = $report['search_console'] ?? [];

        if (is_array($queries) && $queries !== []) {
            $top = $queries[0];
            $checks[] = Keyword::contains($title, $top['query']) || Keyword::partial($title, $top['query'])
                ? $this->check('search_console_fit', 'Buscas reais no título', 'good', 'O título cobre "'.$top['query'].'", a busca que mais mostra a página.', 1)
                : $this->check('search_console_fit', 'Buscas reais no título', 'ok', 'A página aparece mais para "'.$top['query'].'" ('.$top['impressions'].' impressões em 28 dias) e o título não cobre esse termo.', 1, ['type' => 'seo_title']);
        }

        return $checks;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function effectiveTitle(array $input): string
    {
        $title = trim((string) ($input['seo_title'] ?? ''));

        if ($title === '' && $input['kind'] === 'case') {
            return trim((string) ($input['title'] ?? '')).' | Case '.trim((string) ($input['client'] ?? ''));
        }

        return $title;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function effectiveDescription(array $input, ?SiteSetting $site): string
    {
        $description = trim((string) ($input['seo_description'] ?? ''));

        if ($description !== '') {
            return $description;
        }

        if ($input['kind'] === 'case' && filled($input['summary'] ?? null)) {
            return trim((string) $input['summary']);
        }

        return trim((string) (Seo::defaults($site)['seo_description'] ?? ''));
    }

    /**
     * @param  list<array<string, mixed>>  $chunks
     * @return array<string, mixed>|null
     */
    private function firstTarget(array $chunks): ?array
    {
        foreach ($chunks as $chunk) {
            if (! empty($chunk['target'])) {
                return $chunk['target'];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function longestBlock(PageDocument $document): ?array
    {
        $blocks = array_values(array_filter($document->blocks, fn (array $block) => ! empty($block['target'])));
        usort($blocks, fn (array $a, array $b) => mb_strlen($b['text']) <=> mb_strlen($a['text']));

        return $blocks[0] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function densityTarget(PageDocument $document, string $keyword): ?array
    {
        $blocks = array_values(array_filter($document->blocks, fn (array $block) => ! empty($block['target'])));
        usort($blocks, fn (array $a, array $b) => Keyword::count($b['text'], $keyword) <=> Keyword::count($a['text'], $keyword));

        return $blocks[0]['target'] ?? null;
    }

    /**
     * @return list<string>
     */
    private function duplicates(string $kind, int $id, string $keyword): array
    {
        $normalized = Keyword::normalize($keyword);
        $names = [];

        Page::query()->originals()->whereNotNull('seo_keyword')->get(['id', 'slug', 'seo_title', 'seo_keyword'])
            ->each(function (Page $page) use ($kind, $id, $normalized, &$names): void {
                if (($kind !== 'page' || $page->id !== $id) && Keyword::normalize((string) $page->seo_keyword) === $normalized) {
                    $names[] = trim((string) preg_replace('/\s*\|.*$/', '', (string) $page->seo_title)) ?: '/'.$page->slug;
                }
            });

        CaseStudy::query()->originals()->whereNotNull('seo_keyword')->get(['id', 'client', 'seo_keyword'])
            ->each(function (CaseStudy $case) use ($kind, $id, $normalized, &$names): void {
                if (($kind !== 'case' || $case->id !== $id) && Keyword::normalize((string) $case->seo_keyword) === $normalized) {
                    $names[] = 'Case '.$case->client;
                }
            });

        return array_slice($names, 0, 4);
    }

    /**
     * @param  list<Check>  $checks
     */
    private function score(array $checks): int
    {
        $total = 0;
        $earned = 0.0;

        foreach ($checks as $check) {
            $total += $check['weight'];
            $earned += $check['weight'] * ['good' => 1, 'ok' => 0.5, 'bad' => 0][$check['status']];
        }

        return $total > 0 ? (int) round($earned / $total * 100) : 0;
    }

    private function occurrences(int $count): string
    {
        return $count.($count === 1 ? ' ocorrência' : ' ocorrências');
    }

    private function percent(float $value): string
    {
        return str_replace('.', ',', (string) $value).'%';
    }

    /**
     * @param  'good'|'ok'|'bad'  $status
     * @param  array<string, mixed>|null  $fix
     * @return Check
     */
    private function check(string $id, string $label, string $status, string $message, int $weight, ?array $fix = null): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'weight' => $weight,
            'fix' => $status === 'good' ? null : $fix,
        ];
    }
}
