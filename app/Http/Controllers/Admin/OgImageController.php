<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateOgImageJob;
use App\Models\CaseStudy;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\PublicContent;
use App\Services\Seo\OgImageCapture;
use App\Support\BatchProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OgImageController extends Controller
{
    private const BATCH = 'og-images';

    public function page(Request $request, Page $page, PublicContent $content, LivePreviewController $preview, OgImageCapture $capture): JsonResponse
    {
        $this->authorize('update', $page);

        $payload = $preview->buildPage($request, $page, $content);

        return $this->capture(fn () => $capture->capture($payload, $content->site($page->localeCode()), ($page->slug ?: 'home').'-'.$page->localeCode()));
    }

    public function caseStudy(Request $request, CaseStudy $caseStudy, PublicContent $content, LivePreviewController $preview, OgImageCapture $capture): JsonResponse
    {
        $this->authorize('update', $caseStudy);

        $payload = $preview->buildCase($request, $caseStudy, $content);

        return $this->capture(fn () => $capture->capture($payload, $content->site($caseStudy->localeCode()), 'case-'.$caseStudy->slug.'-'.$caseStudy->localeCode()));
    }

    /**
     * Enfileira o card de todas as páginas e cases publicados, em todos os idiomas, sobrescrevendo a OG atual.
     */
    public function generateAll(): JsonResponse
    {
        $this->authorize('create', MenuItem::class);

        if (! BatchProgress::running(self::BATCH)) {
            $jobs = [
                ...Page::query()->published()->orderBy('locale')->orderBy('slug')->pluck('id')->map(fn ($id) => new GenerateOgImageJob('page', (int) $id))->all(),
                ...CaseStudy::query()->published()->orderBy('locale')->orderBy('slug')->pluck('id')->map(fn ($id) => new GenerateOgImageJob('case', (int) $id))->all(),
            ];

            BatchProgress::start(self::BATCH, 'Gerar OG images', $jobs);
        }

        return response()->json(BatchProgress::status(self::BATCH));
    }

    public function status(): JsonResponse
    {
        $this->authorize('viewAny', Page::class);

        return response()->json(BatchProgress::status(self::BATCH));
    }

    public function cancel(): JsonResponse
    {
        $this->authorize('create', MenuItem::class);

        BatchProgress::cancel(self::BATCH);

        return response()->json(BatchProgress::status(self::BATCH));
    }

    /**
     * @param  callable(): string  $run
     */
    private function capture(callable $run): JsonResponse
    {
        try {
            $path = $run();
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'path' => $path,
            'width' => OgImageCapture::WIDTH,
            'height' => OgImageCapture::HEIGHT,
        ]);
    }
}
