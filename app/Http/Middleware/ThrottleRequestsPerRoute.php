<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * O throttle padrão conta por usuário (ou IP) somando todas as rotas: a prévia ao vivo
 * e a análise em tempo real esgotavam o limite das rotas mais restritas. Aqui cada rota
 * tem o próprio contador.
 */
class ThrottleRequestsPerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request): string
    {
        $route = $request->route();
        $scope = $route ? implode('|', $route->methods()).':'.$route->uri() : '';

        return sha1($scope.'|'.parent::resolveRequestSignature($request));
    }
}
