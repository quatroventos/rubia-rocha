<?php

namespace App\Http\Controllers\Admin;

use App\Cms\SectionCatalog;
use App\Http\Controllers\Controller;
use App\Models\CaseStudy;
use App\Models\Page;
use App\Services\PublicContent;
use App\Services\SystemOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Preview ao vivo do editor: o editor envia o conteúdo ainda não salvo, o servidor monta o payload em memória e
 * guarda em cache por usuário; a aba de preview consulta esse rascunho. Nada aqui grava no banco.
 */
class LivePreviewController extends Controller
{
    private const TTL_SECONDS = 3600;

    public function showCase(Request $request, CaseStudy $caseStudy, PublicContent $content): View
    {
        $user = $request->user();

        if ($user === null || ! $user->isCmsMember()) {
            abort(404);
        }

        $this->authorize('update', $caseStudy);

        return view('site.app', [
            'site' => app(SystemOptions::class)->site(),
            'payload' => $content->caseStudy($caseStudy),
            'sitePayload' => $content->site($caseStudy->localeCode()),
            'cmsPreview' => true,
            'previewDraftUrl' => self::draftUrl('cases', $caseStudy->id),
        ]);
    }

    public static function draftUrl(string $kind, int $id): string
    {
        return '/'.trim((string) config('cms.admin_path'), '/').'/api/preview/'.$kind.'/'.$id.'/draft';
    }

    public function page(Request $request, Page $page, PublicContent $content): JsonResponse
    {
        $this->authorize('update', $page);

        return response()->json($this->remember($request, 'pages', $page->id, $this->buildPage($request, $page, $content)));
    }

    public function caseStudy(Request $request, CaseStudy $caseStudy, PublicContent $content): JsonResponse
    {
        $this->authorize('update', $caseStudy);

        return response()->json($this->remember($request, 'cases', $caseStudy->id, $this->buildCase($request, $caseStudy, $content)));
    }

    /**
     * Payload público da página com o conteúdo enviado pelo editor, sem gravar.
     *
     * @return array<string, mixed>
     */
    public function buildPage(Request $request, Page $page, PublicContent $content): array
    {
        $data = $request->validate([
            ...$this->sectionRules(),
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ]);

        $page->forceFill([
            'content' => ['sections' => $data['content']['sections']],
            'seo_title' => $data['seo_title'] ?? $page->seo_title,
            'seo_description' => $data['seo_description'] ?? $page->seo_description,
        ]);

        return $content->page($page);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCase(Request $request, CaseStudy $caseStudy, PublicContent $content): array
    {
        $data = $request->validate([
            ...$this->sectionRules(),
            'client' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:300'],
            'sector' => ['nullable', 'string', 'max:60'],
            'year' => ['nullable', 'integer'],
            'url' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:60'],
            'services' => ['nullable', 'array'],
            'services.*' => ['string', 'max:255'],
            'cover_path' => ['nullable', 'string', 'max:500'],
            'cover_alt' => ['nullable', 'string', 'max:255'],
            'cover_ratio' => ['nullable', 'string', 'max:10'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ]);

        $caseStudy->forceFill([
            ...collect($data)->except('content')->map(fn ($value) => $value ?? '')->all(),
            'year' => $data['year'] ?? null,
            'tags' => $data['tags'] ?? [],
            'services' => $data['services'] ?? [],
            'content' => ['sections' => $data['content']['sections']],
        ]);

        return $content->caseStudy($caseStudy);
    }

    public function pageDraft(Request $request, Page $page): JsonResponse|Response
    {
        $this->authorize('update', $page);

        return $this->draft($request, 'pages', $page->id);
    }

    public function caseDraft(Request $request, CaseStudy $caseStudy): JsonResponse|Response
    {
        $this->authorize('update', $caseStudy);

        return $this->draft($request, 'cases', $caseStudy->id);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function remember(Request $request, string $kind, int $id, array $payload): array
    {
        Cache::put($this->key($request, $kind, $id), ['version' => (string) hrtime(true), 'page' => $payload], self::TTL_SECONDS);

        return $payload;
    }

    private function draft(Request $request, string $kind, int $id): JsonResponse|Response
    {
        $draft = Cache::get($this->key($request, $kind, $id));

        if (! is_array($draft) || $draft['version'] === $request->query('since')) {
            return response()->noContent();
        }

        return response()->json($draft);
    }

    private function key(Request $request, string $kind, int $id): string
    {
        return 'cms-preview:'.$request->user()?->id.':'.$kind.':'.$id;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function sectionRules(): array
    {
        return [
            'content' => ['required', 'array'],
            'content.sections' => ['present', 'array', 'max:80'],
            'content.sections.*.id' => ['required', 'string', 'max:40'],
            'content.sections.*.type' => ['required', 'string', Rule::in(SectionCatalog::types())],
            'content.sections.*.data' => ['nullable', 'array'],
        ];
    }
}
