<?php

namespace Tests\Feature;

use App\Models\ContactSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubiaSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_serves_the_static_site(): void
    {
        $index = public_path('estudio/index.html');
        $this->assertFileExists($index);

        $this->get('/estudio')
            ->assertOk()
            ->assertSee('<title>Rubia Rocha Fotografia</title>', false)
            ->assertSee('id="rubia-root"', false)
            ->assertSee('/estudio/assets/', false);
    }

    public function test_service_subpage_serves_the_static_site(): void
    {
        $this->get('/estudio/ensaios/gestante')->assertOk()->assertSee('id="rubia-root"', false);
        $this->get('/estudio/eventos/corporativo')->assertOk();
        $this->get('/estudio/ensaios/nao-existe')->assertNotFound();
    }

    public function test_unknown_page_is_missing(): void
    {
        $this->get('/estudio/nao-existe')->assertNotFound();
    }

    public function test_contact_form_is_stored(): void
    {
        $this->post('/estudio/contato', [
            'nome' => 'ana souza',
            'email' => 'ana@example.com',
            'mensagem' => 'Quero um ensaio em família.',
        ])->assertRedirect('/estudio/contato');

        $this->assertDatabaseHas(ContactSubmission::class, [
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'destination' => 'Rubia Rocha Fotografia',
            'message' => 'Quero um ensaio em família.',
        ]);
    }

    public function test_contact_form_rejects_an_empty_message(): void
    {
        $this->from('/estudio/contato')
            ->post('/estudio/contato', [
                'nome' => 'Ana',
                'email' => 'ana@example.com',
                'mensagem' => '',
            ])
            ->assertRedirect('/estudio/contato')
            ->assertSessionHasErrors('mensagem');

        $this->assertDatabaseCount(ContactSubmission::class, 0);
    }

    public function test_honeypot_does_not_store_the_message(): void
    {
        $this->post('/estudio/contato', [
            'nome' => 'Ana Souza',
            'email' => 'ana@example.com',
            'mensagem' => 'Quero um ensaio.',
            'empresa_site' => 'spam',
        ])->assertRedirect('/estudio/contato');

        $this->assertDatabaseCount(ContactSubmission::class, 0);
    }
}
