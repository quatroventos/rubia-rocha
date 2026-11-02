<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCaseDraftJob;
use App\Models\CaseStudy;
use App\Services\Cases\CaseDraftWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseDraftController extends Controller
{
    public function store(Request $request, CaseDraftWriter $writer): JsonResponse
    {
        $this->authorize('create', CaseStudy::class);

        if (! $writer->configured()) {
            return response()->json(['message' => 'Defina a chave da OpenAI em SEO > Integrações para usar a IA.'], 422);
        }

        $data = $request->validate([
            'prompt' => ['nullable', 'required_without:url', 'string', 'min:20', 'max:8000'],
            'url' => ['nullable', 'required_without:prompt', 'url', 'max:500'],
            'prints' => ['sometimes', 'boolean'],
            'mockups' => ['sometimes', 'boolean'],
            'case' => ['nullable', 'array'],
            'case.client' => ['nullable', 'string', 'max:255'],
            'case.title' => ['nullable', 'string', 'max:255'],
            'case.summary' => ['nullable', 'string', 'max:300'],
            'case.sector' => ['nullable', 'string', 'max:60'],
            'case.year' => ['nullable', 'integer'],
            'case.services' => ['nullable', 'array'],
            'case.services.*' => ['string', 'max:255'],
        ], [
            'prompt.required_without' => 'Informe a URL do projeto ou descreva o projeto para a IA.',
            'url.required_without' => 'Informe a URL do projeto ou descreva o projeto para a IA.',
            'prompt.min' => 'Descreva o projeto com um pouco mais de detalhe.',
            'url.url' => 'Informe uma URL completa, com https://.',
        ]);

        $token = GenerateCaseDraftJob::start([
            'prompt' => (string) ($data['prompt'] ?? ''),
            'url' => $data['url'] ?? null,
            'prints' => (bool) ($data['prints'] ?? false),
            'mockups' => (bool) ($data['mockups'] ?? false),
            'case' => array_filter($data['case'] ?? [], fn ($value) => filled($value)),
            'options' => [
                'sectors' => array_column(CaseStudyController::sectorOptions(), 'label', 'value'),
                'services' => array_column(CaseStudyController::serviceOptions(), 'label', 'value'),
            ],
        ], $request->user()?->id);

        return response()->json(['token' => $token], 202);
    }

    public function show(Request $request, string $token): JsonResponse
    {
        $this->authorize('create', CaseStudy::class);

        $status = GenerateCaseDraftJob::status($token);
        if ($status === null || $status['user_id'] !== $request->user()?->id) {
            abort(404);
        }

        unset($status['user_id']);

        return response()->json($status);
    }
}
