<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\AuthController;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCmsUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isCmsMember()) {
            return response()->json(['message' => 'Não autenticado.'], 401);
        }

        if (! AuthController::twoFactorBypassed() && ! $user->hasCompletedTwoFactor() && ! $request->session()->has(AuthController::IMPERSONATOR_KEY)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['message' => 'Autenticação em dois fatores obrigatória. Entre novamente.'], 401);
        }

        $response = $next($request);

        app(AuditLogger::class)->fromRequest($request, $response, $user);

        return $response;
    }
}
