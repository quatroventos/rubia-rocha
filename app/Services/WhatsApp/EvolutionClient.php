<?php

namespace App\Services\WhatsApp;

use App\Models\SiteSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente da Evolution API v2 para uma instância já pareada.
 * As credenciais vêm direto do banco porque o cache de SystemOptions descarta campos ocultos.
 */
class EvolutionClient
{
    public function __construct(
        private ?string $url,
        private ?string $apiKey,
        private ?string $instance,
    ) {}

    public static function fromSettings(?SiteSetting $settings = null): self
    {
        $settings ??= SiteSetting::query()->first();

        return new self(
            $settings?->evolution_url,
            $settings?->evolution_api_key,
            $settings?->evolution_instance,
        );
    }

    public function configured(): bool
    {
        return filled($this->url) && filled($this->apiKey) && filled($this->instance);
    }

    /**
     * Estado da conexão da instância: "open" quando o número está pareado.
     */
    public function connectionState(): string
    {
        $response = $this->call(fn (PendingRequest $request) => $request->get($this->endpoint('/instance/connectionState')));

        return (string) ($response['instance']['state'] ?? $response['state'] ?? 'unknown');
    }

    public function sendText(string $number, string $text): void
    {
        $this->call(fn (PendingRequest $request) => $request->post($this->endpoint('/message/sendText'), [
            'number' => $number,
            'text' => $text,
        ]));
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) $this->url, '/').$path.'/'.rawurlencode((string) $this->instance);
    }

    /**
     * @param  callable(PendingRequest): Response  $send
     * @return array<string, mixed>
     */
    private function call(callable $send): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Evolution API não configurada.');
        }

        try {
            $response = $send(Http::acceptJson()->timeout(15)->withHeaders(['apikey' => (string) $this->apiKey]));
        } catch (ConnectionException) {
            throw new RuntimeException('Não foi possível conectar à Evolution API.');
        }

        if ($response->failed()) {
            $message = $response->json('response.message') ?? $response->json('message') ?? $response->body();

            throw new RuntimeException('Evolution API respondeu '.$response->status().': '.mb_substr(is_array($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : (string) $message, 0, 300));
        }

        return (array) $response->json();
    }
}
