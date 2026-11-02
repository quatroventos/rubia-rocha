<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Acesso de emergência: remove o segredo e os códigos de recuperação do usuário,
 * que configura um novo autenticador no próximo login.
 */
class ResetTwoFactor extends Command
{
    protected $signature = 'cms:reset-2fa {email}';

    protected $description = 'Zera a autenticação em dois fatores de um usuário do CMS.';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error('Usuário não encontrado: '.$email);

            return self::FAILURE;
        }

        $user->forceFill([
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ])->save();

        $this->info('2FA zerado para '.$email.'. Um novo autenticador será configurado no próximo login.');

        return self::SUCCESS;
    }
}
