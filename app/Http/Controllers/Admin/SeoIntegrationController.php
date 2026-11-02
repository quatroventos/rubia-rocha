<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoIntegration;
use App\Models\SiteSetting;
use App\Services\Content\SectionCopyWriter;
use App\Services\Seo\OpenSeoDashboard;
use App\Services\Seo\SeoIntegrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Aba "Integrações" da tela de SEO: OpenSEO, OpenAI e prompts da IA de SEO.
 * Segredos são só de escrita: a tela recebe apenas se estão definidos (no CMS ou no .env).
 */
class SeoIntegrationController extends Controller
{
    private const PROMPTS = ['prompt_writer', 'prompt_seo_title', 'prompt_seo_description', 'prompt_keyword'];

    public function __construct(private SeoIntegrations $integrations) {}

    public function show(): JsonResponse
    {
        $this->authorize('view', SiteSetting::current());

        return response()->json(['data' => $this->payload(SeoIntegration::current())]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorize('update', SiteSetting::current());

        $data = $request->validate([
            'openseo_url' => ['nullable', 'url:http,https', 'max:255'],
            'openseo_host_header' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9.\-_]+(:\d+)?$/i'],
            'openseo_project_id' => ['nullable', 'string', 'max:64'],
            'openseo_site_url' => ['nullable', 'url:http,https', 'max:255'],
            'openseo_location_code' => ['nullable', 'integer', 'min:1', 'max:99999999'],
            'openseo_language_code' => ['nullable', 'string', 'max:10'],
            'openseo_paid_enabled' => ['sometimes', 'boolean'],
            'openai_model' => ['nullable', 'string', 'max:100'],
            'prompt_writer' => ['nullable', 'string', 'max:8000'],
            'prompt_seo_title' => ['nullable', 'string', 'max:2000'],
            'prompt_seo_description' => ['nullable', 'string', 'max:2000'],
            'prompt_keyword' => ['nullable', 'string', 'max:2000'],
            'secrets' => ['sometimes', 'array'],
            'secrets.*' => ['nullable', 'string', 'max:500'],
            'clear' => ['sometimes', 'array'],
            'clear.*' => [Rule::in(SeoIntegration::SECRETS)],
        ]);

        $settings = SeoIntegration::current();
        $settings->fill(collect($data)->except(['secrets', 'clear'])->map(fn ($value) => is_string($value) ? (trim($value) !== '' ? trim($value) : null) : $value)->all());

        if (trim((string) ($data['prompt_writer'] ?? '')) === trim(SectionCopyWriter::defaultPrompt())) {
            $settings->prompt_writer = null;
        }

        foreach (SeoIntegration::SECRETS as $secret) {
            if (in_array($secret, $data['clear'] ?? [], true)) {
                $settings->{$secret} = null;
            } elseif (filled($data['secrets'][$secret] ?? null)) {
                $settings->{$secret} = trim((string) $data['secrets'][$secret]);
            }
        }

        $settings->save();
        $this->integrations->apply($settings);

        try {
            Artisan::call('queue:restart');
        } catch (\Throwable) {
            //
        }

        return response()->json(['data' => $this->payload($settings)]);
    }

    /**
     * Testa a conexão com o que está salvo.
     */
    public function test(string $service, OpenSeoDashboard $openSeo): JsonResponse
    {
        $this->authorize('update', SiteSetting::current());

        try {
            return response()->json(match ($service) {
                'openseo' => $this->testOpenSeo($openSeo),
                'openai' => $this->testOpenAi(),
            });
        } catch (RuntimeException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function testOpenSeo(OpenSeoDashboard $openSeo): array
    {
        $projects = $openSeo->projects();
        $project = collect($projects)->firstWhere('id', (string) config('services.openseo.project'));

        return [
            'ok' => $project !== null,
            'message' => $project !== null
                ? 'Conectado ao projeto '.($project['name'] ?? $project['id']).'.'
                : ($projects === [] ? 'Conectado, mas a conta não tem projetos.' : 'Conectado. Escolha um dos projetos da conta.'),
            'projects' => $projects,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function testOpenAi(): array
    {
        if (! filled(config('services.openai.key'))) {
            throw new RuntimeException('Defina a chave da OpenAI.');
        }

        try {
            $response = Http::withToken((string) config('services.openai.key'))
                ->timeout(20)
                ->get(rtrim((string) config('services.openai.base_url'), '/').'/models/'.rawurlencode((string) config('services.openai.model')));
        } catch (ConnectionException) {
            throw new RuntimeException('Não foi possível conectar à OpenAI.');
        }

        return match (true) {
            $response->successful() => ['ok' => true, 'message' => 'Chave ok, modelo '.config('services.openai.model').' disponível.'],
            $response->status() === 401 => ['ok' => false, 'message' => 'A OpenAI recusou a chave.'],
            $response->status() === 404 => ['ok' => false, 'message' => 'Chave ok, mas o modelo '.config('services.openai.model').' não existe ou não está liberado na conta.'],
            default => ['ok' => false, 'message' => 'OpenAI '.$response->status().': '.mb_substr((string) data_get($response->json(), 'error.message', $response->body()), 0, 200)],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SeoIntegration $settings): array
    {
        $values = [];
        $fallbacks = [];
        $secrets = [];

        foreach (array_keys(SeoIntegrations::MAP) as $column) {
            if (in_array($column, SeoIntegration::SECRETS, true)) {
                $secrets[$column] = ['set' => filled($settings->getAttribute($column)), 'env' => filled($this->integrations->fallback($column))];

                continue;
            }

            $values[$column] = $settings->getAttribute($column);

            if (! in_array($column, self::PROMPTS, true)) {
                $fallbacks[$column] = $this->integrations->fallback($column);
            }
        }

        return [
            'values' => $values,
            'fallbacks' => $fallbacks,
            'secrets' => $secrets,
            'prompt_defaults' => ['prompt_writer' => SectionCopyWriter::defaultPrompt()],
            'paid' => $this->integrations->paidEnabled(),
        ];
    }
}
