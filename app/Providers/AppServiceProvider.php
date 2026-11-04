<?php

namespace App\Providers;

use App\Http\Middleware\ThrottleRequestsPerRoute;
use App\Models\Certification;
use App\Models\FormOption;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\SiteSetting;
use App\Observers\CrawlerFilesObserver;
use App\Observers\OgImageObserver;
use App\Observers\SitePrerenderObserver;
use App\Observers\SystemOptionsObserver;
use App\Policies\AdminOnlyPolicy;
use App\Policies\FormOptionPolicy;
use App\Services\MailSettings;
use App\Services\Seo\SeoIntegrations;
use App\Services\SystemOptions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SeoIntegrations::class);
        $this->app->bind(ThrottleRequests::class, ThrottleRequestsPerRoute::class);
    }

    public function boot(): void
    {
        Gate::policy(MenuItem::class, AdminOnlyPolicy::class);
        Gate::policy(Certification::class, AdminOnlyPolicy::class);
        Gate::policy(FormOption::class, FormOptionPolicy::class);
        Gate::policy(SiteSetting::class, AdminOnlyPolicy::class);
        Gate::policy(Redirect::class, AdminOnlyPolicy::class);

        SiteSetting::observe(SystemOptionsObserver::class);
        MenuItem::observe(SystemOptionsObserver::class);
        Certification::observe(SystemOptionsObserver::class);
        FormOption::observe(SystemOptionsObserver::class);

        foreach ([Page::class, SiteSetting::class, MenuItem::class, Certification::class, FormOption::class] as $model) {
            $model::observe(SitePrerenderObserver::class);
        }

        foreach ([Page::class, SiteSetting::class, MenuItem::class] as $model) {
            $model::observe(CrawlerFilesObserver::class);
        }

        Page::observe(OgImageObserver::class);

        $appUrl = (string) config('app.url');
        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        try {
            app(MailSettings::class)->apply();
        } catch (\Throwable) {
            //
        }

        try {
            app(SeoIntegrations::class)->apply();
        } catch (\Throwable) {
            //
        }

        View::composer(['layouts.error', 'errors.*'], function ($view) {
            $empty = [
                'site' => null,
                'headerMenu' => collect(),
                'footerMenu1' => collect(),
                'footerMenu2' => collect(),
                'certs' => collect(),
            ];

            try {
                if (! Schema::hasTable('site_settings')) {
                    $view->with($empty);

                    return;
                }

                $options = app(SystemOptions::class);

                $view->with([
                    'site' => $options->site(),
                    'headerMenu' => $options->menu('header'),
                    'footerMenu1' => $options->menu('footer_1'),
                    'footerMenu2' => $options->menu('footer_2'),
                    'certs' => $options->certifications(),
                ]);
            } catch (\Throwable) {
                $view->with($empty);
            }
        });
    }
}
