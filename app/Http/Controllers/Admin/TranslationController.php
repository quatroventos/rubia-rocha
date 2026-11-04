<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\TranslateContentJob;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\Translation\TextTranslator;
use App\Support\BatchProgress;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TranslationController extends Controller
{
    private const BATCH = 'translations';

    public function config(TextTranslator $translator): JsonResponse
    {
        return response()->json([
            'configured' => $translator->configured(),
            'default' => Locales::default(),
            'locales' => array_values(Locales::publicList()),
            'translated' => Locales::translated(),
        ]);
    }

    public function page(Request $request, Page $page, string $locale, TextTranslator $translator): JsonResponse
    {
        $source = $page->sourceRow();
        $this->authorize('update', $source);
        $this->guard($locale, $translator);

        TranslateContentJob::queue('page', $source->id, $locale, $request->user()?->id);

        return $this->status('page', $source->id, $locale);
    }

    public function menus(Request $request, string $locale, TextTranslator $translator): JsonResponse
    {
        $this->authorize('create', MenuItem::class);
        $this->guard($locale, $translator);

        TranslateContentJob::queue('menus', null, $locale, $request->user()?->id);

        return $this->status('menus', null, $locale);
    }

    /**
     * Enfileira a tradução de todas as páginas publicadas e dos menus, em todos os idiomas, publicando o resultado.
     * Sem "force", pula o que já está traduzido e em dia com o pt-BR.
     */
    public function all(Request $request, TextTranslator $translator): JsonResponse
    {
        $this->authorize('create', MenuItem::class);
        abort_unless($translator->configured(), 422, 'Defina a chave da OpenAI em SEO > Integrações para traduzir com IA.');

        $force = $request->boolean('force');

        if (! BatchProgress::running(self::BATCH)) {
            $userId = $request->user()?->id;
            $pages = Page::query()->originals()->published()->with('translations')->orderBy('slug')->get();
            $jobs = [];

            foreach (Locales::translated() as $locale) {
                $jobs[] = new TranslateContentJob('menus', null, $locale, $userId, true);

                foreach ($pages as $page) {
                    if ($force || $this->needsTranslation($page, $locale)) {
                        $jobs[] = new TranslateContentJob('page', $page->id, $locale, $userId, true);
                    }
                }
            }

            $connection = config('services.openai.queue_connection');
            BatchProgress::start(self::BATCH, 'Traduzir site', $jobs, is_string($connection) && $connection !== 'sync' ? $connection : null);
        }

        return response()->json(BatchProgress::status(self::BATCH));
    }

    public function allStatus(): JsonResponse
    {
        $this->authorize('viewAny', MenuItem::class);

        return response()->json(BatchProgress::status(self::BATCH));
    }

    public function cancelAll(): JsonResponse
    {
        $this->authorize('create', MenuItem::class);

        BatchProgress::cancel(self::BATCH);

        return response()->json(BatchProgress::status(self::BATCH));
    }

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['page', 'menus'])],
            'id' => ['nullable', 'integer'],
            'locale' => ['required', Rule::in(Locales::translated())],
        ]);

        $id = isset($data['id']) ? (int) $data['id'] : null;

        if ($data['kind'] === 'page' && $id !== null) {
            $this->authorize('view', Page::query()->findOrFail($id));
        } else {
            $this->authorize('viewAny', MenuItem::class);
        }

        return $this->status($data['kind'], $id, $data['locale']);
    }

    private function status(string $kind, ?int $id, string $locale): JsonResponse
    {
        $state = TranslateContentJob::status($kind, $id, $locale);
        $row = match ($kind) {
            'page' => $id ? Page::query()->where('translation_of', $id)->where('locale', $locale)->first() : null,
            default => null,
        };

        return response()->json([
            'state' => $state['state'] ?? 'idle',
            'message' => $state['message'] ?? null,
            'translation' => $row ? [
                'id' => $row->id,
                'slug' => $row->slug,
                'status' => $row->status,
                'path' => $row->publicPath(),
                'translated_at' => $row->translated_at?->toIso8601String(),
            ] : null,
        ]);
    }

    private function needsTranslation(Page $source, string $locale): bool
    {
        $row = $source->translations->firstWhere('locale', $locale);
        $row?->setRelation('original', $source);

        return $row === null || $row->status !== 'published' || $row->isTranslationStale();
    }

    private function guard(string $locale, TextTranslator $translator): void
    {
        abort_unless(in_array($locale, Locales::translated(), true), 404);
        abort_unless($translator->configured(), 422, 'Defina a chave da OpenAI em SEO > Integrações para traduzir com IA.');
    }
}
