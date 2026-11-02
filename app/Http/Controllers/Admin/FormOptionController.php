<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RestoresSoftDeletedModels;
use App\Http\Controllers\Controller;
use App\Models\FormOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormOptionController extends Controller
{
    use RestoresSoftDeletedModels;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FormOption::class);

        return response()->json([
            'data' => $this->scopedQuery($request, FormOption::class)
                ->orderBy('form')
                ->orderBy('sort')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', FormOption::class);

        $item = FormOption::query()->create($this->rules($request));

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, FormOption $formOption): JsonResponse
    {
        $this->authorize('update', $formOption);
        $formOption->update($this->rules($request));

        return response()->json(['data' => $formOption]);
    }

    public function destroy(FormOption $formOption): JsonResponse
    {
        $this->authorize('delete', $formOption);
        $formOption->delete();

        return response()->json([], 204);
    }

    public function restore(Request $request, int $formOption): JsonResponse
    {
        return $this->restoreSoftDeleted($request, FormOption::class, $formOption);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request): array
    {
        return $request->validate([
            'form' => ['required', 'in:contact'],
            'field' => ['required', 'string', 'max:120'],
            'label' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:180'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
