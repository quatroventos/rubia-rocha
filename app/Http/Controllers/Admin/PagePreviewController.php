<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\PublicContent;
use App\Services\SystemOptions;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PagePreviewController extends Controller
{
    public function __invoke(Request $request, Page $page): View
    {
        $user = $request->user();

        if ($user === null || ! $user->isCmsMember()) {
            abort(404);
        }

        $this->authorize('view', $page);

        $content = app(PublicContent::class);

        return view('site.app', [
            'page' => $page,
            'site' => app(SystemOptions::class)->site(),
            'payload' => $content->page($page),
            'sitePayload' => $content->site(),
            'cmsPreview' => true,
            'previewDraftUrl' => LivePreviewController::draftUrl('pages', $page->id),
        ]);
    }
}
