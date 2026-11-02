<?php

namespace Tests\Feature;

use App\Mail\ContactMessageMail;
use App\Models\ContactSubmission;
use App\Models\FormOption;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_sends_to_destination_email(): void
    {
        Mail::fake();
        $this->makeSettings(['email' => 'site@quatroventos.com.br']);
        $this->makeContactPage();
        $this->makeDestination('Comercial', 'comercial@quatroventos.com.br');

        $this->post('/contato', $this->payload('Comercial'))
            ->assertRedirect('/contato?status=enviado#contact-form-title');

        $this->assertDatabaseHas('contact_submissions', [
            'name' => 'Ana Silva',
            'destination' => 'Comercial',
        ]);

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->hasTo('comercial@quatroventos.com.br')
                && $mail->destination === 'Comercial'
                && $mail->senderEmail === 'ana@example.com';
        });
    }

    public function test_contact_form_falls_back_to_site_email(): void
    {
        Mail::fake();
        $this->makeSettings(['email' => 'site@quatroventos.com.br']);
        $this->makeContactPage();
        $this->makeDestination('Jurídico', null);

        $this->post('/contato', $this->payload('Jurídico'))
            ->assertRedirect('/contato?status=enviado#contact-form-title');

        Mail::assertSent(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->hasTo('site@quatroventos.com.br');
        });
    }

    public function test_contact_form_shows_invalid_feedback(): void
    {
        $this->makeSettings();
        $this->makeContactPage();
        $this->makeDestination('Comercial', 'comercial@example.com');

        $this->from('/contato')
            ->post('/contato', [
                'nome' => '',
                'email' => 'invalido',
                'telefone' => '',
                'destino' => 'Comercial',
                'mensagem' => '',
            ])
            ->assertRedirect('/contato?status=invalido#contact-form-title')
            ->assertSessionHasErrors(['nome', 'email', 'telefone', 'mensagem', 'lgpd']);

        $this->assertSame(0, ContactSubmission::query()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(string $destino, array $overrides = []): array
    {
        return array_merge([
            'nome' => 'Ana Silva',
            'email' => 'ana@example.com',
            'telefone' => '16999999999',
            'destino' => $destino,
            'mensagem' => 'Gostaria de mais informações.',
            'lgpd' => '1',
        ], $overrides);
    }

    private function makeDestination(string $value, ?string $email): FormOption
    {
        return FormOption::query()->create([
            'form' => 'contact',
            'field' => 'destino',
            'label' => $value,
            'value' => $value,
            'email' => $email,
            'sort' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSettings(array $overrides = []): SiteSetting
    {
        return SiteSetting::query()->create(array_merge([
            'name' => 'Quatro Ventos',
            'og_locale' => 'pt_BR',
            'twitter_card' => 'summary_large_image',
            'email' => 'adv@quatroventos.com.br',
        ], $overrides));
    }

    private function makeContactPage(): Page
    {
        return Page::query()->create([
            'slug' => 'contato',
            'template' => 'contact',
            'status' => 'published',
            'seo_title' => 'Contato',
            'content' => [
                'form' => [
                    'title' => 'Fale conosco',
                ],
            ],
            'published_at' => now(),
        ]);
    }
}
