<?php

namespace App\Services;

use App\Models\CmsAction;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogger
{
    public function __construct(private GeoLocationService $geo) {}

    public function record(
        Request $request,
        ?User $user,
        string $action,
        string $description,
        ?int $status = null,
        array $extra = [],
    ): ?CmsAction {
        $location = $this->geo->locate($request);

        try {
            return CmsAction::query()->create([
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? $extra['user_name'] ?? null,
                'user_email' => $user?->email ?? $extra['user_email'] ?? null,
                'action' => $action,
                'description' => $description,
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'ip' => $request->ip(),
                'city' => $location['city'],
                'region' => $location['region'],
                'country' => $location['country'],
                'status' => $status,
                'properties' => $extra === [] ? null : $extra,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    public function fromRequest(Request $request, Response $response, ?User $user): void
    {
        if (! $this->shouldLog($request)) {
            return;
        }

        $this->record(
            $request,
            $user,
            $this->action($request),
            $this->description($request, $user),
            $response->getStatusCode(),
        );
    }

    private function shouldLog(Request $request): bool
    {
        $apiPath = $this->apiPath($request);

        if ($request->isMethod('GET') && ($apiPath === 'audit' || $apiPath === 'me')) {
            return false;
        }

        if ($request->isMethod('POST') && (
            $apiPath === 'impersonation/leave'
            || preg_match('#^users/[^/]+/impersonate$#', $apiPath) === 1
            || $apiPath === 'media-optimizations/optimize'
            || $apiPath === 'media-optimizations/revert'
        )) {
            return false;
        }

        return true;
    }

    private function action(Request $request): string
    {
        $path = $request->path();

        return match (true) {
            str_contains($path, '/logout') => 'logout',
            str_contains($path, '/impersonation/leave') => 'impersonate.leave',
            str_contains($path, '/impersonate') => 'impersonate',
            str_contains($path, '/download') => 'download',
            $request->isMethod('POST') && str_contains($path, '/media') => 'upload',
            str_contains($path, '/cache/flush') => 'cache.flush',
            str_contains($path, '/cache/warm') => 'cache.warm',
            str_contains($path, '/media-optimizations/optimize') => 'media.optimize',
            str_contains($path, '/media-optimizations/revert') => 'media.revert',
            str_contains($path, '/media-optimizations/settings') => 'media.optimize.settings',
            default => strtolower($request->method()),
        };
    }

    private function description(Request $request, ?User $user): string
    {
        $who = $user?->name ?? 'Alguém';
        $target = $this->target($request);

        return match ($request->method()) {
            'POST' => match (true) {
                str_contains($request->path(), '/logout') => $who.' saiu do painel',
                str_contains($request->path(), '/impersonation/leave') => $who.' encerrou a impersonação',
                str_contains($request->path(), '/impersonate') => $who.' acessou como '.$target,
                default => $who.' criou '.$target,
            },
            'PUT', 'PATCH' => $who.' atualizou '.$target,
            'DELETE' => $who.' excluiu '.$target,
            default => $who.' acessou '.$target,
        };
    }

    private function target(Request $request): string
    {
        $path = $this->apiPath($request);
        $segments = array_values(array_filter(explode('/', $path)));
        $resource = $segments[0] ?? '';
        $id = $segments[1] ?? null;

        return match (true) {
            $resource === 'pages' && $id !== null => 'a página '.$id,
            $resource === 'pages' => 'as páginas',
            $resource === 'messages' && $id !== null => 'a mensagem #'.$id,
            $resource === 'messages' => 'as mensagens',
            $resource === 'careers' && isset($segments[2]) && $segments[2] === 'download' => 'o currículo #'.$id,
            $resource === 'careers' && $id !== null => 'o currículo #'.$id,
            $resource === 'careers' => 'os currículos',
            $resource === 'users' && isset($segments[2]) && $segments[2] === 'impersonate' => 'o usuário #'.$id,
            $resource === 'impersonation' => 'a impersonação',
            $resource === 'users' && $id !== null => 'o usuário #'.$id,
            $resource === 'users' => 'os usuários',
            $resource === 'forms' && $id !== null => 'o formulário '.$id,
            $resource === 'forms' => 'os formulários',
            $resource === 'form-options' && $id !== null => 'a opção de formulário #'.$id,
            $resource === 'form-options' => 'as opções de formulário',
            $resource === 'menu-items' && $id !== null => 'o item de menu #'.$id,
            $resource === 'menu-items' => 'o menu',
            $resource === 'certifications' && $id !== null => 'a certificação #'.$id,
            $resource === 'certifications' => 'as certificações',
            $resource === 'site' => 'as configurações do site',
            $resource === 'seo' => 'o SEO',
            $resource === 'tags' => 'as tags',
            $resource === 'sitemap' => 'o sitemap',
            $resource === 'cache' => 'o cache',
            $resource === 'media-optimizations' => 'as otimizações de imagem',
            $resource === 'cases' && $id === 'mockup' => 'a capa de um case',
            $resource === 'media' => 'um arquivo',
            $resource === 'profile' => 'o próprio perfil',
            $resource === 'dashboard' => 'o dashboard',
            $resource === 'audit' => 'o log de ações',
            default => $path !== '' ? $path : 'o painel',
        };
    }

    private function apiPath(Request $request): string
    {
        $admin = trim((string) config('cms.admin_path'), '/');
        $path = '/'.ltrim($request->path(), '/');
        $prefix = '/'.$admin.'/api';

        if (str_starts_with($path, $prefix)) {
            return trim(substr($path, strlen($prefix)), '/');
        }

        return trim($request->path(), '/');
    }
}
