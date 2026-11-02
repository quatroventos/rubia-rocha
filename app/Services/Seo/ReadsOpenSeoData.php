<?php

namespace App\Services\Seo;

/**
 * Leitura estrutural das respostas do OpenSEO. O formato exato das ferramentas não é
 * documentado, então os registros são achados pelas chaves e os campos por vários caminhos.
 *
 * @phpstan-type Metrics array{keyword: string, volume: int|null, difficulty: int|null, intent: string|null, intent_code: string|null, cpc: float|null}
 */
trait ReadsOpenSeoData
{
    /**
     * @var array<string, string>
     */
    private static array $intents = [
        'informational' => 'informativa',
        'commercial' => 'comercial',
        'transactional' => 'transacional',
        'navigational' => 'navegacional',
    ];

    /**
     * Falhas que o OpenSEO devolve como dados, sem marcar a chamada como erro:
     * `{ok: false, reason, connectUrl}` e `{status: 'error', error: {code, message, actionUrl}}`.
     *
     * @return array{message: string, action_url: string|null}|null
     */
    private function failure(mixed $data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        if (($data['ok'] ?? null) === false) {
            $reason = (string) ($data['reason'] ?? '');

            return [
                'message' => $this->failureMessage($reason, (string) ($data['message'] ?? '')),
                'action_url' => $data['connectUrl'] ?? data_get($data, 'meta.url'),
            ];
        }

        if (($data['status'] ?? null) === 'error') {
            return [
                'message' => $this->failureMessage((string) data_get($data, 'error.code', ''), (string) data_get($data, 'error.message', '')),
                'action_url' => data_get($data, 'error.actionUrl') ?? data_get($data, 'meta.url'),
            ];
        }

        return null;
    }

    private function failureMessage(string $code, string $message): string
    {
        return match (true) {
            $code === 'api_error' => 'O Google devolveu erro ao OpenSEO. Reconecte o Search Console nas integrações do projeto.',
            str_contains($code, 'ga4') || str_contains($code, 'analytics') => 'Google Analytics não conectado ao projeto no OpenSEO.',
            str_contains($code, 'not_connected') || str_contains($code, 'no_connection') || str_contains($code, 'missing') => 'Search Console não conectado ao projeto no OpenSEO.',
            default => $this->translateError($message !== '' ? $message : 'O OpenSEO não conseguiu ler estes dados.'),
        };
    }

    private function translateError(string $message): string
    {
        if (str_contains($message, 'HTTP 402') || str_contains(strtolower($message), 'insufficient')) {
            return 'Sem saldo no DataForSEO. Recarregue a conta para usar consultas pagas.';
        }

        return $message;
    }

    /**
     * Primeira lista de registros que tenha todas as chaves pedidas, em qualquer nível da resposta.
     *
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private function rows(mixed $data, array $keys): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (array_is_list($data) && $data !== []) {
            $records = array_values(array_filter($data, fn ($item) => is_array($item) && ! array_is_list($item)));
            $matches = array_values(array_filter($records, fn (array $record) => collect($keys)->every(fn (string $key) => $this->value($record, [$key, 'keys.0']) !== null)));

            if ($matches !== []) {
                return $matches;
            }
        }

        foreach ($data as $value) {
            $found = $this->rows($value, $keys);

            if ($found !== []) {
                return $found;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $paths
     */
    private function value(array $row, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = data_get($row, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return Metrics
     */
    private function keywordRow(array $row): array
    {
        $intent = $this->value($row, ['intent', 'searchIntent', 'search_intent', 'mainIntent', 'main_intent', 'search_intent_info.main_intent', 'intent.main']);

        if (is_array($intent)) {
            $intent = $intent['main'] ?? $intent[0] ?? null;
        }

        $intent = is_string($intent) ? strtolower(trim($intent)) : null;
        $cpc = $this->number($this->value($row, ['cpc', 'keyword_info.cpc']));

        return [
            'keyword' => trim((string) ($this->value($row, ['keyword']) ?? '')),
            'volume' => $this->integer($this->value($row, ['searchVolume', 'search_volume', 'volume', 'keyword_info.search_volume'])),
            'difficulty' => $this->integer($this->value($row, ['keywordDifficulty', 'keyword_difficulty', 'difficulty', 'kd', 'keyword_properties.keyword_difficulty'])),
            'intent' => $intent !== null && $intent !== '' && $intent !== 'unknown' ? (self::$intents[$intent] ?? $intent) : null,
            'intent_code' => $intent !== null && isset(self::$intents[$intent]) ? $intent : null,
            'cpc' => $cpc !== null ? round($cpc, 2) : null,
        ];
    }
}
