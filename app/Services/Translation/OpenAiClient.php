<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiClient
{
    public function configured(): bool
    {
        return filled(config('services.openai.key'));
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function json(string $system, string $user, string $schemaName, array $schema): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('OPENAI_API_KEY não configurada no .env.');
        }

        $response = Http::withToken((string) config('services.openai.key'))
            ->acceptJson()
            ->timeout((int) config('services.openai.timeout', 120))
            ->retry(2, 1500, throw: false)
            ->post(rtrim((string) config('services.openai.base_url'), '/').'/responses', [
                'model' => (string) config('services.openai.model'),
                'temperature' => 0.2,
                'store' => false,
                'instructions' => $system,
                'input' => $user,
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => $schemaName,
                        'strict' => true,
                        'schema' => $schema,
                    ],
                ],
            ]);

        if ($response->failed()) {
            $message = (string) ($response->json('error.message') ?? $response->body());

            throw new RuntimeException('OpenAI '.$response->status().': '.mb_substr($message, 0, 300));
        }

        if ($response->json('status') === 'incomplete') {
            throw new RuntimeException('OpenAI interrompeu a resposta: '.($response->json('incomplete_details.reason') ?? 'motivo desconhecido').'.');
        }

        $content = $this->outputText((array) $response->json('output', []));
        $decoded = $content !== '' ? json_decode($content, true) : null;

        if (! is_array($decoded)) {
            throw new RuntimeException('Resposta da OpenAI fora do formato esperado.');
        }

        return $decoded;
    }

    /**
     * @param  array<int, array<string, mixed>>  $output
     */
    private function outputText(array $output): string
    {
        $text = '';

        foreach ($output as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ((array) ($item['content'] ?? []) as $part) {
                if (($part['type'] ?? null) === 'refusal') {
                    throw new RuntimeException('OpenAI recusou: '.mb_substr((string) ($part['refusal'] ?? ''), 0, 300));
                }

                if (($part['type'] ?? null) === 'output_text') {
                    $text .= (string) ($part['text'] ?? '');
                }
            }
        }

        return $text;
    }
}
