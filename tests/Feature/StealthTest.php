<?php

namespace Tests\Feature;

use Tests\TestCase;

class StealthTest extends TestCase
{
    public function test_public_api_is_not_found(): void
    {
        $this->get('/api')->assertNotFound();
        $this->post('/api')->assertNotFound();
        $this->get('/admin')->assertNotFound();
        $this->get('/login')->assertNotFound();
        $this->get('/filament')->assertNotFound();
    }

    public function test_cms_shell_is_served_on_secret_path(): void
    {
        $this->get('/gestao/login')->assertOk();
    }
}
