<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_is_stored_and_mailed_to_site_email(): void
    {
        Mail::fake();
        $this->makeSettings();

        $this->postJson('/api/contato', $this->payload())
            ->assertOk()
            ->assertJsonPath('status', 'enviado');

        $this->assertDatabaseHas('contact_submissions', [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'phone' => '',
            'destination' => '',
            'message' => 'Quero um ensaio de gestante.',
        ]);

        Mail::assertSent(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo('contato@rubia.test')
            && $mail->senderEmail === 'ana@example.com');
    }

    public function test_submit_without_javascript_redirects_to_contact_anchor(): void
    {
        Mail::fake();
        $this->makeSettings();

        $this->post('/api/contato', $this->payload())
            ->assertRedirect('/contato?status=enviado#orcamento');
    }

    public function test_invalid_submit_returns_the_site_messages(): void
    {
        $this->makeSettings();

        $this->postJson('/api/contato', ['nome' => '', 'email' => 'invalido', 'mensagem' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'nome' => 'Me conta o seu nome.',
                'email' => 'Esse e-mail parece incompleto.',
                'mensagem' => 'Escreve um pouco sobre o momento que você quer registrar.',
            ]);

        $this->assertSame(0, ContactSubmission::query()->count());
    }

    public function test_honeypot_is_accepted_silently_without_storing(): void
    {
        Mail::fake();
        $this->makeSettings();

        $this->postJson('/api/contato', $this->payload(['empresa_site' => 'spam']))
            ->assertOk();

        $this->assertSame(0, ContactSubmission::query()->count());
        Mail::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nome' => 'ana silva',
            'email' => 'ana@example.com',
            'mensagem' => 'Quero um ensaio de gestante.',
        ], $overrides);
    }

    private function makeSettings(): SiteSetting
    {
        return SiteSetting::query()->create([
            'name' => 'Rubia Rocha Fotografia',
            'og_locale' => 'pt_BR',
            'twitter_card' => 'summary_large_image',
            'email' => 'contato@rubia.test',
        ]);
    }
}
