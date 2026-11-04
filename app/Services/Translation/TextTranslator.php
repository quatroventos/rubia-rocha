<?php

namespace App\Services\Translation;

use App\Models\SiteSetting;
use App\Support\Locales;
use App\Support\Seo;
use Illuminate\Support\Str;
use RuntimeException;

class TextTranslator
{
    private const BATCH_CHARS = 6000;

    /**
     * Nomes próprios que nunca são traduzidos.
     *
     * @var list<string>
     */
    public const KEEP = [
        'Rubia Rocha Fotografia',
        'Rubia Rocha',
        'WhatsApp',
        'WordPress',
        'Laravel',
        'React',
        'OpenAI',
        'ChatGPT',
        'LLM',
        'SaaS',
        'ERP',
        'CRM',
        'Curitiba',
    ];

    public function __construct(private OpenAiClient $client) {}

    public function configured(): bool
    {
        return $this->client->configured();
    }

    /**
     * @param  array<string, string>  $strings  id => texto em pt-BR
     * @return array<string, string> id => texto traduzido
     */
    public function translate(array $strings, string $locale, string $context = ''): array
    {
        $strings = array_filter($strings, fn ($text) => is_string($text) && trim($text) !== '');
        if ($strings === []) {
            return [];
        }

        $result = [];
        foreach ($this->batches($strings) as $batch) {
            $result += $this->request($batch, $locale, $context);
        }

        $missing = array_diff_key($strings, $result);
        if ($missing !== []) {
            foreach ($this->batches($missing) as $batch) {
                $result += $this->request($batch, $locale, $context);
            }
        }

        $missing = array_diff(array_keys($strings), array_keys($result));
        if ($missing !== []) {
            throw new RuntimeException('Tradução incompleta: '.count($missing).' trecho(s) sem retorno.');
        }

        return $result;
    }

    /**
     * O modelo às vezes devolve o lote sem algum item; quem chama repete só os ids que faltaram.
     *
     * @param  array<string, string>  $batch
     * @return array<string, string>
     */
    private function request(array $batch, string $locale, string $context): array
    {
        $result = [];
        $items = [];
        foreach ($batch as $id => $text) {
            $items[] = ['id' => (string) $id, 'text' => $text];
        }

        $decoded = $this->client->json(
            $this->systemPrompt($locale),
            ($context !== '' ? "Context: {$context}\n\n" : '')
                .'Translate every item. Return the same ids.'."\n\n"
                .json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'translations',
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['items'],
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['id', 'text'],
                            'properties' => [
                                'id' => ['type' => 'string'],
                                'text' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ]
        );

        foreach ((array) ($decoded['items'] ?? []) as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : null;
            $text = is_array($item) ? ($item['text'] ?? null) : null;
            if (is_string($id) && is_string($text) && array_key_exists($id, $batch)) {
                $result[$id] = $text;
            }
        }

        return $result;
    }

    /**
     * @param  array{slug: string, title: string, description: string, summary: string}  $source
     * @return array{slug: string, seo_title: string, seo_description: string}
     */
    public function seo(array $source, string $locale): array
    {
        $language = Locales::aiName($locale);

        $decoded = $this->client->json(
            'You are a senior SEO specialist writing metadata in '.$language.' for the website of Rubia Rocha Fotografia, '
                .'a family, maternity, couple and event photographer from Curitiba, Brazil. Do not translate literally: research intent and use the terms people '
                .'actually search for in '.$language.'. Title: up to '.Seo::titleBudget(SiteSetting::query()->first()).' characters including spaces, no brand suffix (it is added automatically). '
                .'Description: '.Seo::DESCRIPTION_MIN.' to '.Seo::DESCRIPTION_MAX.' characters including spaces, declarative, no clickbait, no emojis. Slug: short, lowercase ASCII, '
                .'words separated by hyphens, in '.$language.', main keyword first. Keep brand and product names untouched: '
                .implode(', ', self::KEEP).'.',
            json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            'seo',
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['slug', 'seo_title', 'seo_description'],
                'properties' => [
                    'slug' => ['type' => 'string'],
                    'seo_title' => ['type' => 'string'],
                    'seo_description' => ['type' => 'string'],
                ],
            ]
        );

        return [
            'slug' => Str::slug((string) ($decoded['slug'] ?? '')),
            'seo_title' => Seo::fit((string) ($decoded['seo_title'] ?? ''), Seo::titleBudget(SiteSetting::query()->first())),
            'seo_description' => Seo::fit((string) ($decoded['seo_description'] ?? ''), Seo::DESCRIPTION_MAX),
        ];
    }

    private function systemPrompt(string $locale): string
    {
        $language = Locales::aiName($locale);

        return 'You translate website copy from Brazilian Portuguese to '.$language.' for Rubia Rocha Fotografia, '
            .'a family, maternity, couple and event photographer from Curitiba, Brazil. Rules: '
            .'1) Write natural, idiomatic marketing copy for that market, not a literal translation. '
            .'2) Keep the voice declarative, direct and confident; short sentences; no exclamation marks; no emojis. '
            .'3) Preserve every HTML tag, attribute and entity exactly as received; translate only the human-readable text between tags. '
            .'Keep markup such as <mark>, <strong>, <em>, <a href="..."> and <br> in equivalent positions. '
            .'4) Never change URLs, e-mail addresses, phone numbers, prices, currencies, numbers, percentages or dates. '
            .'5) Never translate brand, client, product or person names. Always keep: '.implode(', ', self::KEEP).'. '
            .'6) Keep roughly the same length so the layout does not break. '
            .'7) If an item is already a proper name or a code, return it unchanged.';
    }

    /**
     * @param  array<string, string>  $strings
     * @return list<array<string, string>>
     */
    private function batches(array $strings): array
    {
        $batches = [];
        $current = [];
        $size = 0;

        foreach ($strings as $id => $text) {
            $length = mb_strlen($text);
            if ($current !== [] && $size + $length > self::BATCH_CHARS) {
                $batches[] = $current;
                $current = [];
                $size = 0;
            }

            $current[(string) $id] = $text;
            $size += $length;
        }

        if ($current !== []) {
            $batches[] = $current;
        }

        return $batches;
    }
}
