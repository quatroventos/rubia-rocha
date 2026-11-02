<?php

use App\Http\Controllers\PublicContentController;
use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\SiteAnalyticsController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SiteFormController;
use App\Http\Middleware\DetectLocale;
use App\Models\Page;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

Route::post('/t', SiteAnalyticsController::class)->middleware('throttle:60,1')->name('analytics.collect');

Route::get('/storage/{path}', PublicStorageController::class)
    ->where('path', '.*')
    ->name('storage.public');

Route::get('/api/site', [PublicContentController::class, 'site'])
    ->middleware('throttle:60,1')
    ->name('api.site');
Route::get('/api/pages/{slug}', [PublicContentController::class, 'page'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:60,1')
    ->name('api.pages.show');
Route::get('/api/cases', [PublicContentController::class, 'cases'])
    ->middleware('throttle:60,1')
    ->name('api.cases.index');
Route::get('/api/cases/{slug}', [PublicContentController::class, 'caseStudy'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:60,1')
    ->name('api.cases.show');
Route::post('/api/contato', [SiteFormController::class, 'contact'])
    ->middleware('throttle:8,1')
    ->name('api.contato.store');
Route::post('/api/contato/lead', [SiteFormController::class, 'lead'])
    ->middleware('throttle:8,1')
    ->name('api.contato.lead');

foreach ((array) config('redirects', []) as $from => $to) {
    Route::redirect($from, $to, 301);
}

Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SiteController::class, 'robots'])->name('robots');

if (app()->environment('local')) {
    Route::get('/revisao', [SiteController::class, 'revisao'])->name('revisao');
}

$localePattern = Locales::routePattern();

if ($localePattern !== '') {
    Route::prefix('{locale}')
        ->where(['locale' => $localePattern])
        ->group(function () {
            Route::get('/', [SiteController::class, 'home'])->name('locale.home');
            Route::get('/cases/{slug}', [SiteController::class, 'caseStudy'])
                ->where('slug', '[a-z0-9-]+')
                ->name('locale.case');
            Route::get('/{slug}.php', [SiteController::class, 'legacy'])
                ->where('slug', '[a-z0-9-]+')
                ->name('locale.legacy');
            Route::get('/{slug}', [SiteController::class, 'show'])
                ->where('slug', '[a-z0-9-]+')
                ->name('locale.page');
        });
}

Route::middleware(DetectLocale::class)->group(function () {
    Route::get('/', [SiteController::class, 'home'])->name('home');
    Route::get('/cases/{slug}', [SiteController::class, 'caseStudy'])
        ->where('slug', '[a-z0-9-]+')
        ->name('case');

    $reserved = implode('|', array_map(
        fn (string $slug) => preg_quote($slug, '/'),
        array_values(array_unique(array_merge(Page::reservedSlugs(), Page::formSlugs())))
    ));

    Route::get('/{slug}', [SiteController::class, 'show'])
        ->where('slug', $reserved === '' ? '[a-z0-9-]+' : '(?!('.$reserved.')$)[a-z0-9-]+')
        ->name('page');
});
