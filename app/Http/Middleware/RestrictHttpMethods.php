<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictHttpMethods
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('livewire') || $request->is('livewire/*')) {
            abort(404);
        }

        if ($this->isPublicContent($request)) {
            return $next($request);
        }

        $admin = trim((string) config('cms.admin_path'), '/');

        $decoys = ['api', 'api/*', 'login', 'up', 'filament', 'filament/*'];
        if ($admin !== 'admin') {
            $decoys[] = 'admin';
            $decoys[] = 'admin/*';
        }

        if ($request->is(...$decoys)) {
            abort(404);
        }

        if ($admin !== '' && $request->is($admin, $admin.'/*')) {
            return $next($request);
        }

        if (! in_array($request->method(), ['GET', 'HEAD', 'POST'], true)) {
            abort(404);
        }

        return $next($request);
    }

    private function isPublicContent(Request $request): bool
    {
        if ($request->is('api/site') && in_array($request->method(), ['GET', 'HEAD'], true)) {
            return true;
        }

        if ($request->is('api/pages/*', 'api/cases', 'api/cases/*') && in_array($request->method(), ['GET', 'HEAD'], true)) {
            return true;
        }

        return $request->is('api/contato', 'api/contato/lead') && $request->isMethod('POST');
    }
}
