<?php

use App\Http\Controllers\MockupStudioController;
use App\Http\Controllers\OgCaptureController;
use App\Http\Controllers\RubiaSiteController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SiteFormController;
use App\Http\Middleware\DetectLocale;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

Route::get('/__og/{token}', OgCaptureController::class)->where('token', '[A-Za-z0-9]{40}')->name('og-capture');

Route::get('/ferramentas/mockups', [MockupStudioController::class, 'show'])->name('mockup-studio.show');
Route::post('/ferramentas/mockups/gerar', [MockupStudioController::class, 'generate'])
    ->middleware('throttle:8,1')
    ->name('mockup-studio.generate');
Route::post('/ferramentas/mockups/avancar', [MockupStudioController::class, 'advance'])
    ->middleware('throttle:30,1')
    ->name('mockup-studio.advance');
Route::post('/ferramentas/mockups/capturar', [MockupStudioController::class, 'capture'])
    ->middleware('throttle:6,1')
    ->name('mockup-studio.capture');

Route::get('/estudio/{path?}', [RubiaSiteController::class, 'show'])
    ->where('path', '.*')
    ->name('rubia.show');
Route::post('/estudio/contato', [RubiaSiteController::class, 'store'])
    ->middleware('throttle:8,1')
    ->name('rubia.contact');

foreach (Page::formSlugs() as $index => $slug) {
    Route::get('/'.$slug, [SiteController::class, 'show'])
        ->middleware(DetectLocale::class)
        ->defaults('slug', $slug)
        ->name($index === 0 ? 'site.form' : 'site.form.'.$slug);
}

Route::post('/contato', [SiteFormController::class, 'contact'])
    ->middleware('throttle:8,1')
    ->name('contato.store');
