<?php

namespace App\Services\Content;

use App\Cms\SectionCatalog;
use App\Models\SiteSetting;
use App\Services\Seo\Keyword;
use App\Services\Seo\MarketInsights;
use App\Services\Seo\PageDocument;
use App\Services\Translation\OpenAiClient;
use App\Support\Seo;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Reescreve os textos de uma seção de página com IA, usando o resto da página como contexto.
 * Só campos de texto corrido entram; links, âncoras, nomes próprios e números ficam como estão.
 */
class SectionCopyWriter
{
    public const MODES = ['melhorar', 'resumir', 'sintetizar'];

    public const SEO_FIELDS = ['seo_title', 'seo_description'];

    private const CONTEXT_CHARS = 12000;

    private const TEXT_TYPES = ['text', 'textarea', 'rich'];

    /**
     * Campos de texto que não são redação: endereços, identificadores, nomes e valores.
     */
    private const SKIP_NAMES = '/(^|_)(href|url|whatsapp|anchor|id|alt|author|company|name|value|prefix|price|year|icon|glyph|log)$/';

    private const ALLOWED_TAGS = '<p><br><strong><em><b><i><a><ul><ol><li>';

    public function __construct(private OpenAiClient $client, private MarketInsights $market) {}

    public function configured(): bool
    {
        return $this->client->configured();
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $sections
     * @param  array{kind: string, title?: string|null, description?: string|null, path?: string|null}  $context
     * @return array<string, mixed>
     */
    public function rewrite(array $sections, int $index, array $context, string $mode, string $instruction = '', ?string $path = null): array
    {
        $section = $sections[$index] ?? null;
        $definition = $section !== null ? SectionCatalog::find($section['type']) : null;

        if ($definition === null) {
            throw new RuntimeException('Seção desconhecida.');
        }

        $data = $section['data'];

        if ($path !== null) {
            $entries = array_intersect_key($this->entries($definition['fields'], $data, includeEmpty: true), [$path => true]);

            if ($entries === []) {
                throw new RuntimeException('Este campo não aceita texto gerado.');
            }
        } else {
            $entries = $this->entries($definition['fields'], $data);

            if ($entries === []) {
                throw new RuntimeException('Esta seção não tem texto para a IA trabalhar. Escreva um rascunho primeiro.');
            }
        }

        $result = $this->client->json(
            $this->systemPrompt(),
            $this->userPrompt($sections, $index, $definition['label'], $entries, $context, $mode, $instruction),
            'section_copy',
            $this->schema(array_keys($entries)),
        );

        foreach (is_array($result['texts'] ?? null) ? $result['texts'] : [] as $text) {
            $key = (string) ($text['path'] ?? '');
            $value = trim((string) ($text['value'] ?? ''));

            if (! isset($entries[$key]) || $value === '') {
                continue;
            }

            Arr::set($data, $key, $entries[$key]['rich'] ? $this->cleanHtml($value) : trim(strip_tags($value)));
        }

        return $data;
    }

    /**
     * Escreve o título SEO ou a description a partir do texto de todas as seções.
     *
     * @param  list<array{type: string, data: array<string, mixed>}>  $sections
     * @param  array{kind: string, title?: string|null, description?: string|null, path?: string|null}  $context
     * @param  array{seo_title?: string|null, seo_description?: string|null, seo_keyword?: string|null}  $seo
     */
    public function seo(array $sections, array $context, array $seo, string $field, string $mode, string $instruction = ''): string
    {
        if (! in_array($field, self::SEO_FIELDS, true)) {
            throw new RuntimeException('Este campo não aceita texto gerado.');
        }

        $outline = PageDocument::make($context['kind'], $sections, [
            'title' => $context['title'] ?? null,
            'summary' => $context['description'] ?? null,
        ])->outline(self::CONTEXT_CHARS);

        if ($outline === '' && blank($context['title'] ?? null) && blank($context['description'] ?? null)) {
            throw new RuntimeException('A página ainda não tem texto para a IA trabalhar.');
        }

        $keyword = trim((string) ($seo['seo_keyword'] ?? ''));
        $market = $this->marketContext($keyword, $context['path'] ?? null);

        $current = trim((string) ($seo[$field] ?? ''));
        $other = $field === 'seo_title' ? 'seo_description' : 'seo_title';

        $site = SiteSetting::query()->first();
        $max = $field === 'seo_title' ? Seo::titleBudget($site) : Seo::DESCRIPTION_MAX;
        $min = $field === 'seo_title' ? 0 : Seo::DESCRIPTION_MIN;
        $siteName = Seo::defaults($site)['og_site_name'];

        $rules = $field === 'seo_title'
            ? 'Título SEO: no máximo '.$max.' caracteres contando espaços, sem o nome do site (o template acrescenta "'.trim(mb_substr(Seo::composeTitle('x', $site), 1)).'"). Comece pelo termo que a pessoa buscaria no Google. Nada de dois-pontos seguidos de slogan.'
            : 'Description: entre '.$min.' e '.$max.' caracteres contando espaços, uma ou duas frases. Diga o que a pessoa encontra na página e para quem serve, com o termo principal na primeira frase. Sem chamadas genéricas como "saiba mais" ou "confira".';

        $tasks = [
            'melhorar' => 'Melhore o texto atual seguindo as regras.',
            'resumir' => 'Encurte o texto atual, mantendo o termo principal.',
            'sintetizar' => 'Condense o texto atual ao núcleo da mensagem.',
        ];

        $prompt = 'Página do site da Rubia Rocha Fotografia, fotógrafa de família, gestante, casal e eventos em Curitiba'
            .(filled($context['title'] ?? null) ? "\nTítulo: ".$context['title'] : '')
            .(filled($context['description'] ?? null) ? "\nResumo: ".$context['description'] : '')
            .(filled($context['path'] ?? null) ? "\nEndereço: ".$context['path'] : '')
            ."\n\nPágina completa, na ordem de leitura (leia tudo antes de escrever):\n".($outline !== '' ? $outline : '(sem seções)')
            .($keyword !== '' ? "\n\nPalavra-chave principal: ".$keyword."\nUse a palavra-chave exata, de forma natural, de preferência no começo. Não repita a palavra-chave no mesmo campo." : '')
            .$market
            .(filled($seo[$other] ?? null) ? "\n\n".($other === 'seo_title' ? 'Título SEO atual' : 'Description atual').' (contexto, não reescreva): '.$seo[$other] : '')
            ."\n\nCampo: ".($field === 'seo_title' ? 'Título SEO' : 'Meta description')
            ."\nRegras do campo: ".$rules
            .$this->extraInstructions($field === 'seo_title' ? 'seo_title' : 'seo_description')
            ."\n\nTarefa: ".($current === '' ? 'Escreva o campo do zero a partir do texto da página.' : $tasks[$mode]."\nTexto atual: ".$current)
            .($instruction !== '' ? "\nInstrução de quem edita: ".$instruction : '')
            ."\n\nDevolva texto puro, sem HTML, sem asteriscos e sem aspas.";

        $value = $this->seoValue($prompt, $field, $siteName);
        $penalty = fn (string $text): int => abs($this->distance(mb_strlen($text), $min, $max)) + ($keyword !== '' && ! Keyword::contains($text, $keyword) ? 1000 : 0);

        if ($value !== '' && $penalty($value) > 0) {
            $length = mb_strlen($value);
            $problems = array_filter([
                $length > $max || $length < $min ? 'tinha '.$length.' caracteres; use '.($min > 0 ? 'entre '.$min.' e '.$max : 'no máximo '.$max).' contando espaços' : null,
                $keyword !== '' && ! Keyword::contains($value, $keyword) ? 'não usou a palavra-chave exata "'.$keyword.'"' : null,
            ]);
            $retry = $this->seoValue($prompt."\n\nSua resposta anterior (\"".$value.'") '.implode(' e ', $problems).'. Reescreva corrigindo.', $field, $siteName);

            if ($retry !== '' && $penalty($retry) <= $penalty($value)) {
                $value = $retry;
            }
        }

        if ($value === '') {
            throw new RuntimeException('A IA não devolveu texto. Tente de novo.');
        }

        return Seo::fit($value, $max);
    }

    /**
     * Reescreve um trecho da página para resolver um problema de palavra-chave apontado pela análise SEO.
     *
     * @param  array{text: string, raw: string, rich: bool, label: string}  $chunk
     * @param  array{kind: string, title?: string|null, path?: string|null}  $context
     */
    public function keywordRewrite(PageDocument $document, array $chunk, string $keyword, string $goal, int $max, array $context): string
    {
        $prompt = 'Página do site da Rubia Rocha Fotografia, fotógrafa de família, gestante, casal e eventos em Curitiba'
            .(filled($context['title'] ?? null) ? "\nTítulo: ".$context['title'] : '')
            .(filled($context['path'] ?? null) ? "\nEndereço: ".$context['path'] : '')
            ."\n\nPágina completa, na ordem de leitura (leia tudo antes de escrever):\n".$document->outline(self::CONTEXT_CHARS)
            ."\n\nPalavra-chave principal: ".$keyword
            .$this->marketContext($keyword, $context['path'] ?? null)
            ."\n\nCampo em edição: ".$chunk['label']
            ."\nFormato: ".($chunk['rich'] ? 'html' : 'texto')
            ."\nTexto atual: ".($chunk['raw'] !== '' ? $chunk['raw'] : '(vazio)')
            ."\n\nTarefa: ".$goal
            ."\nNo máximo ".$max.' caracteres. Mantenha o sentido, os fatos e o papel do campo na página. A palavra-chave entra de forma natural, nunca empilhada.'
            .$this->extraInstructions('keyword')
            ."\n\nDevolva só o novo texto do campo.";

        $result = $this->client->json($this->systemPrompt(), $prompt, 'keyword_rewrite', [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value'],
            'properties' => ['value' => ['type' => 'string']],
        ]);

        $value = trim((string) ($result['value'] ?? ''));

        if ($value === '') {
            throw new RuntimeException('A IA não devolveu texto. Tente de novo.');
        }

        return $chunk['rich'] ? $this->cleanHtml($value) : Seo::fit(trim(strip_tags($value)), $max);
    }

    /**
     * Dados do OpenSEO já em cache para a palavra-chave, prontos para entrar no prompt.
     */
    private function marketContext(string $keyword, ?string $path): string
    {
        $block = $keyword !== '' ? $this->market->promptContext($this->market->cached($keyword, $path)) : '';

        return $block !== '' ? "\n\n".$block : '';
    }

    private function seoValue(string $prompt, string $field, string $siteName): string
    {
        $result = $this->client->json($this->systemPrompt(), $prompt, 'seo_copy', [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['value'],
            'properties' => ['value' => ['type' => 'string']],
        ]);

        $value = trim(str_replace(['*', '"', '“', '”'], '', strip_tags((string) ($result['value'] ?? ''))));

        if ($field === 'seo_title' && $siteName !== '') {
            $value = trim((string) preg_replace('/\s*[|\-–—]\s*'.preg_quote($siteName, '/').'\s*$/iu', '', $value));
        }

        return $value;
    }

    /**
     * Quantos caracteres faltam (negativo) ou sobram (positivo) em relação à faixa aceita.
     */
    private function distance(int $length, int $min, int $max): int
    {
        return $length > $max ? $length - $max : ($length < $min ? $length - $min : 0);
    }

    /**
     * Textos preenchidos da seção, indexados pelo caminho no conteúdo (title, items.0.body, rotating.1).
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $data
     * @return array<string, array{label: string, rich: bool, value: string}>
     */
    private function entries(array $fields, array $data, string $prefix = '', string $labelPrefix = '', bool $includeEmpty = false): array
    {
        $entries = [];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            $type = (string) ($field['type'] ?? '');
            $label = $labelPrefix.($field['label'] ?? $name);
            $value = $data[$name] ?? null;

            if ($name === '' || preg_match(self::SKIP_NAMES, $name) === 1) {
                continue;
            }

            if ($type === 'repeater' && is_array($value)) {
                foreach (array_values($value) as $i => $item) {
                    $itemLabel = $label.' '.($i + 1);

                    if (! empty($field['simple'])) {
                        if (is_string($item) && ($includeEmpty || trim(strip_tags($item)) !== '')) {
                            $entries[$prefix.$name.'.'.$i] = ['label' => $itemLabel, 'rich' => ($field['itemType'] ?? '') === 'rich', 'value' => $item];
                        }

                        continue;
                    }

                    if (is_array($item)) {
                        $entries += $this->entries((array) ($field['fields'] ?? []), $item, $prefix.$name.'.'.$i.'.', $itemLabel.' > ', $includeEmpty);
                    }
                }

                continue;
            }

            if (in_array($type, self::TEXT_TYPES, true) && ($includeEmpty ? $value === null || is_string($value) : is_string($value) && trim(strip_tags($value)) !== '')) {
                $entries[$prefix.$name] = ['label' => $label, 'rich' => $type === 'rich', 'value' => (string) $value];
            }
        }

        return $entries;
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $sections
     * @param  array<string, array{label: string, rich: bool, value: string}>  $entries
     * @param  array{kind: string, title?: string|null, description?: string|null, path?: string|null}  $context
     */
    private function userPrompt(array $sections, int $index, string $label, array $entries, array $context, string $mode, string $instruction): string
    {
        $others = [];

        foreach ($sections as $i => $other) {
            if ($i === $index) {
                continue;
            }

            $definition = SectionCatalog::find($other['type']);
            $texts = array_map(fn (array $entry) => $this->plain($entry['value']), $this->entries($definition['fields'] ?? [], $other['data']));

            if ($texts !== []) {
                $others[] = '['.($definition['label'] ?? $other['type']).'] '.implode(' | ', $texts);
            }
        }

        $tasks = [
            'melhorar' => 'Melhore: reescreva com mais clareza e naturalidade, mantendo o tamanho aproximado e todas as informações.',
            'resumir' => 'Resuma: encurte cada texto em cerca de 40%, mantendo o essencial.',
            'sintetizar' => 'Sintetize: condense cada texto ao núcleo da mensagem, uma ideia por frase, sem redundâncias nem exemplos acessórios.',
        ];

        $fields = [];
        foreach ($entries as $path => $entry) {
            $fields[] = ['path' => $path, 'campo' => $entry['label'], 'formato' => $entry['rich'] ? 'html' : 'texto', 'texto' => $entry['value']];
        }

        $empty = array_filter($entries, fn (array $entry) => trim(strip_tags($entry['value'])) !== '') === [];
        $task = $empty
            ? 'Escreva: o campo está vazio. Crie o texto do zero, coerente com a seção, a página e o papel do campo.'
            : $tasks[$mode];

        $definition = SectionCatalog::find($sections[$index]['type']);
        $siblings = array_map(
            fn (array $entry) => $entry['label'].': '.$this->plain($entry['value']),
            array_diff_key($this->entries($definition['fields'] ?? [], $sections[$index]['data']), $entries),
        );

        return 'Página do site da Rubia Rocha Fotografia, fotógrafa de família, gestante, casal e eventos em Curitiba'
            .(filled($context['title'] ?? null) ? "\nTítulo: ".$context['title'] : '')
            .(filled($context['description'] ?? null) ? "\nDescrição: ".$context['description'] : '')
            .(filled($context['path'] ?? null) ? "\nEndereço: ".$context['path'] : '')
            ."\n\nOutras seções da página (contexto, não reescreva):\n"
            .($others !== [] ? mb_substr(implode("\n", $others), 0, self::CONTEXT_CHARS) : '(nenhuma)')
            ."\n\nSeção em edição: ".$label
            .($siblings !== [] ? "\nOutros campos desta seção (contexto, não reescreva):\n".implode("\n", $siblings) : '')
            ."\n\nTarefa: ".$task
            .($instruction !== '' ? "\nInstrução de quem edita: ".$instruction : '')
            ."\n\nCampos (devolva todos, pelo path):\n".json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function systemPrompt(): string
    {
        $custom = trim((string) config('services.openai.prompts.writer'));

        return ($custom !== '' ? $custom : self::defaultPrompt())."\n\n".WritingGuide::rules();
    }

    /**
     * Instruções extras do CMS (SEO > Integrações) somadas a uma tarefa.
     */
    private function extraInstructions(string $name): string
    {
        $extra = trim((string) config('services.openai.prompts.'.$name));

        return $extra !== '' ? "\nInstruções do site para este campo: ".$extra : '';
    }

    /**
     * Prompt de sistema padrão, usado quando o CMS não define outro.
     */
    public static function defaultPrompt(): string
    {
        return <<<'PROMPT'
Você é um escritor direto, conciso e natural. Elimine completamente o "AI Slop" e os vícios de linguagem robóticos.

DIRETRIZES DE ESCRITA:
- Proibido usar palavras e clichês de IA: "crucial", "fundamental", "no cenário atual", "revolucionário", "mergulhar profundamente", "vale ressaltar", "lembre-se que".
- Use voz ativa. Nunca use voz passiva desnecessária.
- Varie o tamanho das frases. Misture frases curtas e de impacto com frases médias.
- Seja direto ao ponto. Elimine introduções longas ("Claro, posso ajudar com isso!") e conclusões redundantes ("Em resumo...", "Concluindo...").
- Escreva em primeira pessoa, como a própria fotógrafa: acolhedor, afetivo e próximo, sem bajulação ou entusiasmo artificial.

REGRAS DO SITE:
- Escreva em português do Brasil.
- Use o contexto da página para entender o assunto e não repetir o que outras seções já dizem.
- Não invente fatos, números, clientes, prazos ou promessas que não estejam no texto ou no contexto.
- Não use travessão; prefira ponto, vírgula ou dois-pontos.
- Campos em formato html: devolva HTML simples, só com p, strong, em, a, ul, ol, li e br. Preserve os links (<a href>) existentes.
- Campos em formato texto: devolva texto puro, sem HTML.
- Títulos e rótulos de botão continuam curtos.
- Devolva todos os campos recebidos, cada um com o mesmo path.
PROMPT;
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, mixed>
     */
    private function schema(array $paths): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['texts'],
            'properties' => [
                'texts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['path', 'value'],
                        'properties' => [
                            'path' => ['type' => 'string', 'enum' => $paths],
                            'value' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Corrige só gramática, ortografia, pontuação e digitação, sem reescrever. A resposta é descartada se mexer na
     * marcação (tags HTML, *destaques*) ou mudar o texto além de uma correção.
     */
    public function proofread(string $text): string
    {
        if (trim(strip_tags($text)) === '') {
            throw new RuntimeException('O campo está vazio.');
        }

        $result = $this->client->json(
            <<<'PROMPT'
Você é revisor de texto. Corrija apenas erros de ortografia, acentuação, digitação, concordância, regência, crase e pontuação, no idioma em que o texto está escrito.
Não reescreva: não troque palavras por sinônimos, não mude a ordem, o tom, o estilo, o tamanho nem a estrutura das frases.
Mantenha nomes próprios, marcas, termos técnicos, estrangeirismos, números, links e emojis.
Mantenha todas as tags HTML com os mesmos atributos e na mesma posição, e os trechos entre *asteriscos* com os asteriscos.
Se não houver erro, devolva o texto idêntico.
PROMPT,
            "Texto:\n".$text,
            'proofread',
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['value'],
                'properties' => ['value' => ['type' => 'string']],
            ],
        );

        $value = (string) ($result['value'] ?? '');

        if (trim($value) === '') {
            throw new RuntimeException('A IA não devolveu o texto corrigido.');
        }

        if ($this->markup($value) !== $this->markup($text)) {
            throw new RuntimeException('A correção alterou a formatação do texto; nada foi aplicado.');
        }

        similar_text($this->plain($text), $this->plain($value), $percent);

        if ($percent < 85) {
            throw new RuntimeException('A IA mudou o texto além de uma correção; nada foi aplicado.');
        }

        return $value === $text ? $text : trim($value);
    }

    /**
     * @return array{tags: list<string>, marks: int}
     */
    private function markup(string $value): array
    {
        preg_match_all('/<[^>]+>/', $value, $tags);

        return ['tags' => $tags[0], 'marks' => substr_count(strip_tags($value), '*')];
    }

    private function plain(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>'], ' ', $value)))));
    }

    private function cleanHtml(string $html): string
    {
        $html = strip_tags($html, self::ALLOWED_TAGS);

        $html = (string) preg_replace_callback('/<(\w+)(\s[^>]*)?>/', function (array $match): string {
            $tag = strtolower($match[1]);

            if ($tag !== 'a') {
                return '<'.$tag.'>';
            }

            preg_match('/href\s*=\s*"([^"]*)"/i', $match[2] ?? '', $href);
            $url = $href[1] ?? '';

            return preg_match('#^(https?://|/|\#|mailto:|tel:)#i', $url) === 1 ? '<a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8', false).'">' : '<a>';
        }, $html);

        return trim($html);
    }
}
