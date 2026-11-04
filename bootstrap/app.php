<?php

use App\Http\Middleware\EnsureCmsUser;
use App\Http\Middleware\RestrictHttpMethods;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetSiteLocale;
use App\Services\LegacyRedirects;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        then: function () {
            Route::middleware('web')->group(base_path('routes/web.php'));
            Route::middleware('web')->group(base_path('routes/admin.php'));
            Route::middleware('public')->group(base_path('routes/public.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(SecurityHeaders::class);
        $middleware->append(RestrictHttpMethods::class);
        $middleware->alias(['cms' => EnsureCmsUser::class]);
        $middleware->validateCsrfTokens(except: [
            't',
        ]);
        $middleware->encryptCookies(except: [
            'rr_locale',
        ]);
        $middleware->web(append: [SetSiteLocale::class]);
        $middleware->group('public', [
            SetSiteLocale::class,
            SubstituteBindings::class,
        ]);
        $middleware->redirectGuestsTo(fn () => url('/'.cms_admin_path().'/login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson()
                || $request->is(cms_admin_path().'/api', cms_admin_path().'/api/*')
        );

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            $adminPath = cms_admin_path();

            if ($request->expectsJson() || $request->is($adminPath.'/api', $adminPath.'/api/*')) {
                return null;
            }

            if ($request->is($adminPath, $adminPath.'/*')) {
                return null;
            }

            return app(LegacyRedirects::class)->respond($request)
                ?? response()->view('errors.404', [], 404);
        });
    })->create();
