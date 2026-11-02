<?php

namespace Tests\Feature;

use App\Jobs\SendContactMailJob;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\FormOption;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSettings(array $overrides = []): SiteSetting
    {
        return SiteSetting::query()->create(array_merge([
            'name' => 'Quatro Ventos',
            'email' => 'contato@quatroventos.com.br',
            'whatsapp_enabled' => true,
            'evolution_url' => 'https://evo.example.com/',
            'evolution_api_key' => 'evo-key',
            'evolution_instance' => 'site',
            'whatsapp_notify_number' => '(41) 98888-7777',
        ], $overrides));
    }

    private function postContact(array $overrides = []): void
    {
        FormOption::query()->create([
            'form' => 'contact',
            'field' => 'destino',
            'label' => 'Comercial',
            'value' => 'Comercial',
            'sort' => 1,
        ]);

        $this->postJson('/contato', array_merge([
            'nome' => 'Ana Silva',
            'email' => 'ana@example.com',
            'telefone' => '(16) 99999-1234',
            'destino' => 'Comercial',
            'mensagem' => 'Quero um site.',
            'lgpd' => '1',
        ], $overrides))->assertOk()->assertJsonPath('status', 'enviado');
    }

    public function test_contact_notifies_office_and_visitor_by_whatsapp(): void
    {
        Mail::fake();
        Queue::fake();
        $this->makeSettings(['whatsapp_visitor_template' => 'Oi {nome}, aqui é a {site}.']);

        $this->postContact();

        Queue::assertPushed(SendWhatsAppMessageJob::class, 2);
        Queue::assertPushed(SendWhatsAppMessageJob::class, fn (SendWhatsAppMessageJob $job) => $job->number === '5541988887777'
            && str_contains($job->text, 'Nome: Ana Silva')
            && str_contains($job->text, 'Destino: Comercial')
            && str_contains($job->text, 'Quero um site.'));
        Queue::assertPushed(SendWhatsAppMessageJob::class, fn (SendWhatsAppMessageJob $job) => $job->number === '5516999991234'
            && $job->text === 'Oi Ana Silva, aqui é a Quatro Ventos.');
    }

    public function test_lead_notifies_by_whatsapp(): void
    {
        Mail::fake();
        Queue::fake();
        $this->makeSettings();

        $this->postJson('/api/contato/lead', [
            'nome' => 'Bruno',
            'whatsapp' => '41 97777-6666',
            'pagina' => '/sites',
        ])->assertOk();

        Queue::assertPushed(SendWhatsAppMessageJob::class, fn (SendWhatsAppMessageJob $job) => $job->number === '5541977776666');
        Queue::assertPushed(SendWhatsAppMessageJob::class, fn (SendWhatsAppMessageJob $job) => $job->number === '5541988887777');
    }

    public function test_nothing_is_sent_when_whatsapp_is_disabled(): void
    {
        Mail::fake();
        Queue::fake();
        $this->makeSettings(['whatsapp_enabled' => false]);

        $this->postContact();

        Queue::assertNotPushed(SendWhatsAppMessageJob::class);
    }

    public function test_email_is_still_queued_when_whatsapp_is_enabled(): void
    {
        Queue::fake();
        $this->makeSettings();

        $this->postContact();

        Queue::assertPushed(SendContactMailJob::class, 1);
    }

    public function test_job_sends_text_through_evolution_api(): void
    {
        $this->makeSettings();
        Http::fake(['evo.example.com/*' => Http::response(['key' => ['id' => 'abc']], 201)]);

        (new SendWhatsAppMessageJob('5541988887777', 'Olá'))->handle();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://evo.example.com/message/sendText/site'
            && $request->hasHeader('apikey', 'evo-key')
            && $request['number'] === '5541988887777'
            && $request['text'] === 'Olá');
    }

    public function test_contact_phone_stays_required_even_if_schema_marks_it_optional(): void
    {
        Mail::fake();
        $this->makeSettings([
            'form_schemas' => ['contact' => ['sections' => [[
                'label' => 'Campos',
                'fields' => [
                    ['name' => 'nome', 'label' => 'Nome', 'type' => 'text', 'required' => true],
                    ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => true],
                    ['name' => 'telefone', 'label' => 'WhatsApp', 'type' => 'tel', 'required' => false],
                    ['name' => 'mensagem', 'label' => 'Mensagem', 'type' => 'textarea', 'required' => true],
                    ['name' => 'lgpd', 'label' => 'LGPD', 'type' => 'checkbox', 'required' => true],
                ],
            ]]]],
        ]);

        $this->postJson('/contato', [
            'nome' => 'Ana',
            'email' => 'ana@example.com',
            'mensagem' => 'Oi',
            'lgpd' => '1',
        ])->assertJsonValidationErrors('telefone');
    }

    public function test_admin_saves_whatsapp_settings_without_exposing_api_key(): void
    {
        $this->makeSettings(['evolution_api_key' => 'old-key']);

        $this->actingAs($this->makeAdmin())
            ->putJson('/gestao/api/site', [
                'name' => 'Quatro Ventos',
                'whatsapp_enabled' => true,
                'evolution_url' => 'https://evo.example.com',
                'evolution_api_key' => '',
                'evolution_instance' => 'nova',
                'whatsapp_notify_number' => '41988887777',
            ])
            ->assertOk()
            ->assertJsonMissingPath('data.evolution_api_key')
            ->assertJsonPath('data.evolution_api_key_set', true)
            ->assertJsonPath('data.evolution_instance', 'nova');

        $this->assertSame('old-key', SiteSetting::query()->first()->evolution_api_key);
    }

    public function test_connection_test_reports_disconnected_instance(): void
    {
        $this->makeSettings();
        Http::fake(['evo.example.com/instance/connectionState/site' => Http::response(['instance' => ['state' => 'close']])]);

        $this->actingAs($this->makeAdmin())
            ->postJson('/gestao/api/site/whatsapp/test')
            ->assertJsonValidationErrors('evolution_url');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'sendText'));
    }

    public function test_connection_test_sends_message_when_instance_is_open(): void
    {
        $this->makeSettings();
        Http::fake([
            'evo.example.com/instance/connectionState/site' => Http::response(['instance' => ['state' => 'open']]),
            'evo.example.com/message/sendText/site' => Http::response(['key' => ['id' => 'abc']], 201),
        ]);

        $this->actingAs($this->makeAdmin())
            ->postJson('/gestao/api/site/whatsapp/test')
            ->assertOk()
            ->assertJsonPath('status', 'sent');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'sendText') && $request['number'] === '5541988887777');
    }
}
