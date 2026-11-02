<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\Seo\OpenSeoDashboard;
use App\Services\Seo\SeoIntegrations;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Aba "Desempenho" da tela de SEO: Search Console, auditoria, palavras-chave, indexação,
 * domínio e visibilidade em IA, lidos do OpenSEO.
 */
class OpenSeoController extends Controller
{
    public function __construct(private OpenSeoDashboard $dashboard) {}

    public function show(Request $request, SeoIntegrations $integrations): JsonResponse
    {
        $this->authorize('view', SiteSetting::current());

        $configured = $this->dashboard->configured();
        $project = null;
        $message = $configured ? null : 'OpenSEO não configurado. Preencha a URL do MCP e o ID do projeto em SEO > Integrações.';

        if ($configured) {
            try {
                $project = $this->dashboard->project($request->boolean('fresh'));
            } catch (RuntimeException $exception) {
                $message = $exception->getMessage();
            }
        }

        return response()->json([
            'configured' => $configured,
            'project' => $project,
            'message' => $message,
            'site_url' => $this->dashboard->siteUrl(),
            'paid' => $integrations->paidEnabled(),
        ]);
    }

    public function searchConsole(Request $request): JsonResponse
    {
        return $this->read(fn () => $this->dashboard->searchConsole((string) $request->query('range', 'last_28_days'), $request->boolean('fresh')));
    }

    public function opportunities(Request $request): JsonResponse
    {
        return $this->read(fn () => $this->dashboard->opportunities($request->boolean('fresh')));
    }

    public function audit(Request $request): JsonResponse
    {
        return $this->read(fn () => $this->dashboard->audit($request->boolean('fresh')));
    }

    public function runAudit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'max_pages' => ['sometimes', 'integer', 'min:10', 'max:500'],
            'lighthouse' => ['sometimes', 'boolean'],
        ]);

        return $this->write(function () use ($data) {
            $this->dashboard->runAudit((int) ($data['max_pages'] ?? 100), (bool) ($data['lighthouse'] ?? false));

            return $this->dashboard->audit(true);
        });
    }

    public function keywords(Request $request): JsonResponse
    {
        return $this->read(fn () => $this->dashboard->keywords($request->boolean('fresh')));
    }

    public function research(Request $request): JsonResponse
    {
        $data = $request->validate(['seed' => ['required', 'string', 'max:120']]);

        return $this->write(fn () => ['rows' => $this->dashboard->research(trim($data['seed']))]);
    }

    public function saveKeywords(Request $request): JsonResponse
    {
        $data = $request->validate([
            'keywords' => ['required', 'array', 'min:1', 'max:100'],
            'keywords.*.keyword' => ['required', 'string', 'max:200'],
            'keywords.*.volume' => ['nullable', 'integer', 'min:0'],
            'keywords.*.difficulty' => ['nullable', 'integer', 'min:0', 'max:100'],
            'keywords.*.cpc' => ['nullable', 'numeric', 'min:0'],
            'keywords.*.intent_code' => ['nullable', Rule::in(['informational', 'commercial', 'transactional', 'navigational'])],
        ]);

        return $this->write(function () use ($data) {
            $this->dashboard->saveKeywords($data['keywords']);

            return $this->dashboard->keywords(true);
        });
    }

    public function removeKeywords(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:250'], 'ids.*' => ['string', 'max:64']]);

        return $this->write(function () use ($data) {
            $this->dashboard->removeKeywords($data['ids']);

            return $this->dashboard->keywords(true);
        });
    }

    public function inspect(Request $request): JsonResponse
    {
        $data = $request->validate(['urls' => ['required', 'array', 'min:1', 'max:10'], 'urls.*' => ['url', 'max:2048']]);

        return $this->read(fn () => $this->dashboard->inspect(array_values($data['urls'])));
    }

    public function domain(): JsonResponse
    {
        return $this->read(fn () => $this->dashboard->cachedDomain() ?? ['state' => 'idle', 'domain' => $this->dashboard->domain()]);
    }

    public function fetchDomain(): JsonResponse
    {
        return $this->write(fn () => $this->dashboard->fetchDomain());
    }

    public function ai(Request $request): JsonResponse
    {
        return $this->read(fn () => $this->dashboard->ai((int) $request->query('days', 7), $request->boolean('fresh')));
    }

    /**
     * Leitura: falha de conexão vira estado "indisponível" para o bloco mostrar a mensagem.
     */
    private function read(Closure $callback): JsonResponse
    {
        $this->authorize('view', SiteSetting::current());

        return $this->guard($callback);
    }

    /**
     * Ação que altera dados ou gasta créditos: exige permissão de edição e falha com 422.
     */
    private function write(Closure $callback): JsonResponse
    {
        $this->authorize('update', SiteSetting::current());

        if (! $this->dashboard->configured()) {
            return response()->json(['message' => 'OpenSEO não configurado.'], 422);
        }

        try {
            return response()->json($callback());
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    private function guard(Closure $callback): JsonResponse
    {
        if (! $this->dashboard->configured()) {
            return response()->json(['state' => 'unconfigured', 'message' => 'OpenSEO não configurado.']);
        }

        try {
            return response()->json($callback());
        } catch (RuntimeException $exception) {
            return response()->json(['state' => 'unavailable', 'message' => $exception->getMessage(), 'action_url' => null]);
        }
    }
}
