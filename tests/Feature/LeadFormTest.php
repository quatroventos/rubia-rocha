<?php

namespace Tests\Feature;

use App\Jobs\SendContactMailJob;
use App\Jobs\SendWhatsAppMessageJob;
use App\Mail\ContactMessageMail;
use App\Models\ContactSubmission;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeadFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_is_stored_and_mailed_to_site_email(): void
    {
        Mail::fake();
        SiteSetting::query()->create(['name' => 'Quatro Ventos', 'email' => 'contato@quatroventos.com.br']);

        $this->postJson('/api/contato/lead', [
            'nome' => 'Ana',
            'whatsapp' => '(41) 99999-0000',
            'pagina' => '/agentes-de-ia',
        ])->assertOk()->assertJsonPath('status', 'enviado');

        $row = ContactSubmission::query()->sole();
        $this->assertSame('(41) 99999-0000', $row->phone);
        $this->assertSame('Lead rápido', $row->destination);
        $this->assertStringContainsString('/agentes-de-ia', $row->message);

        Mail::assertSent(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo('contato@quatroventos.com.br'));
    }

    public function test_mail_and_whatsapp_are_queued_instead_of_blocking_the_request(): void
    {
        $this->settingsWithWhatsApp();
        Queue::fake();

        $this->postJson('/api/contato/lead', ['nome' => 'Ana', 'whatsapp' => '(41) 99999-0000'])
            ->assertOk()
            ->assertJsonPath('status', 'enviado');

        Queue::assertPushed(SendContactMailJob::class, fn (SendContactMailJob $job) => $job->recipient === 'contato@quatroventos.com.br');
        Queue::assertPushed(SendWhatsAppMessageJob::class, fn (SendWhatsAppMessageJob $job) => $job->number === '5541988887777');
        $this->assertSame(1, ContactSubmission::query()->count());
    }

    public function test_delivery_failures_never_fail_the_form(): void
    {
        $this->settingsWithWhatsApp();
        Http::fake(['evo.example.com/*' => Http::response(['message' => 'instance offline'], 500)]);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP fora do ar'));

        $this->postJson('/api/contato/lead', ['nome' => 'Ana', 'whatsapp' => '(41) 99999-0000'])
            ->assertOk()
            ->assertJsonPath('status', 'enviado');

        $this->assertSame(1, ContactSubmission::query()->count());
    }

    public function test_lowercase_or_uppercase_name_is_capitalized(): void
    {
        Queue::fake();

        $this->postJson('/api/contato/lead', ['nome' => '  maria  da silva ', 'whatsapp' => '(41) 99999-0000'])->assertOk();
        $this->postJson('/api/contato/lead', ['nome' => 'JOÃO DOS SANTOS', 'whatsapp' => '(41) 99999-0000'])->assertOk();
        $this->postJson('/api/contato/lead', ['nome' => 'Ana McDonald', 'whatsapp' => '(41) 99999-0000'])->assertOk();

        $this->assertSame(['Maria da Silva', 'João dos Santos', 'Ana McDonald'], ContactSubmission::query()->orderBy('id')->pluck('name')->all());
    }

    public function test_lead_requires_name_and_whatsapp(): void
    {
        $this->postJson('/api/contato/lead', ['nome' => '', 'whatsapp' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nome', 'whatsapp']);

        $this->assertSame(0, ContactSubmission::query()->count());
    }

    private function settingsWithWhatsApp(): void
    {
        SiteSetting::query()->create([
            'name' => 'Quatro Ventos',
            'email' => 'contato@quatroventos.com.br',
            'whatsapp_enabled' => true,
            'evolution_url' => 'https://evo.example.com',
            'evolution_api_key' => 'evo-key',
            'evolution_instance' => 'site',
            'whatsapp_notify_number' => '(41) 98888-7777',
        ]);
    }

    public function test_honeypot_is_ignored(): void
    {
        $this->postJson('/api/contato/lead', ['empresa_site' => 'bot', 'nome' => 'x'])
            ->assertOk()
            ->assertJsonPath('status', 'enviado');

        $this->assertSame(0, ContactSubmission::query()->count());
    }
}
