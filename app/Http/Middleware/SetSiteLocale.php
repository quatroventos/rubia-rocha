<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSiteLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $prefix = $route?->parameter('locale');

        if (is_string($prefix)) {
            $locale = Locales::fromPrefix($prefix);
            $route->forgetParameter('locale');
        } else {
            $query = $request->query('locale');
            $locale = Locales::normalize(is_string($query) ? $query : null);
        }

        Locales::setCurrent($locale);

        return $next($request);
    }
}
