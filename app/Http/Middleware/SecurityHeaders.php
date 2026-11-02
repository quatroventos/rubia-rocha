<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('X-Livewire');

        $admin = trim((string) config('cms.admin_path'), '/');
        if ($admin !== '' && $request->is($admin.'*')) {
            [$dev, $devWs] = $this->viteDevOrigins();

            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; img-src 'self' data: blob: https:{$dev}; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com{$dev}; font-src 'self' https://fonts.gstatic.com data:{$dev}; script-src 'self' 'unsafe-inline' 'unsafe-eval'{$dev}; connect-src 'self'{$dev}{$devWs}; frame-src 'self'; frame-ancestors 'self'"
            );
        }

        return $response;
    }

    /**
     * Origem do dev server do Vite (public/hot), liberada no CSP apenas em desenvolvimento.
     *
     * @return array{0: string, 1: string}
     */
    private function viteDevOrigins(): array
    {
        $hot = public_path('hot');

        if (app()->isProduction() || ! is_file($hot)) {
            return ['', ''];
        }

        $url = trim((string) file_get_contents($hot));
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return ['', ''];
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $ws = ($parts['scheme'] === 'https' ? 'wss' : 'ws').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return [' '.$origin, ' '.$ws];
    }
}
