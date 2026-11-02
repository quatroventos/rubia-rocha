<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public const IMPERSONATOR_KEY = 'cms_impersonator_id';

    public const TWO_FACTOR_PENDING_KEY = 'cms_two_factor_pending';

    public const TWO_FACTOR_SETUP_KEY = 'cms_two_factor_setup_secret';

    private const TWO_FACTOR_MAX_ATTEMPTS = 5;

    /**
     * Ambiente local dispensa o 2FA no login e no middleware do painel.
     */
    public static function twoFactorBypassed(): bool
    {
        return app()->environment('local');
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Auth::getProvider()->validateCredentials($user, $credentials)) {
            app(AuditLogger::class)->record(
                $request,
                null,
                'login.failed',
                'Tentativa de login inválida',
                422,
                ['user_email' => $credentials['email']],
            );

            throw ValidationException::withMessages([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        if (! $user->isCmsMember()) {
            app(AuditLogger::class)->record(
                $request,
                $user,
                'login.denied',
                $user->name.' tentou entrar sem permissão',
                422,
            );

            throw ValidationException::withMessages([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        $request->session()->forget([self::IMPERSONATOR_KEY, self::TWO_FACTOR_SETUP_KEY]);

        if (self::twoFactorBypassed()) {
            Auth::login($user);
            $request->session()->regenerate();

            app(AuditLogger::class)->record($request, $user, 'login', $user->name.' entrou no painel (ambiente local, sem 2FA)', 200);

            return response()->json([
                'status' => 'authenticated',
                'user' => $this->payload($request, $user),
                'recovery_codes' => null,
            ]);
        }

        $request->session()->put(self::TWO_FACTOR_PENDING_KEY, [
            'user_id' => $user->id,
            'expires_at' => now()->addMinutes(10)->getTimestamp(),
        ]);

        if ($user->hasCompletedTwoFactor()) {
            return response()->json(['status' => 'two_factor_challenge']);
        }

        $twoFactor = app(TwoFactorService::class);
        $secret = $twoFactor->makeSecret();
        $request->session()->put(self::TWO_FACTOR_SETUP_KEY, $secret);

        return response()->json([
            'status' => 'two_factor_setup',
            'secret' => $secret,
            'otpauth' => $twoFactor->otpauthUri($user, $secret),
        ]);
    }

    public function twoFactor(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'required_without:recovery_code', 'string', 'max:16'],
            'recovery_code' => ['nullable', 'string', 'max:32'],
        ]);

        $pending = $request->session()->get(self::TWO_FACTOR_PENDING_KEY);
        $user = is_array($pending) && ($pending['expires_at'] ?? 0) >= now()->getTimestamp()
            ? User::query()->find($pending['user_id'] ?? null)
            : null;

        if ($user === null || ! $user->isCmsMember()) {
            $request->session()->forget([self::TWO_FACTOR_PENDING_KEY, self::TWO_FACTOR_SETUP_KEY]);

            throw ValidationException::withMessages([
                'code' => 'Sessão de login expirada. Entre novamente.',
            ]);
        }

        $setupSecret = $request->session()->get(self::TWO_FACTOR_SETUP_KEY);
        $recoveryCodes = null;

        if (is_string($setupSecret) && ! $user->hasCompletedTwoFactor()) {
            $valid = filled($data['code'] ?? null) && $twoFactor->verify($setupSecret, $data['code']);

            if ($valid) {
                $recoveryCodes = $twoFactor->generateRecoveryCodes();
                $user->forceFill([
                    'app_authentication_secret' => $setupSecret,
                    'app_authentication_recovery_codes' => $twoFactor->hashRecoveryCodes($recoveryCodes),
                ])->save();
            }
        } elseif (filled($data['code'] ?? null)) {
            $valid = $twoFactor->verify((string) $user->app_authentication_secret, $data['code']);
        } else {
            $valid = $twoFactor->consumeRecoveryCode($user, (string) $data['recovery_code']);
        }

        if (! $valid) {
            app(AuditLogger::class)->record(
                $request,
                $user,
                'login.two_factor_failed',
                $user->name.' errou o código de dois fatores',
                422,
            );

            $attempts = ($pending['attempts'] ?? 0) + 1;

            if ($attempts >= self::TWO_FACTOR_MAX_ATTEMPTS) {
                $request->session()->forget([self::TWO_FACTOR_PENDING_KEY, self::TWO_FACTOR_SETUP_KEY]);

                throw ValidationException::withMessages([
                    'code' => 'Muitas tentativas. Entre novamente.',
                ]);
            }

            $request->session()->put(self::TWO_FACTOR_PENDING_KEY, [...$pending, 'attempts' => $attempts]);

            throw ValidationException::withMessages([
                'code' => 'Código inválido.',
            ]);
        }

        $request->session()->forget([self::TWO_FACTOR_PENDING_KEY, self::TWO_FACTOR_SETUP_KEY]);
        Auth::login($user);
        $request->session()->regenerate();

        app(AuditLogger::class)->record(
            $request,
            $user,
            $recoveryCodes !== null ? 'two_factor.enabled' : 'login',
            $recoveryCodes !== null
                ? $user->name.' ativou a autenticação em dois fatores e entrou no painel'
                : $user->name.' entrou no painel',
            200,
            filled($data['recovery_code'] ?? null) && $recoveryCodes === null ? ['recovery_code' => true] : [],
        );

        return response()->json([
            'status' => 'authenticated',
            'user' => $this->payload($request, $user),
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['status' => 'logged_out']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['user' => $this->payload($request, $user)]);
    }

    public function impersonate(Request $request, User $user): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $this->authorize('impersonate', $user);

        if ($request->session()->has(self::IMPERSONATOR_KEY)) {
            throw ValidationException::withMessages([
                'user' => 'Já está acessando como outro usuário.',
            ]);
        }

        if ($actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'Não é possível acessar como você mesmo.',
            ]);
        }

        if (! $user->isCmsMember()) {
            throw ValidationException::withMessages([
                'user' => 'Este usuário não tem acesso ao painel.',
            ]);
        }

        $request->session()->put(self::IMPERSONATOR_KEY, $actor->id);
        Auth::login($user);
        $request->session()->regenerate();

        app(AuditLogger::class)->record(
            $request,
            $actor,
            'impersonate',
            $actor->name.' acessou como '.$user->name,
            200,
            [
                'target_id' => $user->id,
                'target_email' => $user->email,
            ],
        );

        return response()->json([
            'status' => 'impersonating',
            'user' => $this->payload($request, $user),
        ]);
    }

    public function leaveImpersonation(Request $request): JsonResponse
    {
        $impersonatorId = $request->session()->pull(self::IMPERSONATOR_KEY);

        if ($impersonatorId === null) {
            throw ValidationException::withMessages([
                'user' => 'Nenhuma impersonação ativa.',
            ]);
        }

        /** @var User|null $current */
        $current = $request->user();
        $root = User::query()->find($impersonatorId);

        if ($root === null || ! $root->isRoot()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'user' => 'Sessão de impersonação inválida.',
            ]);
        }

        Auth::login($root);
        $request->session()->regenerate();

        app(AuditLogger::class)->record(
            $request,
            $root,
            'impersonate.leave',
            $root->name.' voltou de '.$current?->name,
            200,
            [
                'target_id' => $current?->id,
                'target_email' => $current?->email,
            ],
        );

        return response()->json([
            'status' => 'restored',
            'user' => $this->payload($request, $root),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, User $user): array
    {
        return self::userPayload($request, $user);
    }

    /**
     * @return array<string, mixed>
     */
    public static function userPayload(Request $request, User $user): array
    {
        $impersonatorId = $request->session()->get(self::IMPERSONATOR_KEY);
        $impersonator = $impersonatorId
            ? User::query()->find($impersonatorId)
            : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->cmsRole(),
            'abilities' => $user->cmsAbilities(),
            'tour_progress' => $user->tour_progress ?? [],
            'recovery_codes_left' => count($user->app_authentication_recovery_codes ?? []),
            'impersonating' => $impersonator !== null,
            'impersonator' => $impersonator ? [
                'id' => $impersonator->id,
                'name' => $impersonator->name,
                'email' => $impersonator->email,
            ] : null,
        ];
    }
}
