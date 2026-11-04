<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\CrawlerFiles;
use App\Services\PublicContent;
use App\Services\SitemapService;
use App\Services\SystemOptions;
use App\Support\Locales;
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

    public function show(string $slug): View
    {
        $page = Page::query()->published()->forLocale(Locales::current())->where('slug', $slug)->first();

        if ($page === null || $page->isHome()) {
            abort(404);
        }

        return $this->render($page);
    }

    public function sitemap(SitemapService $sitemap): Response
    {
        return response($sitemap->xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(CrawlerFiles $files): Response
    {
        return response($files->read(CrawlerFiles::ROBOTS), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function llms(CrawlerFiles $files): Response
    {
        return response($files->read(CrawlerFiles::LLMS), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function render(Page $page): View
    {
        $payload = app(PublicContent::class)->page($page);

        return view('site.app', [
            'page' => $page,
            'site' => app(SystemOptions::class)->site(),
            'payload' => $payload,
            'fragment' => $this->fragmentFor($payload),
            'cmsPreview' => false,
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

        return '<main id="conteudo"><p class="intro" role="status">O HTML desta URL ainda não foi gerado. Rode php artisan site:prerender.</p></main>';
    }
}
