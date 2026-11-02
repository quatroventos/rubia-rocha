<?php

namespace App\Services\Seo;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use stdClass;

/**
 * Cliente mínimo do MCP do OpenSEO (Streamable HTTP, JSON-RPC 2.0), hospedado ou self-hosted.
 * Abre a sessão uma vez por instância e chama ferramentas sempre no projeto configurado.
 * Self-hosted sem autenticação dispensa a chave; de dentro do Docker, OPENSEO_HOST_HEADER
 * passa pela proteção de host do servidor (que só aceita localhost).
 */
class OpenSeoClient
{
    public const PROTOCOL_VERSION = '2025-06-18';

    private ?string $session = null;

    private bool $ready = false;

    private int $requestId = 0;

    public function configured(): bool
    {
        return filled(config('services.openseo.url')) && filled(config('services.openseo.project'));
    }

    public function projectId(): string
    {
        return (string) config('services.openseo.project');
    }

    /**
     * Chama uma ferramenta do MCP. O projectId configurado entra em todas as chamadas, salvo `$scoped = false`
     * (ferramentas da conta, como list_projects).
     *
     * @param  array<string, mixed>  $arguments
     */
    public function call(string $tool, array $arguments = [], bool $scoped = true): mixed
    {
        if ($scoped ? ! $this->configured() : ! filled(config('services.openseo.url'))) {
            throw new RuntimeException('OpenSEO não configurado.');
        }

        $this->initialize();

        $arguments = $scoped ? ['projectId' => (string) config('services.openseo.project'), ...$arguments] : $arguments;

        $result = $this->request('tools/call', [
            'name' => $tool,
            'arguments' => $arguments === [] ? new stdClass : $arguments,
        ]);

        $text = collect((array) ($result['content'] ?? []))
            ->filter(fn ($part) => is_array($part) && ($part['type'] ?? null) === 'text')
            ->pluck('text')
            ->implode("\n");

        if (! empty($result['isError'])) {
            throw new RuntimeException('OpenSEO: '.mb_substr($text !== '' ? $text : 'erro na ferramenta '.$tool, 0, 300));
        }

        if (isset($result['structuredContent']) && is_array($result['structuredContent'])) {
            return $result['structuredContent'];
        }

        $decoded = json_decode($text, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $text;
    }

    /**
     * Argumentos de mercado (país e idioma) quando definidos no .env; sem eles vale o padrão do projeto no OpenSEO.
     *
     * @return array<string, int|string>
     */
    public function market(): array
    {
        return array_filter([
            'locationCode' => config('services.openseo.location_code'),
            'languageCode' => config('services.openseo.language_code'),
        ], fn ($value) => filled($value));
    }

    private function initialize(): void
    {
        if ($this->ready) {
            return;
        }

        $this->request('initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => new stdClass,
            'clientInfo' => ['name' => 'quatroventos-cms', 'version' => '1.0'],
        ]);

        $this->send(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
        $this->ready = true;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function request(string $method, array $params): array
    {
        $id = ++$this->requestId;
        $message = $this->send(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params], $id);

        if (isset($message['error'])) {
            throw new RuntimeException('OpenSEO: '.mb_substr((string) ($message['error']['message'] ?? 'erro desconhecido'), 0, 300));
        }

        return is_array($message['result'] ?? null) ? $message['result'] : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(array $payload, ?int $id = null): array
    {
        $headers = ['Accept' => 'application/json, text/event-stream'];

        if ($this->session !== null) {
            $headers['Mcp-Session-Id'] = $this->session;
        }

        if ($this->ready || ($payload['method'] ?? null) === 'notifications/initialized') {
            $headers['MCP-Protocol-Version'] = self::PROTOCOL_VERSION;
        }

        if (filled(config('services.openseo.host_header'))) {
            $headers['Host'] = (string) config('services.openseo.host_header');
        }

        $request = Http::withHeaders($headers)->timeout((int) config('services.openseo.timeout', 90));

        if (filled(config('services.openseo.key'))) {
            $request = $request->withToken((string) config('services.openseo.key'));
        }

        try {
            $response = $request->post((string) config('services.openseo.url'), $payload);
        } catch (ConnectionException) {
            throw new RuntimeException('Não foi possível conectar ao OpenSEO. Verifique se o servidor está no ar.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new RuntimeException(filled(config('services.openseo.key')) ? 'O OpenSEO recusou a chave de API.' : 'O OpenSEO recusou o acesso. Preencha a chave de API ou o Host header em SEO > Integrações.');
        }

        if ($response->failed()) {
            throw new RuntimeException('OpenSEO '.$response->status().': '.mb_substr($response->body(), 0, 300));
        }

        $this->session = $response->header('Mcp-Session-Id') ?: $this->session;

        return $id === null ? [] : $this->decode($response, $id);
    }

    /**
     * A resposta vem como JSON simples ou como stream SSE com um evento por mensagem.
     *
     * @return array<string, mixed>
     */
    private function decode(Response $response, int $id): array
    {
        if (! str_contains(strtolower($response->header('Content-Type')), 'text/event-stream')) {
            $message = $response->json();

            return is_array($message) ? $message : [];
        }

        foreach (preg_split('/\r?\n\r?\n/', $response->body()) ?: [] as $event) {
            $data = collect(preg_split('/\r?\n/', $event) ?: [])
                ->filter(fn (string $line) => str_starts_with($line, 'data:'))
                ->map(fn (string $line) => ltrim(substr($line, 5)))
                ->implode("\n");
            $message = $data !== '' ? json_decode($data, true) : null;

            if (is_array($message) && ($message['id'] ?? null) === $id) {
                return $message;
            }
        }

        throw new RuntimeException('Resposta do OpenSEO fora do formato esperado.');
    }
}
