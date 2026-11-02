<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * Instalação nova (banco sem usuários): cria o admin root a partir de ADMIN_EMAIL e ADMIN_PASSWORD,
 * para dar acesso ao CMS e importar um backup. Com qualquer usuário no banco, não faz nada.
 */
class EnsureAdmin extends Command
{
    protected $signature = 'cms:ensure-admin';

    protected $description = 'Cria o admin root inicial a partir do .env quando o banco não tem usuários.';

    public function handle(): int
    {
        if (User::withTrashed()->exists()) {
            $this->line('Já existem usuários; nada a fazer.');

            return self::SUCCESS;
        }

        $email = trim((string) config('cms.bootstrap_admin.email'));
        $password = (string) config('cms.bootstrap_admin.password');

        if ($email === '' || $password === '') {
            $this->warn('Banco sem usuários. Defina ADMIN_EMAIL e ADMIN_PASSWORD para criar o admin inicial.');

            return self::SUCCESS;
        }

        if (mb_strlen($password) < 8) {
            $this->error('ADMIN_PASSWORD precisa de pelo menos 8 caracteres.');

            return self::FAILURE;
        }

        Role::findOrCreate('root', 'web');
        Role::findOrCreate('user', 'web');

        $user = User::query()->create([
            'name' => (string) config('cms.bootstrap_admin.name', 'Administrador'),
            'email' => $email,
            'password' => $password,
        ]);
        $user->syncRoles(['root']);

        $this->info('Admin inicial criado: '.$email);

        return self::SUCCESS;
    }
}
