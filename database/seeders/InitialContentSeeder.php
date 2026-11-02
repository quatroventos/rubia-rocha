<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class InitialContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->rolesAndAdmin();
        $this->call([SiteContentSeeder::class, CaseStudySeeder::class]);
    }

    private function rolesAndAdmin(): void
    {
        Role::findOrCreate('root', 'web');
        Role::findOrCreate('user', 'web');

        $user = User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'gabriel@quatroventos.com.br')],
            [
                'name' => env('ADMIN_NAME', 'Administrador'),
                'password' => env('ADMIN_PASSWORD', 'RbaAdmin2026!'),
            ]
        );

        $user->syncRoles(['root']);

        $gabriel = User::query()->updateOrCreate(
            ['email' => 'gabriel@quatroventos.com.br'],
            [
                'name' => 'Gabriel',
                'password' => '12345678',
            ]
        );

        $gabriel->syncRoles(['root']);
    }
}
