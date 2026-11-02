<?php

namespace App\Http\Controllers;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Services\PublicContent;
use App\Services\SitemapService;
use App\Services\SystemOptions;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SiteController extends Controller
{
    public function home(): View
    {
        $page = Page::query()->published()->forLocale(Locales::current())->where('template', 'home')->firstOrFail();

        return $this->render($page);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $locale = Locales::current();
        $page = Page::query()->published()->forLocale($locale)->where('slug', $slug)->first();

        if ($page === null) {
            return $this->legacyRedirect($locale, $slug) ?? abort(404);
        }

        if ($page->isHome()) {
            abort(404);
        }

        return $this->render($page);
    }

    public function legacy(string $slug): RedirectResponse
    {
        return $this->legacyRedirect(Locales::current(), $slug) ?? abort(404);
    }

    private function legacyRedirect(string $locale, string $slug): ?RedirectResponse
    {
        if (Locales::isDefault($locale)) {
            return null;
        }

        $source = config('locale_redirects.'.$locale.'.'.$slug);
        if (! is_string($source)) {
            return null;
        }

        $original = Page::query()->originals()->published()
            ->where($source === 'home' ? 'template' : 'slug', $source)
            ->first();
        $translation = $original?->translationFor($locale);

        $target = $translation !== null && $translation->status === 'published'
            ? $translation->publicPath()
            : Locales::path($locale, '/');

        return redirect($target, 301);
    }

    public function caseStudy(string $slug, PublicContent $content): View
    {
        $case = CaseStudy::query()->published()->forLocale(Locales::current())->where('slug', $slug)->firstOrFail();

        $payload = $content->caseStudy($case);

        return view('site.app', [
            'site' => app(SystemOptions::class)->site(),
            'payload' => $payload,
            'sitePayload' => $content->site($case->localeCode()),
            'fragment' => $this->fragmentFor($payload),
            'cmsPreview' => false,
        ]);
    }

    public function sitemap(SitemapService $sitemap): Response
    {
        return response($sitemap->xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function revisao(): Response
    {
        abort_unless(app()->environment('local'), 404);

        return response()
            ->view('site.revisao')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function robots(): Response
    {
        $base = rtrim((string) config('app.url'), '/');

        $allow = "Allow: /\n";
        $body = "User-agent: *\n{$allow}\n"
            ."User-agent: GPTBot\n{$allow}\n"
            ."User-agent: OAI-SearchBot\n{$allow}\n"
            ."User-agent: Google-Extended\n{$allow}\n"
            ."Sitemap: {$base}/sitemap.xml\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function render(Page $page, bool $cmsPreview = false): View
    {
        $content = app(PublicContent::class);

        $payload = $content->page($page);

        return view('site.app', [
            'page' => $page,
            'site' => app(SystemOptions::class)->site(),
            'payload' => $payload,
            'sitePayload' => $content->site($page->localeCode()),
            'fragment' => $cmsPreview ? '' : $this->fragmentFor($payload),
            'cmsPreview' => $cmsPreview,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fragmentFor(array $payload): string
    {
        $locale = is_string($payload['locale'] ?? null) ? $payload['locale'] : 'pt-BR';
        $path = is_string($payload['path'] ?? null) ? $payload['path'] : '/';
        $relative = trim($path, '/');
        $name = $relative === '' ? 'index.html' : $relative.'.html';
        $file = storage_path('app/prerender/'.$locale.'/'.$name);

        if (is_file($file)) {
            return File::get($file);
        }

        if (app()->environment('production')) {
            Log::error('HTML público ausente.', ['file' => $file]);
            abort(500);
        }

        return '<main id="conteudo"><p class="wrap" role="status">O HTML desta URL ainda não foi gerado. Rode php artisan site:prerender.</p></main>';
    }
}
