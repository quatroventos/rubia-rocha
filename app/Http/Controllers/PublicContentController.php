<?php

namespace App\Http\Controllers;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Services\PublicContent;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicContentController extends Controller
{
    public function site(PublicContent $content): JsonResponse
    {
        return response()->json($content->site(Locales::current()));
    }

    public function page(string $slug, PublicContent $content): JsonResponse
    {
        $query = Page::query()->published()->forLocale(Locales::current());
        $page = ($slug === 'home' ? $query->where('template', 'home') : $query->where('slug', $slug))->firstOrFail();

        return response()->json($content->page($page));
    }

    public function cases(Request $request, PublicContent $content): JsonResponse
    {
        $service = $request->query('servico');

        return response()->json([
            'data' => $content->cases(is_string($service) && $service !== '' ? $service : null, Locales::current()),
        ]);
    }

    public function caseStudy(string $slug, PublicContent $content): JsonResponse
    {
        $case = CaseStudy::query()->published()->forLocale(Locales::current())->where('slug', $slug)->firstOrFail();

        return response()->json($content->caseStudy($case));
    }
}
