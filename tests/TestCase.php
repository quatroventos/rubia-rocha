<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        foreach ([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'DB_HOST' => '',
            'DB_PORT' => '',
            'DB_USERNAME' => '',
            'DB_PASSWORD' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $app = parent::createApplication();

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');

        if ($app->bound('db')) {
            $app->make('db')->purge();
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function seedRoles(): void
    {
        Role::findOrCreate('root', 'web');
        Role::findOrCreate('user', 'web');
    }

    protected function makeAdmin(array $overrides = []): User
    {
        $this->seedRoles();

        $user = User::factory()->create($overrides);
        $user->syncRoles(['root']);

        return $user;
    }

    protected function makeEditor(array $overrides = []): User
    {
        $this->seedRoles();

        $user = User::factory()->create($overrides);
        $user->syncRoles(['user']);

        return $user;
    }
}
