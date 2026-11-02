<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\AuditLogger;
use App\Services\ImageOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaOptimizationController extends Controller
{
    public function show(ImageOptimizationService $optimizer): JsonResponse
    {
        $this->authorize('viewAny', SiteSetting::class);

        return response()->json(['data' => $optimizer->panel()]);
    }

    public function updateSettings(Request $request, ImageOptimizationService $optimizer): JsonResponse
    {
        $this->authorize('viewAny', SiteSetting::class);

        $data = $request->validate([
            'quality' => ['required', 'integer', 'min:60', 'max:100'],
            'max_width' => ['nullable', 'integer', 'min:0', 'max:5000'],
        ]);

        $optimizer->updateSettings($data);

        return response()->json(['data' => $optimizer->panel()]);
    }

    public function optimize(Request $request, ImageOptimizationService $optimizer, AuditLogger $audit): JsonResponse
    {
        $this->authorize('viewAny', SiteSetting::class);

        $data = $request->validate([
            'paths' => ['sometimes', 'array'],
            'paths.*' => ['string', 'max:500'],
            'quality' => ['sometimes', 'integer', 'min:60', 'max:100'],
            'max_width' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:5000'],
        ]);

        @set_time_limit(300);

        $result = $optimizer->optimize(
            $data['paths'] ?? [],
            isset($data['quality']) ? (int) $data['quality'] : null,
            array_key_exists('max_width', $data) ? (int) ($data['max_width'] ?? 0) : null,
        );

        $processed = (int) ($result['processed'] ?? 0);
        $failed = (int) ($result['failed'] ?? 0);
        $who = $request->user()?->name ?? 'Alguém';
        $failures = $result['failures'] ?? [];

        if ($failed > 0) {
            $sample = collect($failures)
                ->take(5)
                ->map(fn (array $item): string => ($item['path'] ?? '?').': '.($item['error'] ?? 'erro'))
                ->implode(' | ');

            $audit->record(
                $request,
                $request->user(),
                'media.optimize.error',
                $who.' otimizou imagens com falha ('.$processed.' ok, '.$failed.' erro). '.$sample,
                422,
                [
                    'processed' => $processed,
                    'failed' => $failed,
                    'failures' => $failures,
                ],
            );
        } else {
            $audit->record(
                $request,
                $request->user(),
                'media.optimize',
                $who.' otimizou '.$processed.' imagem(ns) para WebP',
                200,
                ['processed' => $processed],
            );
        }

        return response()->json(['data' => $result]);
    }

    public function revert(Request $request, ImageOptimizationService $optimizer, AuditLogger $audit): JsonResponse
    {
        $this->authorize('viewAny', SiteSetting::class);

        $data = $request->validate([
            'paths' => ['sometimes', 'array'],
            'paths.*' => ['string', 'max:500'],
        ]);

        $result = $optimizer->revert($data['paths'] ?? []);
        $reverted = (int) ($result['reverted'] ?? 0);
        $who = $request->user()?->name ?? 'Alguém';

        $audit->record(
            $request,
            $request->user(),
            'media.revert',
            $who.' reverteu '.$reverted.' otimização(ões) de imagem',
            200,
            ['reverted' => $reverted],
        );

        return response()->json(['data' => $result]);
    }
}
