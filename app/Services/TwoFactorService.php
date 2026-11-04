<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(private Google2FA $google2fa) {}

    public function makeSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function otpauthUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl('Rubia Rocha Fotografia', $user->email, $secret);
    }

    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? $code;

        return $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::lower(Str::random(10)))
            ->all();
    }

    /**
     * @param  list<string>  $plain
     * @return list<string>
     */
    public function hashRecoveryCodes(array $plain): array
    {
        return array_map(fn (string $code) => Hash::make($code), $plain);
    }

    /**
     * @param  list<string>|null  $stored
     */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $code = Str::lower(trim($code));
        $stored = $user->app_authentication_recovery_codes ?? [];

        foreach ($stored as $index => $hashed) {
            $hashed = (string) $hashed;
            $matches = str_starts_with($hashed, '$2')
                ? Hash::check($code, $hashed)
                : hash_equals(Str::lower($hashed), $code);

            if (! $matches) {
                continue;
            }

            unset($stored[$index]);
            $user->app_authentication_recovery_codes = array_values($stored);
            $user->save();

            return true;
        }

        return false;
    }
}
