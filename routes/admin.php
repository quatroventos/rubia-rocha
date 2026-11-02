<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\CacheController;
use App\Http\Controllers\Admin\CareerSubmissionController;
use App\Http\Controllers\Admin\CaseDraftController;
use App\Http\Controllers\Admin\CaseMockupController;
use App\Http\Controllers\Admin\CaseStudyController;
use App\Http\Controllers\Admin\CertificationController;
use App\Http\Controllers\Admin\ContactSubmissionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FormController;
use App\Http\Controllers\Admin\FormOptionController;
use App\Http\Controllers\Admin\LivePreviewController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MediaOptimizationController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\OgImageController;
use App\Http\Controllers\Admin\OpenSeoController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PagePreviewController;
use App\Http\Controllers\Admin\SectionCopyController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\SeoInsightsController;
use App\Http\Controllers\Admin\SeoIntegrationController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\SpaController;
use App\Http\Controllers\Admin\TourController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

$admin = trim((string) config('cms.admin_path'), '/');

if ($admin === '') {
    return;
}

Route::prefix($admin)->group(function () {
    Route::prefix('api')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:8,1');
        Route::post('login/two-factor', [AuthController::class, 'twoFactor'])->middleware('throttle:8,1');

        Route::middleware('cms')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::patch('tour', [TourController::class, 'update']);
            Route::post('impersonation/leave', [AuthController::class, 'leaveImpersonation']);
            Route::get('dashboard', [DashboardController::class, 'show']);
            Route::post('dashboard/lighthouse', [DashboardController::class, 'refreshLighthouse']);
            Route::post('media', [MediaController::class, 'store']);

            Route::get('media-optimizations', [MediaOptimizationController::class, 'show']);
            Route::put('media-optimizations/settings', [MediaOptimizationController::class, 'updateSettings']);
            Route::post('media-optimizations/optimize', [MediaOptimizationController::class, 'optimize']);
            Route::post('media-optimizations/revert', [MediaOptimizationController::class, 'revert']);

            Route::get('pages', [PageController::class, 'index']);
            Route::post('pages', [PageController::class, 'store']);
            Route::get('pages/{page}', [PageController::class, 'show']);
            Route::put('pages/{page}', [PageController::class, 'update']);
            Route::delete('pages/{page}', [PageController::class, 'destroy']);
            Route::get('pages/{page}/revisions', [PageController::class, 'revisions']);
            Route::post('pages/{page}/revisions/{revision}/restore', [PageController::class, 'restoreRevision']);

            Route::get('translations/config', [TranslationController::class, 'config']);
            Route::get('translations/status', [TranslationController::class, 'show']);
            Route::get('translations/all', [TranslationController::class, 'allStatus']);
            Route::post('translations/all', [TranslationController::class, 'all'])->middleware('throttle:5,1');
            Route::delete('translations/all', [TranslationController::class, 'cancelAll']);
            Route::post('pages/{page}/translations/{locale}', [TranslationController::class, 'page'])->middleware('throttle:20,1');
            Route::post('cases/{caseStudy}/translations/{locale}', [TranslationController::class, 'caseStudy'])->whereNumber('caseStudy')->middleware('throttle:20,1');
            Route::post('menu-items/translations/{locale}', [TranslationController::class, 'menus'])->middleware('throttle:10,1');

            Route::post('preview/pages/{page}', [LivePreviewController::class, 'page'])->middleware('throttle:240,1');
            Route::post('preview/cases/{caseStudy}', [LivePreviewController::class, 'caseStudy'])->whereNumber('caseStudy')->middleware('throttle:240,1');
            Route::post('og-image/pages/{page}', [OgImageController::class, 'page'])->middleware('throttle:10,1');
            Route::post('og-image/cases/{caseStudy}', [OgImageController::class, 'caseStudy'])->whereNumber('caseStudy')->middleware('throttle:10,1');
            Route::get('og-image/all', [OgImageController::class, 'status']);
            Route::post('og-image/all', [OgImageController::class, 'generateAll'])->middleware('throttle:5,1');
            Route::delete('og-image/all', [OgImageController::class, 'cancel']);
            Route::post('seo/analyze', [SeoInsightsController::class, 'analyze'])->middleware('throttle:240,1');
            Route::post('seo/analyze/rendered', [SeoInsightsController::class, 'analyzeRendered'])->middleware('throttle:20,1');
            Route::post('seo/fix', [SeoInsightsController::class, 'fix'])->middleware('throttle:30,1');
            Route::get('seo/market', [SeoInsightsController::class, 'market'])->middleware('throttle:240,1');
            Route::post('seo/market', [SeoInsightsController::class, 'fetchMarket'])->middleware('throttle:10,1');
            Route::get('seo/lighthouse/{kind}/{id}', [SeoInsightsController::class, 'lighthouse'])->whereIn('kind', ['pages', 'cases'])->whereNumber('id');
            Route::post('seo/lighthouse/{kind}/{id}', [SeoInsightsController::class, 'measure'])->whereIn('kind', ['pages', 'cases'])->whereNumber('id')->middleware('throttle:10,1');
            Route::get('preview/pages/{page}/draft', [LivePreviewController::class, 'pageDraft'])->middleware('throttle:600,1');
            Route::get('preview/cases/{caseStudy}/draft', [LivePreviewController::class, 'caseDraft'])->whereNumber('caseStudy')->middleware('throttle:600,1');
            Route::post('sections/copy', [SectionCopyController::class, 'store'])->middleware('throttle:30,1');
            Route::post('sections/proofread', [SectionCopyController::class, 'proofread'])->middleware('throttle:60,1');
            Route::post('cases/mockup', [CaseMockupController::class, 'store'])->middleware('throttle:20,1');
            Route::post('cases/draft', [CaseDraftController::class, 'store'])->middleware('throttle:10,1');
            Route::get('cases/draft/{token}', [CaseDraftController::class, 'show'])->whereUuid('token');
            Route::apiResource('cases', CaseStudyController::class)->parameters(['cases' => 'caseStudy'])->whereNumber('caseStudy');
            Route::post('cases/{caseStudy}/restore', [CaseStudyController::class, 'restore'])->whereNumber('caseStudy');
            Route::get('menu-items', [MenuItemController::class, 'index']);
            Route::post('menu-items', [MenuItemController::class, 'store']);
            Route::put('menu-items/sync', [MenuItemController::class, 'sync']);
            Route::put('menu-items/reorder', [MenuItemController::class, 'reorder']);
            Route::put('menu-items/{menuItem}', [MenuItemController::class, 'update']);
            Route::delete('menu-items/{menuItem}', [MenuItemController::class, 'destroy']);
            Route::apiResource('certifications', CertificationController::class)->except(['show']);
            Route::post('certifications/{certification}/restore', [CertificationController::class, 'restore'])->whereNumber('certification');
            Route::get('forms', [FormController::class, 'index']);
            Route::get('forms/{form}', [FormController::class, 'show'])->where('form', 'contact');
            Route::put('forms/{form}', [FormController::class, 'update'])->where('form', 'contact');
            Route::apiResource('form-options', FormOptionController::class)->except(['show']);
            Route::post('form-options/{formOption}/restore', [FormOptionController::class, 'restore'])->whereNumber('formOption');

            Route::get('cache', [CacheController::class, 'show']);
            Route::put('cache', [CacheController::class, 'update']);
            Route::post('cache/flush', [CacheController::class, 'flush']);
            Route::post('cache/warm', [CacheController::class, 'warm']);

            Route::get('backup', [BackupController::class, 'index']);
            Route::put('backup', [BackupController::class, 'update']);
            Route::post('backup/run', [BackupController::class, 'run']);
            Route::post('backup/import', [BackupController::class, 'import'])->middleware('throttle:10,1');
            Route::get('backup/{backup}/download', [BackupController::class, 'download']);
            Route::post('backup/{backup}/restore', [BackupController::class, 'restore']);
            Route::post('backup/{backup}/undelete', [BackupController::class, 'undelete'])->whereNumber('backup');
            Route::delete('backup/{backup}', [BackupController::class, 'destroy']);

            Route::get('site', [SiteSettingController::class, 'show']);
            Route::put('site', [SiteSettingController::class, 'update']);
            Route::post('site/whatsapp/test', [SiteSettingController::class, 'testWhatsApp'])->middleware('throttle:6,1');
            Route::get('tags', [SiteSettingController::class, 'tags']);
            Route::put('tags', [SiteSettingController::class, 'updateTags']);
            Route::get('seo', [SeoController::class, 'show']);
            Route::put('seo', [SeoController::class, 'update']);
            Route::get('seo/openseo', [OpenSeoController::class, 'show']);
            Route::get('seo/openseo/search-console', [OpenSeoController::class, 'searchConsole']);
            Route::get('seo/openseo/opportunities', [OpenSeoController::class, 'opportunities']);
            Route::get('seo/openseo/audit', [OpenSeoController::class, 'audit']);
            Route::post('seo/openseo/audit', [OpenSeoController::class, 'runAudit'])->middleware('throttle:5,1');
            Route::get('seo/openseo/keywords', [OpenSeoController::class, 'keywords']);
            Route::post('seo/openseo/keywords', [OpenSeoController::class, 'saveKeywords'])->middleware('throttle:30,1');
            Route::delete('seo/openseo/keywords', [OpenSeoController::class, 'removeKeywords'])->middleware('throttle:30,1');
            Route::post('seo/openseo/keywords/research', [OpenSeoController::class, 'research'])->middleware('throttle:10,1');
            Route::post('seo/openseo/index', [OpenSeoController::class, 'inspect'])->middleware('throttle:20,1');
            Route::get('seo/openseo/domain', [OpenSeoController::class, 'domain']);
            Route::post('seo/openseo/domain', [OpenSeoController::class, 'fetchDomain'])->middleware('throttle:5,1');
            Route::get('seo/openseo/ai', [OpenSeoController::class, 'ai']);
            Route::get('seo/integrations', [SeoIntegrationController::class, 'show']);
            Route::put('seo/integrations', [SeoIntegrationController::class, 'update'])->middleware('throttle:20,1');
            Route::post('seo/integrations/test/{service}', [SeoIntegrationController::class, 'test'])->whereIn('service', ['openseo', 'openai'])->middleware('throttle:20,1');
            Route::get('sitemap', [SiteSettingController::class, 'sitemap']);
            Route::put('sitemap', [SiteSettingController::class, 'updateSitemap']);

            Route::get('messages', [ContactSubmissionController::class, 'index']);
            Route::get('messages/{contactSubmission}', [ContactSubmissionController::class, 'show']);
            Route::delete('messages/{contactSubmission}', [ContactSubmissionController::class, 'destroy']);
            Route::post('messages/{contactSubmission}/restore', [ContactSubmissionController::class, 'restore'])->whereNumber('contactSubmission');

            Route::get('careers', [CareerSubmissionController::class, 'index']);
            Route::get('careers/{careerSubmission}', [CareerSubmissionController::class, 'show']);
            Route::get('careers/{careerSubmission}/download', [CareerSubmissionController::class, 'download']);
            Route::delete('careers/{careerSubmission}', [CareerSubmissionController::class, 'destroy']);
            Route::post('careers/{careerSubmission}/restore', [CareerSubmissionController::class, 'restore'])->whereNumber('careerSubmission');

            Route::get('profile', [UserController::class, 'profile']);
            Route::put('profile', [UserController::class, 'updateProfile']);
            Route::post('profile/recovery-codes', [UserController::class, 'regenerateRecoveryCodes'])->middleware('throttle:6,1');

            Route::apiResource('users', UserController::class)->except(['show']);
            Route::post('users/{user}/restore', [UserController::class, 'restore'])->whereNumber('user');
            Route::post('users/{user}/impersonate', [AuthController::class, 'impersonate']);
            Route::post('users/{user}/two-factor/reset', [UserController::class, 'resetTwoFactor']);
            Route::get('audit', [AuditController::class, 'index']);
        });
    });

    Route::get('preview/cases/{caseStudy}', [LivePreviewController::class, 'showCase'])->whereNumber('caseStudy');
    Route::get('preview/{page}', PagePreviewController::class);
    Route::get('/{any?}', SpaController::class)->where('any', '^(?!api(?:/|$)|preview(?:/|$)).*');
});
