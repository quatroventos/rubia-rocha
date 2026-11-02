<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RestoresSoftDeletedModels;
use App\Http\Controllers\Controller;
use App\Models\Certification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificationController extends Controller
{
    use RestoresSoftDeletedModels;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Certification::class);

        return response()->json([
            'data' => $this->scopedQuery($request, Certification::class)
                ->orderBy('sort')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Certification::class);

        $item = Certification::query()->create($this->rules($request));

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, Certification $certification): JsonResponse
    {
        $this->authorize('update', $certification);
        $certification->update($this->rules($request));

        return response()->json(['data' => $certification]);
    }

    public function destroy(Certification $certification): JsonResponse
    {
        $this->authorize('delete', $certification);
        $certification->delete();

        return response()->json([], 204);
    }

    public function restore(Request $request, int $certification): JsonResponse
    {
        return $this->restoreSoftDeleted($request, Certification::class, $certification);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:badge,placeholder'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'url' => ['nullable', 'string', 'max:500'],
            'alt' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
