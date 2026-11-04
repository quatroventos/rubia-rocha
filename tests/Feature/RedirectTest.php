<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Redirect;
use Database\Seeders\RedirectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_url_redirects_permanently_keeping_query_and_counting_hits(): void
    {
        $redirect = Redirect::factory()->create(['from_path' => '/sobre-2', 'to_url' => '/quem-sou']);

        $this->get('/sobre-2/?utm_source=google')
            ->assertStatus(301)
            ->assertRedirect(url('/quem-sou').'?utm_source=google');

        $this->assertSame(1, $redirect->fresh()->hits);
        $this->assertNotNull($redirect->fresh()->last_hit_at);
    }

    public function test_nested_legacy_url_and_external_destination(): void
    {
        Redirect::factory()->create(['from_path' => '/ensaios/ensaio-de-gestante-curitiba', 'to_url' => '/ensaios/gestante']);
        Redirect::factory()->create(['from_path' => '/loja', 'to_url' => 'https://loja.example.com', 'status_code' => 302]);

        $this->get('/ensaios/ensaio-de-gestante-curitiba/')->assertStatus(301)->assertRedirect(url('/ensaios/gestante'));
        $this->get('/loja')->assertStatus(302)->assertRedirect('https://loja.example.com');
    }

    public function test_inactive_redirect_and_published_page_are_not_redirected(): void
    {
        Page::query()->create(['slug' => 'contato', 'template' => 'custom', 'status' => 'published', 'published_at' => now(), 'seo_title' => 'Contato', 'content' => ['sections' => []]]);
        Redirect::factory()->create(['from_path' => '/contato', 'to_url' => '/']);
        Redirect::factory()->create(['from_path' => '/pausado', 'to_url' => '/', 'is_active' => false]);

        $this->get('/contato')->assertOk();
        $this->get('/pausado')->assertNotFound();
    }

    public function test_editor_manages_redirects_with_normalized_origin(): void
    {
        $editor = $this->makeEditor();

        $id = $this->actingAs($editor)->postJson('/gestao/api/redirects', [
            'from_path' => 'https://fotografacuritiba.com.br/Sobre-2/',
            'to_url' => '/quem-sou',
        ])->assertCreated()
            ->assertJsonPath('data.from_path', '/sobre-2')
            ->assertJsonPath('data.status_code', 301)
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->actingAs($editor)->putJson("/gestao/api/redirects/{$id}", ['from_path' => '/sobre-2', 'to_url' => '/contato', 'status_code' => 302, 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.to_url', '/contato')
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($editor)->deleteJson("/gestao/api/redirects/{$id}")->assertNoContent();
        $this->assertSoftDeleted('redirects', ['id' => $id]);
    }

    public function test_rejects_duplicate_published_page_and_self_redirect(): void
    {
        $editor = $this->makeEditor();
        Page::query()->create(['slug' => 'ensaios', 'template' => 'custom', 'status' => 'published', 'published_at' => now(), 'seo_title' => 'Ensaios', 'content' => ['sections' => []]]);
        Redirect::factory()->create(['from_path' => '/portfolio', 'to_url' => '/ensaios']);

        $this->actingAs($editor)->postJson('/gestao/api/redirects', ['from_path' => '/portfolio/', 'to_url' => '/'])
            ->assertJsonValidationErrors('from_path');
        $this->actingAs($editor)->postJson('/gestao/api/redirects', ['from_path' => '/ensaios', 'to_url' => '/'])
            ->assertJsonValidationErrors('from_path');
        $this->actingAs($editor)->postJson('/gestao/api/redirects', ['from_path' => '/antigo', 'to_url' => '/antigo/'])
            ->assertJsonValidationErrors('to_url');
        $this->actingAs($editor)->postJson('/gestao/api/redirects', ['from_path' => '/antigo', 'to_url' => 'javascript:alert(1)'])
            ->assertJsonValidationErrors('to_url');
    }

    public function test_guest_cannot_manage_redirects(): void
    {
        $this->getJson('/gestao/api/redirects')->assertUnauthorized();
    }

    public function test_seeder_maps_legacy_pages_without_restoring_cms_changes(): void
    {
        $this->seed(RedirectSeeder::class);
        $this->assertSame(count(RedirectSeeder::LEGACY_PAGES), Redirect::query()->count());

        Redirect::query()->where('from_path', '/sobre-2')->first()->delete();
        Redirect::query()->where('from_path', '/portfolio')->update(['to_url' => '/eventos']);

        $this->seed(RedirectSeeder::class);

        $this->assertSoftDeleted('redirects', ['from_path' => '/sobre-2']);
        $this->assertDatabaseHas('redirects', ['from_path' => '/portfolio', 'to_url' => '/eventos']);
    }
}
