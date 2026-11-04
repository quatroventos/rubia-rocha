<?php

use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\SiteAnalyticsController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SiteFormController;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

Route::post('/t', SiteAnalyticsController::class)->middleware('throttle:60,1')->name('analytics.collect');

Route::get('/storage/{path}', PublicStorageController::class)
    ->where('path', '.*')
    ->name('storage.public');

Route::post('/api/contato', [SiteFormController::class, 'contact'])
    ->middleware('throttle:8,1')
    ->name('api.contato.store');

Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SiteController::class, 'robots'])->name('robots');
Route::get('/llms.txt', [SiteController::class, 'llms'])->name('llms');

Route::get('/', [SiteController::class, 'home'])->name('home');

$reserved = implode('|', array_map(fn (string $slug) => preg_quote($slug, '/'), Page::reservedSlugs()));

Route::get('/{slug}', [SiteController::class, 'show'])
    ->where('slug', '(?!('.$reserved.')(/|$))'.Page::SLUG_PATTERN)
    ->name('page');
