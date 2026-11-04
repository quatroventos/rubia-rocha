<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RestoresSoftDeletedModels;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Redirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RedirectController extends Controller
{
    use RestoresSoftDeletedModels;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Redirect::class);

        return response()->json([
            'data' => $this->scopedQuery($request, Redirect::class)->orderBy('from_path')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Redirect::class);

        $redirect = Redirect::query()->create($this->validated($request));

        return response()->json(['data' => $redirect], 201);
    }

    public function update(Request $request, Redirect $redirect): JsonResponse
    {
        $this->authorize('update', $redirect);
        $redirect->update($this->validated($request, $redirect));

        return response()->json(['data' => $redirect]);
    }

    public function destroy(Redirect $redirect): JsonResponse
    {
        $this->authorize('delete', $redirect);
        $redirect->delete();

        return response()->json([], 204);
    }

    public function restore(Request $request, int $redirect): JsonResponse
    {
        $model = Redirect::onlyTrashed()->findOrFail($redirect);
        $this->ensureUniqueOrigin($model->from_path, $model->id);

        return $this->restoreSoftDeleted($request, Redirect::class, $redirect);
    }

    /**
     * @return array{from_path: string, to_url: string, status_code: int, is_active: bool}
     */
    private function validated(Request $request, ?Redirect $current = null): array
    {
        $request->merge([
            'from_path' => is_string($request->input('from_path')) ? Redirect::normalizePath($request->input('from_path')) : $request->input('from_path'),
            'to_url' => is_string($request->input('to_url')) ? trim($request->input('to_url')) : $request->input('to_url'),
        ]);

        $data = $request->validate([
            'from_path' => ['required', 'string', 'max:500', 'not_in:/'],
            'to_url' => ['required', 'string', 'max:2048', 'regex:#^(/|https?://)#i'],
            'status_code' => ['nullable', 'integer', Rule::in(Redirect::STATUS_CODES)],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'from_path.required' => 'Informe o endereço antigo.',
            'from_path.not_in' => 'A página inicial não pode ser redirecionada.',
            'to_url.required' => 'Informe o destino.',
            'to_url.regex' => 'O destino deve começar com / ou com http(s)://.',
        ]);

        $from = $data['from_path'];
        $this->ensureUniqueOrigin($from, $current?->id);

        if ($this->isPublishedPage($from)) {
            throw ValidationException::withMessages(['from_path' => 'Esse endereço é de uma página publicada; o redirect nunca seria usado.']);
        }

        if (str_starts_with($data['to_url'], '/') && Redirect::normalizePath($data['to_url']) === $from) {
            throw ValidationException::withMessages(['to_url' => 'O destino não pode ser o próprio endereço antigo.']);
        }

        return [
            'from_path' => $from,
            'to_url' => $data['to_url'],
            'status_code' => (int) ($data['status_code'] ?? 301),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function ensureUniqueOrigin(string $from, ?int $ignoreId): void
    {
        $taken = Redirect::query()
            ->where('from_path', $from)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['from_path' => 'Já existe um redirect para esse endereço.']);
        }
    }

    private function isPublishedPage(string $path): bool
    {
        $slug = trim($path, '/');

        return Page::query()->originals()->published()->where('slug', $slug === '' ? 'home' : $slug)->exists();
    }
}
