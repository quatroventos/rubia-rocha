<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SectionCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.key' => 'test-key',
            'services.openai.base_url' => 'https://openai.test/v1',
        ]);
    }

    /**
     * @param  list<array{path: string, value: string}>  $texts
     */
    private function fakeOpenAi(array $texts): void
    {
        Http::fake([
            'openai.test/*' => Http::response(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['texts' => $texts])]]]]]),
        ]);
    }

    private function page(): Page
    {
        return Page::query()->create([
            'slug' => 'agentes-de-ia',
            'template' => 'custom',
            'status' => 'published',
            'published_at' => now(),
            'seo_title' => 'Agentes de IA | Rubia Rocha Fotografia',
            'content' => ['sections' => []],
        ]);
    }

    public function test_rewrites_only_text_fields_of_the_section_using_page_context(): void
    {
        $this->fakeOpenAi([
            ['path' => 'title', 'value' => 'Onde a IA *rende*'],
            ['path' => 'items.0.body', 'value' => '<p onclick="x()">Menos fila.</p><script>alert(1)</script><a href="javascript:x">link</a>'],
            ['path' => 'items.0.href', 'value' => 'https://inventado.test'],
        ]);

        $sections = [
            ['type' => 'site.prose', 'data' => ['title' => 'Contexto', 'body' => '<p>Atendemos indústrias.</p>']],
            ['type' => 'site.facts', 'data' => [
                'title' => 'Onde a IA entra',
                'tone' => 'dark',
                'items' => [['title' => 'Atendimento', 'body' => '<p>Um atendimento que espera na fila.</p>', 'href' => '/contato']],
            ]],
        ];

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/sections/copy', [
                'kind' => 'page',
                'page_id' => $this->page()->id,
                'index' => 1,
                'mode' => 'resumir',
                'title' => 'Agentes de IA',
                'sections' => $sections,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Onde a IA *rende*')
            ->assertJsonPath('data.items.0.body', '<p>Menos fila.</p>alert(1)<a>link</a>')
            ->assertJsonPath('data.items.0.title', 'Atendimento')
            ->assertJsonPath('data.items.0.href', '/contato')
            ->assertJsonPath('data.tone', 'dark');

        Http::assertSent(function (Request $request): bool {
            $body = (string) json_encode($request->data(), JSON_UNESCAPED_UNICODE);
            $paths = $request['text']['format']['schema']['properties']['texts']['items']['properties']['path']['enum'];

            return str_contains($body, 'AI Slop')
                && str_contains($body, 'regra de três')
                && str_contains($body, 'Atendemos indústrias')
                && str_contains($body, 'Resuma')
                && $paths === ['title', 'items.0.title', 'items.0.body'];
        });
    }

    public function test_single_field_is_written_from_context_even_when_empty(): void
    {
        $this->fakeOpenAi([
            ['path' => 'items.0.title', 'value' => 'Fila zerada'],
        ]);

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/sections/copy', [
                'kind' => 'page',
                'page_id' => $this->page()->id,
                'index' => 0,
                'mode' => 'melhorar',
                'field' => 'items.0.title',
                'sections' => [['type' => 'site.facts', 'data' => [
                    'title' => 'Resultados',
                    'items' => [['body' => '<p>O atendimento deixou de esperar.</p>']],
                ]]],
            ])
            ->assertOk()
            ->assertJsonPath('value', 'Fila zerada')
            ->assertJsonPath('data.title', 'Resultados');

        Http::assertSent(function (Request $request): bool {
            $body = (string) json_encode($request->data(), JSON_UNESCAPED_UNICODE);
            $paths = $request['text']['format']['schema']['properties']['texts']['items']['properties']['path']['enum'];

            return $paths === ['items.0.title']
                && str_contains($body, 'Escreva: o campo está vazio')
                && str_contains($body, 'O atendimento deixou de esperar');
        });
    }

    public function test_field_outside_the_text_fields_is_rejected(): void
    {
        Http::fake();

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/sections/copy', [
                'kind' => 'page',
                'page_id' => $this->page()->id,
                'index' => 0,
                'mode' => 'melhorar',
                'field' => 'items.0.href',
                'sections' => [['type' => 'site.facts', 'data' => ['items' => [['title' => 'A', 'href' => '/x']]]]],
            ])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_section_without_text_is_rejected_without_calling_the_ai(): void
    {
        Http::fake();

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/sections/copy', [
                'kind' => 'page',
                'page_id' => $this->page()->id,
                'index' => 0,
                'mode' => 'melhorar',
                'sections' => [['type' => 'site.prose', 'data' => ['title' => '', 'body' => '']]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta seção não tem texto para a IA trabalhar. Escreva um rascunho primeiro.');

        Http::assertNothingSent();
    }

    public function test_seo_description_is_written_from_the_whole_page(): void
    {
        Http::fake([
            'openai.test/*' => Http::response(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['value' => '"Agentes de IA que *atendem* clientes.<br>"'])]]]]]),
        ]);

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/sections/copy', [
                'kind' => 'page',
                'target' => 'seo',
                'page_id' => $this->page()->id,
                'mode' => 'melhorar',
                'field' => 'seo_description',
                'seo_title' => 'Agentes de IA',
                'sections' => [
                    ['type' => 'site.prose', 'data' => ['title' => 'Atendimento', 'body' => '<p>Respondemos clientes no WhatsApp.</p>']],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('value', 'Agentes de IA que atendem clientes.');

        Http::assertSent(function (Request $request): bool {
            $body = (string) json_encode($request->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($body, 'Respondemos clientes no WhatsApp')
                && str_contains($body, 'entre 120 e 160 caracteres')
                && str_contains($body, 'Título SEO atual (contexto, não reescreva): Agentes de IA');
        });
    }

    public function test_seo_title_out_of_range_is_retried_then_fitted_to_the_budget(): void
    {
        $long = 'Agentes de IA para atendimento no WhatsApp com integração ao seu ERP | Rubia Rocha Fotografia';
        $stillLong = 'Agentes de IA para atendimento no WhatsApp integrados ao seu ERP';
        Http::fakeSequence('openai.test/*')
            ->push(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['value' => $long])]]]]])
            ->push(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['value' => $stillLong])]]]]]);

        $value = $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/sections/copy', [
                'kind' => 'page',
                'target' => 'seo',
                'page_id' => $this->page()->id,
                'mode' => 'melhorar',
                'field' => 'seo_title',
                'sections' => [['type' => 'site.prose', 'data' => ['title' => 'Atendimento', 'body' => '<p>WhatsApp.</p>']]],
            ])
            ->assertOk()
            ->json('value');

        $this->assertSame('Agentes de IA para atendimento no', $value);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains((string) json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'no máximo 35 caracteres'));
    }

    public function test_seo_target_rejects_other_fields(): void
    {
        Http::fake();

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/sections/copy', [
                'kind' => 'page',
                'page_id' => $this->page()->id,
                'target' => 'seo',
                'mode' => 'melhorar',
                'field' => 'canonical',
                'sections' => [['type' => 'site.prose', 'data' => ['title' => 'Oi']]],
            ])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_guest_cannot_rewrite(): void
    {
        $this->postJson('/gestao/api/sections/copy', [
            'kind' => 'page',
            'page_id' => 1,
            'index' => 0,
            'mode' => 'melhorar',
            'sections' => [['type' => 'site.prose', 'data' => ['title' => 'Oi']]],
        ])->assertUnauthorized();
    }

    private function fakeProofread(string $value): void
    {
        Http::fake([
            'openai.test/*' => Http::response(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['value' => $value])]]]]]),
        ]);
    }

    public function test_proofread_returns_the_corrected_text_keeping_markup(): void
    {
        $this->fakeProofread('<p>Agentes de IA que <strong>atendem</strong> os clientes e *executam* tarefas.</p>');

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/sections/proofread', ['text' => '<p>Agentes de IA qeu <strong>atendem</strong> os cliente e *executam* tarefas.</p>'])
            ->assertOk()
            ->assertJsonPath('changed', true)
            ->assertJsonPath('value', '<p>Agentes de IA que <strong>atendem</strong> os clientes e *executam* tarefas.</p>');

        Http::assertSent(fn (Request $request) => str_contains((string) json_encode($request->data()), 'qeu'));
    }

    public function test_proofread_reports_no_change_when_text_is_correct(): void
    {
        $this->fakeProofread('Agentes de IA para empresas.');

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/sections/proofread', ['text' => 'Agentes de IA para empresas.'])
            ->assertOk()
            ->assertJsonPath('changed', false);
    }

    public function test_proofread_rejects_changes_to_markup(): void
    {
        $this->fakeProofread('<p>Agentes de IA que atendem clientes.</p>');

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/sections/proofread', ['text' => '<p>Agentes de IA que <strong>atendem</strong> clientes.</p>'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A correção alterou a formatação do texto; nada foi aplicado.');
    }

    public function test_proofread_rejects_rewrites(): void
    {
        $this->fakeProofread('Soluções inteligentes que transformam o atendimento da sua empresa com automação.');

        $this->actingAs($this->makeEditor())
            ->postJson('/gestao/api/sections/proofread', ['text' => 'Agentes de IA que atendem clientes.'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A IA mudou o texto além de uma correção; nada foi aplicado.');
    }
}
