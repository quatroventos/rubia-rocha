<?php

namespace Tests\Feature;

use App\Models\CareerSubmission;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CmsAclTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_list_users(): void
    {
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->getJson('/gestao/api/users')
            ->assertOk();
    }

    public function test_editor_cannot_view_audit_but_can_view_forms(): void
    {
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->getJson('/gestao/api/audit')
            ->assertForbidden();

        $this->actingAs($editor)
            ->getJson('/gestao/api/forms')
            ->assertOk();
    }

    public function test_admin_can_list_users(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->getJson('/gestao/api/users')
            ->assertOk();
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->deleteJson('/gestao/api/users/'.$admin->id)
            ->assertForbidden();
    }

    public function test_editor_can_create_page(): void
    {
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->postJson('/gestao/api/pages', [
                'seo_title' => 'Teste',
                'slug' => 'teste',
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'teste')
            ->assertJsonPath('data.seo_title', 'Teste')
            ->assertJsonPath('data.template', 'custom');

        $this->assertDatabaseHas('pages', [
            'slug' => 'teste',
            'seo_title' => 'Teste',
            'template' => 'custom',
        ]);
    }

    public function test_reserved_slug_is_rejected_with_message(): void
    {
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->postJson('/gestao/api/pages', [
                'seo_title' => 'Analytics',
                'slug' => 't',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.slug.0', 'Este endereço está reservado pelo sistema.');
    }

    public function test_home_cannot_be_deleted(): void
    {
        $admin = $this->makeAdmin();
        $page = Page::query()->create([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'draft',
            'seo_title' => 'Home',
            'content' => [],
        ]);

        $this->actingAs($admin)
            ->deleteJson('/gestao/api/pages/'.$page->slug)
            ->assertForbidden();
    }

    public function test_editor_can_update_page(): void
    {
        $editor = $this->makeEditor();
        $page = Page::query()->create([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'draft',
            'seo_title' => 'Home',
            'content' => [],
        ]);

        $this->actingAs($editor)
            ->putJson('/gestao/api/pages/home', [
                'status' => 'published',
                'seo_title' => 'Home publicada',
                'content' => ['hero' => ['subtitle' => 'ok']],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertNotNull($page->fresh()->published_at);
    }

    public function test_slug_change_updates_menu_links_to_the_page(): void
    {
        $page = Page::query()->create([
            'slug' => 'sobre',
            'template' => 'about',
            'status' => 'published',
            'seo_title' => 'Sobre',
            'content' => [],
            'published_at' => now(),
        ]);

        $linked = MenuItem::query()->create(['group' => 'header', 'label' => 'Sobre', 'href' => '/sobre', 'page_id' => $page->id, 'sort' => 1, 'is_active' => true]);
        $loose = MenuItem::query()->create(['group' => 'footer', 'label' => 'Empresa', 'href' => '/sobre#equipe', 'sort' => 2, 'is_active' => true]);
        $other = MenuItem::query()->create(['group' => 'footer', 'label' => 'Contato', 'href' => '/contato', 'sort' => 3, 'is_active' => true]);

        $this->actingAs($this->makeEditor())
            ->putJson('/gestao/api/pages/sobre', [
                'slug' => 'sobre-a-quatro-ventos',
                'seo_title' => 'Sobre',
                'content' => ['sections' => [['id' => 's1', 'type' => 'site.hero', 'data' => ['title' => 'Sobre']]]],
            ])
            ->assertOk();

        $this->assertSame('/sobre-a-quatro-ventos', $linked->fresh()->href);
        $this->assertSame('/sobre-a-quatro-ventos#equipe', $loose->fresh()->href);
        $this->assertSame($page->id, $loose->fresh()->page_id);
        $this->assertSame('/contato', $other->fresh()->href);
    }

    public function test_editor_can_update_page_slug(): void
    {
        SiteSetting::query()->create([
            'name' => 'Quatro Ventos',
            'og_locale' => 'pt_BR',
            'twitter_card' => 'summary_large_image',
        ]);

        $page = Page::query()->create([
            'slug' => 'quem-somos',
            'template' => 'about',
            'status' => 'published',
            'seo_title' => 'Quem somos',
            'content' => [],
            'published_at' => now(),
        ]);

        $this->get('/quem-somos')->assertOk();

        $this->actingAs($this->makeEditor())
            ->putJson('/gestao/api/pages/quem-somos', [
                'status' => 'published',
                'slug' => 'o-escritorio',
                'seo_title' => 'Quem somos',
                'content' => ['hero' => ['title' => 'Quem somos']],
            ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'o-escritorio');

        $this->flushHeaders();
        $this->get('/o-escritorio')->assertOk();
        $this->get('/quem-somos')->assertNotFound();
        $this->assertSame('o-escritorio', $page->fresh()->slug);
    }

    public function test_home_slug_cannot_be_changed(): void
    {
        $page = Page::query()->create([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'published',
            'seo_title' => 'Home',
            'content' => [],
            'published_at' => now(),
        ]);

        $this->actingAs($this->makeEditor())
            ->putJson('/gestao/api/pages/home', [
                'status' => 'published',
                'slug' => 'inicio',
                'seo_title' => 'Home',
                'content' => ['hero' => ['subtitle' => 'ok']],
            ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'home');

        $this->assertSame('home', $page->fresh()->slug);
    }

    public function test_editor_can_update_tags(): void
    {
        $this->makeSettings();
        $editor = $this->makeEditor();

        $this->actingAs($editor)
            ->putJson('/gestao/api/tags', ['tag_header' => '<script>analytics</script>'])
            ->assertOk()
            ->assertJsonPath('data.tag_header', '<script>analytics</script>');
    }

    public function test_authenticated_resume_download(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('resumes/cv.pdf', 'pdf');

        $submission = CareerSubmission::query()->create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'phone' => '16999999999',
            'location' => 'Ribeirão Preto',
            'area' => 'Jurídico',
            'seniority' => 'Pleno',
            'resume_path' => 'resumes/cv.pdf',
            'resume_name' => 'cv.pdf',
        ]);

        $this->actingAs($this->makeEditor())
            ->get("/gestao/api/careers/{$submission->id}/download")
            ->assertOk();
    }

    public function test_guest_cannot_download_resume(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('resumes/cv.pdf', 'pdf');

        $submission = CareerSubmission::query()->create([
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'phone' => '16999999999',
            'location' => 'Ribeirão Preto',
            'area' => 'Jurídico',
            'seniority' => 'Pleno',
            'resume_path' => 'resumes/cv.pdf',
            'resume_name' => 'cv.pdf',
        ]);

        $this->get("/gestao/api/careers/{$submission->id}/download")
            ->assertStatus(401);
    }

    public function test_guest_cannot_preview_page(): void
    {
        $this->makePage();

        $this->get('/gestao/preview/home')->assertNotFound();
    }

    public function test_editor_can_preview_page(): void
    {
        $this->makeSettings();
        $this->makePage();

        $this->actingAs($this->makeEditor())
            ->get('/gestao/preview/home?cms_preview=1')
            ->assertOk();
    }

    private function makeSettings(): void
    {
        SiteSetting::query()->create([
            'name' => 'Quatro Ventos',
            'og_locale' => 'pt_BR',
            'twitter_card' => 'summary_large_image',
        ]);
    }

    private function makePage(): void
    {
        Page::query()->create([
            'slug' => 'home',
            'template' => 'home',
            'status' => 'published',
            'seo_title' => 'Home',
            'content' => [],
            'published_at' => now(),
        ]);
    }
}
