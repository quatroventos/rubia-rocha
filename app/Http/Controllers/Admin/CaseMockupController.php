<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CaseStudy;
use App\Services\ImageOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CaseMockupController extends Controller
{
    public function store(Request $request, ImageOptimizationService $optimizer): JsonResponse
    {
        $this->authorize('create', CaseStudy::class);

        $data = $request->validate([
            'image' => ['required', 'file', 'mimes:png,jpeg,jpg,webp', 'max:20480'],
            'alt' => ['required', 'string', 'max:255'],
        ], [
            'image.required' => 'Envie a imagem do mockup.',
            'image.file' => 'Envie a imagem do mockup.',
            'image.mimes' => 'A capa precisa ser PNG, JPEG ou WebP.',
            'image.max' => 'A imagem passa de 20 MB.',
            'alt.required' => 'Informe o alt da capa.',
            'alt.max' => 'O alt passa de 255 caracteres.',
        ]);

        $stored = $request->file('image')->store('media/cases', 'public');
        $result = $optimizer->optimize([$stored]);
        $public = $optimizer->publicUrlFor($stored);

        if ($result['failed'] > 0 || ! is_string($public) || $public === '') {
            Storage::disk('public')->delete($stored);
            $failure = $result['failures'][0] ?? null;

            throw ValidationException::withMessages([
                'image' => is_array($failure) ? (string) ($failure['error'] ?? 'Não consegui gerar o WebP da capa.') : 'Não consegui gerar o WebP da capa.',
            ]);
        }

        $path = parse_url($public, PHP_URL_PATH);

        return response()->json([
            'path' => is_string($path) && $path !== '' ? $path : $public,
            'alt' => $data['alt'],
        ]);
    }
}
