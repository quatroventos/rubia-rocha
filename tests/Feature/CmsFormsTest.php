<?php

namespace Tests\Feature;

use App\Cms\FormDefinition;
use App\Models\ContactSubmission;
use App\Models\FormOption;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_list_forms(): void
    {
        $this->actingAs($this->makeEditor())
            ->getJson('/gestao/api/forms')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'contact');
    }

    public function test_admin_lists_site_forms(): void
    {
        FormOption::query()->create([
            'form' => 'contact',
            'field' => 'destino',
            'label' => 'Comercial',
            'value' => 'Comercial',
            'sort' => 1,
        ]);

        $this->actingAs($this->makeAdmin())
            ->getJson('/gestao/api/forms')
            ->assertOk()
            ->assertJsonPath('data.0.key', 'contact')
            ->assertJsonCount(1, 'data');
    }

    public function test_admin_can_sync_contact_destinations(): void
    {
        $this->makeSettings();

        $existing = FormOption::query()->create([
            'form' => 'contact',
            'field' => 'destino',
            'label' => 'Comercial',
            'value' => 'Comercial',
            'sort' => 1,
        ]);

        $this->actingAs($this->makeAdmin())
            ->putJson('/gestao/api/forms/contact', $this->contactPayload([
                ['id' => $existing->id, 'label' => 'Comercial', 'value' => 'Comercial', 'email' => 'comercial@example.com', 'sort' => 1],
                ['id' => null, 'label' => 'Imprensa', 'value' => 'Imprensa', 'email' => 'imprensa@example.com', 'sort' => 2],
            ]))
            ->assertOk()
            ->assertJsonPath('data.key', 'contact')
            ->assertJsonCount(2, 'data.sections.0.fields.3.options');

        $this->assertDatabaseHas('form_options', [
            'form' => 'contact',
            'field' => 'destino',
            'label' => 'Imprensa',
            'email' => 'imprensa@example.com',
        ]);
        $this->assertDatabaseHas('form_options', [
            'form' => 'contact',
            'field' => 'destino',
            'label' => 'Comercial',
            'email' => 'comercial@example.com',
        ]);
    }

    public function test_admin_can_add_and_edit_contact_fields(): void
    {
        $this->makeSettings();
        $this->makeContactPage();

        FormOption::query()->create([
            'form' => 'contact',
            'field' => 'destino',
            'label' => 'Comercial',
            'value' => 'Comercial',
            'sort' => 1,
        ]);

        $this->actingAs($this->makeAdmin())
            ->putJson('/gestao/api/forms/contact', $this->contactPayload(
                [['id' => null, 'label' => 'Comercial', 'value' => 'Comercial', 'sort' => 1]],
                [
                    ['name' => 'empresa', 'label' => 'Empresa', 'type' => 'text', 'required' => true],
                ],
                ['nome' => 'Nome completo']
            ))
            ->assertOk()
            ->assertJsonPath('data.sections.0.fields.0.label', 'Nome completo')
            ->assertJsonPath('data.sections.0.fields.4.name', 'empresa')
            ->assertJsonPath('data.sections.0.fields.4.label', 'Empresa');

        $this->get('/contato')
            ->assertOk()
            ->assertSee('Nome completo')
            ->assertSee('Empresa');

        $this->post('/contato', [
            'nome' => 'Ana',
            'email' => 'ana@example.com',
            'telefone' => '16999999999',
            'destino' => 'Comercial',
            'mensagem' => 'Olá',
            'empresa' => 'Quatro Ventos',
            'lgpd' => '1',
        ])->assertRedirect();

        $submission = ContactSubmission::query()->first();

        $this->assertNotNull($submission);
        $this->assertSame('Ana', $submission->name);
        $this->assertSame('Empresa', $submission->extra['empresa']['label']);
        $this->assertSame('Quatro Ventos', $submission->extra['empresa']['value']);
    }

    public function test_unknown_form_is_not_found(): void
    {
        $this->actingAs($this->makeAdmin())
            ->getJson('/gestao/api/forms/newsletter')
            ->assertNotFound();
    }

    /**
     * @param  list<array<string, mixed>>  $destinoOptions
     * @param  list<array<string, mixed>>  $extraFields
     * @param  array<string, string>  $labelOverrides
     * @return array<string, mixed>
     */
    private function contactPayload(array $destinoOptions, array $extraFields = [], array $labelOverrides = []): array
    {
        $form = FormDefinition::get('contact');
        $sections = $form['sections'];

        foreach ($sections as &$section) {
            foreach ($section['fields'] as &$field) {
                if (isset($labelOverrides[$field['name']])) {
                    $field['label'] = $labelOverrides[$field['name']];
                }

                if ($field['name'] === 'destino') {
                    $field['options'] = $destinoOptions;
                }
            }
            unset($field);
        }
        unset($section);

        if ($extraFields !== []) {
            $sections[0]['fields'] = array_merge($sections[0]['fields'], $extraFields);
        }

        return [
            'submit_label' => $form['submit_label'],
            'lgpd_label' => $form['lgpd_label'],
            'sections' => $sections,
        ];
    }

    private function makeSettings(): SiteSetting
    {
        return SiteSetting::query()->create([
            'name' => 'Quatro Ventos',
            'og_locale' => 'pt_BR',
            'twitter_card' => 'summary_large_image',
        ]);
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
