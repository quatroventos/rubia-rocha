<?php

namespace App\Http\Middleware;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Support\Locales;
use App\Support\UserAgent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class DetectLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() || ! Locales::isDefault(Locales::current()) || UserAgent::isBot($request->userAgent())) {
            return $this->vary($next($request));
        }

        $stored = $request->cookies->get(Locales::cookie());
        $stored = is_string($stored) && Locales::isSupported($stored) ? $stored : null;
        $wanted = $stored ?? Locales::fromAcceptLanguage($request->header('Accept-Language'));

        if ($wanted !== null && ! Locales::isDefault($wanted)) {
            $target = $this->alternatePath($request, $wanted);

            if ($target !== null) {
                $query = $request->getQueryString();
                $response = redirect($target.($query ? '?'.$query : ''), 302);

                if ($stored === null) {
                    $response->headers->setCookie($this->cookie($wanted));
                }

                return $this->vary($response);
            }
        }

        $response = $next($request);

        if ($stored === null && Locales::isDefault($wanted)) {
            $response->headers->setCookie($this->cookie(Locales::default()));
        }

        return $this->vary($response);
    }

    private function alternatePath(Request $request, string $locale): ?string
    {
        $route = $request->route();
        $name = $route?->getName();
        $slug = $route?->parameter('slug');

        $source = match (true) {
            $name === 'home' => Page::query()->originals()->published()->where('template', 'home')->first(),
            $name === 'case' && is_string($slug) => CaseStudy::query()->originals()->published()->where('slug', $slug)->first(),
            is_string($slug) => Page::query()->originals()->published()->where('slug', $slug)->first(),
            default => null,
        };

        $translation = $source?->translationFor($locale);

        return $translation !== null && $translation->status === 'published'
            ? $translation->publicPath()
            : null;
    }

    private function cookie(string $locale): Cookie
    {
        return Cookie::create(Locales::cookie(), $locale, now()->addYear(), '/', null, null, false, false, Cookie::SAMESITE_LAX);
    }

    private function vary(Response $response): Response
    {
        $response->setVary(['Accept-Language', 'Cookie'], false);

        return $response;
    }
}
